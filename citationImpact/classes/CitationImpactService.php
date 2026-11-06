<?php

/**
 * @file plugins/generic/citationImpact/classes/CitationImpactService.php
 *
 * Distributed under the GNU GPL v3.
 *
 * @class CitationImpactService
 * @brief Reads published articles with DOIs from the OJS database, fetches
 *  citation counts from the Dimensions Metrics API and keeps them in a small
 *  JSON store inside the OJS files directory.
 *
 *  OJS 3.5: DOIs live in the `dois` table (publications.doi_id), not in
 *  publication_settings as in OJS 3.3.
 */

namespace APP\plugins\generic\citationImpact\classes;

use APP\core\Application;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PKP\config\Config;
use PKP\db\DAORegistry;

class CitationImpactService {

	const PLUGIN_NAME = 'citationimpactplugin';
	const DEFAULT_API = 'https://metrics-api.dimensions.ai/doi/';
	const OPENALEX_API = 'https://api.openalex.org';
	const STATUS_PUBLISHED = 3;

	/** @var int */
	protected $contextId;

	/** @var array */
	protected $settings;

	/**
	 * @param int $contextId
	 * @param array $settings Plugin settings for this context (see getSettings()).
	 */
	public function __construct($contextId, $settings = null) {
		$this->contextId = (int) $contextId;
		$this->settings = $settings === null ? self::getSettings($contextId) : $settings;
	}

	/**
	 * Plugin settings with defaults, read straight from the settings table so
	 * the scheduled task works without the plugin being loaded.
	 * @param int $contextId
	 * @return array
	 */
	public static function getSettings($contextId) {
		$dao = DAORegistry::getDAO('PluginSettingsDAO'); /** @var \PKP\plugins\PluginSettingsDAO $dao */
		$get = function ($name, $default) use ($dao, $contextId) {
			$value = $dao->getSetting((int) $contextId, self::PLUGIN_NAME, $name);
			return ($value === null || $value === '') ? $default : $value;
		};
		return array(
			'contactEmail' => (string) $get('contactEmail', ''),
			'listMode' => $get('listMode', 'cited') === 'all' ? 'all' : 'cited',
			'windowIncludeCurrent' => (string) $get('windowIncludeCurrent', '1') !== '0',
			'ttlDays' => max(1, (int) $get('ttlDays', 7)),
			'requestDelayMs' => max(0, (int) $get('requestDelayMs', 300)),
			'pagePath' => (string) $get('pagePath', 'impact'),
			'apiUrl' => (string) $get('apiUrl', self::DEFAULT_API),
			'accentColor' => (string) $get('accentColor', ''),
			// both: Dimensions is the main count, OpenAlex shown beside it for comparison;
			// max: one count per article, the higher of the two.
			'sources' => in_array($get('sources', 'both'), array('both', 'max', 'dimensions', 'openalex'), true) ? $get('sources', 'both') : 'both',
			'openAlexKey' => trim((string) $get('openAlexKey', '')),
			'articleBadge' => (string) $get('articleBadge', '1') !== '0',
		);
	}

	/**
	 * @param array $cites Citation counts.
	 * @return int h-index of the set.
	 */
	public static function hIndex(array $cites) {
		rsort($cites);
		$h = 0;
		foreach ($cites as $i => $c) {
			if ($c >= $i + 1) {
				$h = $i + 1;
			} else {
				break;
			}
		}
		return $h;
	}

	/**
	 * Publication years of the "last 3 years" window (ARJUNA "Dampak Ilmiah").
	 * @param bool $includeCurrent true: current year and the two before; false: the three full years before.
	 * @return array [from, to]
	 */
	public static function windowYears($includeCurrent) {
		$y = (int) date('Y');
		return $includeCurrent ? array($y - 2, $y) : array($y - 3, $y - 1);
	}

	/**
	 * @param int $contextId
	 * @return bool
	 */
	public static function isEnabledFor($contextId) {
		$dao = DAORegistry::getDAO('PluginSettingsDAO'); /** @var \PKP\plugins\PluginSettingsDAO $dao */
		return (bool) $dao->getSetting((int) $contextId, self::PLUGIN_NAME, 'enabled');
	}

	//
	// Storage
	//

	/**
	 * @return string Directory holding this plugin's data, outside the web root.
	 */
	protected function getDataDir() {
		$dir = rtrim(Config::getVar('files', 'files_dir'), '/\\') . DIRECTORY_SEPARATOR . 'citationImpact';
		if (!is_dir($dir)) {
			@mkdir($dir, 0755, true);
		}
		return $dir;
	}

	protected function getStorePath() {
		return $this->getDataDir() . DIRECTORY_SEPARATOR . 'context-' . $this->contextId . '.json';
	}

	/**
	 * @return array {items: {doi: {c, r, f, s, t}}, updatedAt, backoffUntil, license}
	 */
	public function readStore() {
		$file = $this->getStorePath();
		if (!is_file($file)) {
			return array('items' => array());
		}
		$data = json_decode((string) file_get_contents($file), true);
		if (!is_array($data)) {
			return array('items' => array());
		}
		if (!isset($data['items']) || !is_array($data['items'])) {
			$data['items'] = array();
		}
		return $data;
	}

	/**
	 * The stored numbers of one DOI (read once per request), or null.
	 */
	public function item($doi) {
		static $cache = array();
		if (!isset($cache[$this->contextId])) {
			$cache[$this->contextId] = $this->readStore()['items'];
		}
		$k = strtolower((string) $doi);
		return isset($cache[$this->contextId][$k]) ? $cache[$this->contextId][$k] : null;
	}

	protected function writeStore(array $data) {
		$file = $this->getStorePath();
		$tmp = $file . '.tmp' . getmypid();
		$json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
		if ($json === false || file_put_contents($tmp, $json) === false || !rename($tmp, $file)) {
			@unlink($tmp);
			throw new Exception('Citation Impact: cannot write ' . $file);
		}
	}

	//
	// Articles
	//

	/**
	 * Published articles of this journal that have a DOI.
	 * @return array List of [submissionId, publicationId, doi, date, urlPath, locale], newest first.
	 */
	public function getArticles() {
		$rows = DB::table('submissions as s')
			->join('publications as p', 'p.publication_id', '=', 's.current_publication_id')
			->join('dois as d', 'd.doi_id', '=', 'p.doi_id')
			->where('s.context_id', '=', $this->contextId)
			->where('s.status', '=', self::STATUS_PUBLISHED)
			->where('p.status', '=', self::STATUS_PUBLISHED)
			->whereNotNull('d.doi')
			->where('d.doi', '<>', '')
			->orderBy('p.date_published', 'desc')
			->select(self::hasUrlPath()
				? array('s.submission_id', 'p.publication_id', 'p.date_published', 'p.url_path', 's.locale', 'd.doi as doi')
				: array('s.submission_id', 'p.publication_id', 'p.date_published', 's.locale', 'd.doi as doi'))
			->get();

		$articles = array();
		$seen = array();
		foreach ($rows as $row) {
			$doi = self::normalizeDoi($row->doi);
			if ($doi === null || isset($seen[strtolower($doi)])) {
				continue;
			}
			$seen[strtolower($doi)] = true;
			$articles[] = array(
				'submissionId' => (int) $row->submission_id,
				'publicationId' => (int) $row->publication_id,
				'doi' => $doi,
				'date' => $row->date_published ? substr((string) $row->date_published, 0, 10) : '',
				'urlPath' => !empty($row->url_path) ? (string) $row->url_path : null,
				'locale' => $row->locale ? (string) $row->locale : null,
			);
		}
		return $articles;
	}

	/**
	 * publications.url_path is checked once per request, so the query keeps
	 * working on databases whose schema differs slightly.
	 * @return bool
	 */
	protected static function hasUrlPath() {
		static $has = null;
		if ($has === null) {
			try {
				$has = Schema::hasColumn('publications', 'url_path');
			} catch (\Throwable $e) {
				$has = false;
			}
		}
		return $has;
	}

	/**
	 * @return int Published articles without a DOI (not counted).
	 */
	public function countWithoutDoi() {
		return (int) DB::table('submissions as s')
			->join('publications as p', 'p.publication_id', '=', 's.current_publication_id')
			->leftJoin('dois as d', 'd.doi_id', '=', 'p.doi_id')
			->where('s.context_id', '=', $this->contextId)
			->where('s.status', '=', self::STATUS_PUBLISHED)
			->where('p.status', '=', self::STATUS_PUBLISHED)
			->where(function ($q) {
				$q->whereNull('d.doi')->orWhere('d.doi', '=', '');
			})
			->count();
	}

	/**
	 * Titles and author names for a set of publications, in the best locale.
	 * @param array $publicationIds
	 * @param string $locale Preferred locale (UI locale).
	 * @param array $fallbackLocales publicationId => submission locale
	 * @return array publicationId => [title, authors[]]
	 */
	public function getDisplayData(array $publicationIds, $locale, array $fallbackLocales = array()) {
		$out = array();
		$pick = function (array $byLocale, $pubLocale) use ($locale) {
			foreach (array($locale, $pubLocale, '') as $l) {
				if ($l !== null && isset($byLocale[$l]) && trim($byLocale[$l]) !== '') {
					return $byLocale[$l];
				}
			}
			foreach ($byLocale as $v) {
				if (trim((string) $v) !== '') {
					return $v;
				}
			}
			return '';
		};
		foreach (array_chunk(array_values(array_unique(array_map('intval', $publicationIds))), 500) as $chunk) {
			$titles = array();
			foreach (DB::table('publication_settings')->whereIn('publication_id', $chunk)
				->whereIn('setting_name', array('title', 'prefix'))->get() as $r) {
				$titles[(int) $r->publication_id][$r->setting_name][(string) $r->locale] = (string) $r->setting_value;
			}
			$authors = array();
			foreach (DB::table('authors as a')->join('author_settings as aset', 'aset.author_id', '=', 'a.author_id')
				->whereIn('a.publication_id', $chunk)->whereIn('aset.setting_name', array('givenName', 'familyName'))
				->orderBy('a.seq')->orderBy('a.author_id')
				->select('a.publication_id', 'a.author_id', 'a.seq', 'aset.locale', 'aset.setting_name', 'aset.setting_value')->get() as $r) {
				$authors[(int) $r->publication_id][(int) $r->author_id][$r->setting_name][(string) $r->locale] = (string) $r->setting_value;
			}
			foreach ($chunk as $pid) {
				$pubLocale = isset($fallbackLocales[$pid]) ? $fallbackLocales[$pid] : null;
				$t = isset($titles[$pid]['title']) ? $pick($titles[$pid]['title'], $pubLocale) : '';
				$p = isset($titles[$pid]['prefix']) ? $pick($titles[$pid]['prefix'], $pubLocale) : '';
				$title = trim(html_entity_decode(strip_tags(trim($p . ' ' . $t)), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
				$names = array();
				if (isset($authors[$pid])) {
					foreach ($authors[$pid] as $a) {
						$given = isset($a['givenName']) ? $pick($a['givenName'], $pubLocale) : '';
						$family = isset($a['familyName']) ? $pick($a['familyName'], $pubLocale) : '';
						$name = trim($given . ' ' . $family);
						if ($name !== '') {
							$names[] = $name;
						}
					}
				}
				$out[$pid] = array('title' => $title, 'authors' => $names);
			}
		}
		return $out;
	}

	/**
	 * @param string $value
	 * @return string|null Bare DOI (10.xxxx/...) or null.
	 */
	public static function normalizeDoi($value) {
		if (!preg_match('~(10\.\d{4,9}/[^\s"<>]+)~i', (string) $value, $m)) {
			return null;
		}
		return rtrim($m[1], '.,;)]');
	}

	//
	// Refresh
	//

	/**
	 * @return string[] Enabled sources, in refresh order ("openalex" first: it is batched and fast).
	 */
	public function sources() {
		switch ($this->settings['sources']) {
			case 'dimensions': return array('dimensions');
			case 'openalex': return array('openalex');
			default: return array('openalex', 'dimensions');
		}
	}

	/**
	 * @return bool Dimensions is the main count and OpenAlex is only shown beside it.
	 */
	public function isSeparate() {
		return $this->settings['sources'] === 'both';
	}

	/**
	 * Per-source fields inside a store item. Dimensions keeps the 1.x keys
	 * (c, r, f, s, t) so existing data stays valid; OpenAlex uses o-prefixed ones.
	 */
	protected static function keys($source) {
		return $source === 'openalex'
			? array('c' => 'oc', 'r' => 'or', 's' => 'os', 't' => 'ot')
			: array('c' => 'c', 'r' => 'r', 's' => 's', 't' => 't');
	}

	/**
	 * @return bool The item has an answer (found or not found) from this source.
	 */
	public static function checkedBy($m, $source) {
		$k = self::keys($source);
		return is_array($m) && isset($m[$k['s']]) && ($m[$k['s']] === 'ok' || $m[$k['s']] === 'nf');
	}

	/**
	 * Numbers of one article across the enabled sources.
	 *
	 * In the default "separate" mode the main count (c, r, f) is Dimensions
	 * only, the figure Indonesian accreditation (ARJUNA) uses; OpenAlex is kept
	 * apart in co/ro. In "max" mode the main count is the higher of the two.
	 *
	 * @return array {ok, c, r, f, cd, co, ro, oid, okOpenAlex}; ok is true once the main count is known.
	 */
	public function merge($m) {
		$out = array('ok' => false, 'c' => null, 'r' => null, 'f' => null, 'cd' => null, 'co' => null, 'ro' => null, 'oid' => null, 'okOpenAlex' => false);
		$rd = null;
		foreach ($this->sources() as $source) {
			if (!self::checkedBy($m, $source)) {
				continue;
			}
			$k = self::keys($source);
			$c = (int) $m[$k['c']];
			$r = (int) (isset($m[$k['r']]) ? $m[$k['r']] : 0);
			if ($source === 'dimensions') {
				$out['cd'] = $c;
				$rd = $r;
				$out['f'] = isset($m['f']) ? $m['f'] : null;
			} else {
				$out['co'] = $c;
				$out['ro'] = $r;
				$out['oid'] = isset($m['oid']) ? $m['oid'] : null;
				$out['okOpenAlex'] = true;
			}
		}
		if ($this->isSeparate()) {
			$out['ok'] = $out['cd'] !== null;
			$out['c'] = $out['cd'];
			$out['r'] = $rd;
		} else {
			$out['ok'] = $out['cd'] !== null || $out['co'] !== null;
			if ($out['ok']) {
				$out['c'] = max((int) $out['cd'], (int) $out['co']);
				$out['r'] = max((int) $rd, (int) $out['ro']);
			}
		}
		return $out;
	}

	/**
	 * Re-check stale DOIs against the enabled sources.
	 * @param int $limit Max DOIs to look up per source in this call.
	 * @param float $deadline microtime(true) after which to stop.
	 * @param bool $force Re-check everything.
	 * @return array Status report.
	 */
	public function refresh($limit, $deadline, $force = false) {
		$lockPath = $this->getDataDir() . DIRECTORY_SEPARATOR . 'context-' . $this->contextId . '.lock';
		$lock = @fopen($lockPath, 'c');
		if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
			return array('status' => 'busy');
		}
		try {
			$articles = $this->getArticles();
			$store = $this->readStore();

			// Forget DOIs that are no longer published.
			$keep = array();
			foreach ($articles as $a) {
				$keep[strtolower($a['doi'])] = true;
			}
			$store['items'] = array_intersect_key($store['items'], $keep);

			$done = 0;
			$errors = 0;
			$remaining = 0;
			$stop = null;
			$waiting = array();
			foreach ($this->sources() as $source) {
				$backoffKey = $source === 'openalex' ? 'oaBackoffUntil' : 'backoffUntil';
				$queue = $this->staleQueue($articles, $store['items'], $source, $force);
				if (!$force && !empty($store[$backoffKey]) && $store[$backoffKey] > time()) {
					// Rate limited earlier: leave this source for later, keep going with the others.
					if ($queue || $this->countChecked($articles, $store['items'], $source) < count($articles)) {
						$waiting[$source] = (int) $store[$backoffKey];
					}
					continue;
				}
				if (microtime(true) >= $deadline) {
					$remaining += count($queue);
					continue;
				}
				$r = $source === 'openalex'
					? $this->refreshOpenAlex($queue, $store, $limit, $deadline)
					: $this->refreshDimensions($queue, $store, $limit, $deadline);
				$done += $r['done'];
				$errors += $r['errors'];
				if ($r['stop'] === 'rateLimited') {
					$waiting[$source] = (int) $store[$backoffKey];
				} else {
					$remaining += max(0, count($queue) - $r['done']);
				}
				if ($r['stop'] === 'failing') {
					$stop = 'failing';
				}
			}

			if ($done > 0) {
				$store['updatedAt'] = time();
			}
			$this->writeStore($store);

			if ($stop !== null) {
				$status = 'error';
			} elseif ($remaining > 0) {
				$status = 'partial';
			} elseif ($waiting) {
				$status = 'backoff';
			} else {
				$status = 'ok';
			}
			$extra = array('remaining' => $remaining, 'reason' => $stop);
			if ($waiting) {
				$extra['until'] = min($waiting);
				$extra['waiting'] = array_keys($waiting);
			}
			return $this->report($status, $articles, $store['items'], $done, $errors, $extra);
		} finally {
			flock($lock, LOCK_UN);
			fclose($lock);
		}
	}

	/**
	 * DOIs this source should look up: never checked first, then the oldest.
	 * @return array List of [key, doi]
	 */
	protected function staleQueue(array $articles, array $items, $source, $force) {
		$k = self::keys($source);
		$now = time();
		$ttl = (int) $this->settings['ttlDays'] * 86400;
		$queue = array();
		foreach ($articles as $a) {
			$key = strtolower($a['doi']);
			$m = isset($items[$key]) ? $items[$key] : null;
			$t = $m && isset($m[$k['t']]) ? (int) $m[$k['t']] : 0;
			if ($force || !$m || !isset($m[$k['s']])) {
				$stale = true;
			} elseif ($m[$k['s']] === 'err') {
				$stale = $now - $t > 3600;
			} else {
				$stale = $now - $t > $ttl;
			}
			if ($stale) {
				$queue[] = array($key, $a['doi'], $t);
			}
		}
		usort($queue, function ($x, $y) {
			return $x[2] - $y[2];
		});
		return $queue;
	}

	/**
	 * Record a failed lookup without losing earlier numbers.
	 */
	protected function markError(array &$items, $key, $source) {
		$k = self::keys($source);
		$ttl = (int) $this->settings['ttlDays'] * 86400;
		if (!isset($items[$key][$k['s']])) {
			$items[$key][$k['c']] = 0;
			$items[$key][$k['r']] = 0;
			$items[$key][$k['s']] = 'err';
			$items[$key][$k['t']] = time();
		} else {
			// Keep the old numbers, retry in about an hour.
			$items[$key][$k['t']] = time() - $ttl + 3600;
		}
	}

	/**
	 * Dimensions Metrics API, one DOI per request.
	 * @return array {done, errors, stop}
	 */
	protected function refreshDimensions(array $queue, array &$store, $limit, $deadline) {
		$done = 0;
		$errors = 0;
		$streak = 0;
		$stop = null;
		$apiUrl = rtrim($this->settings['apiUrl'] ?: self::DEFAULT_API, '/') . '/';
		foreach ($queue as $entry) {
			if ($done >= $limit || microtime(true) >= $deadline) {
				break;
			}
			list($k, $doi) = $entry;
			$path = implode('/', array_map('rawurlencode', explode('/', $doi)));
			list($code, $body) = $this->httpGet($apiUrl . $path);
			$done++;
			$decoded = $code === 200 ? json_decode($body, true) : null;
			if ($code === 200 && is_array($decoded)) {
				$store['items'][$k] = array_merge(isset($store['items'][$k]) ? $store['items'][$k] : array(), array(
					'c' => (int) (isset($decoded['times_cited']) ? $decoded['times_cited'] : 0),
					'r' => (int) (isset($decoded['recent_citations']) ? $decoded['recent_citations'] : 0),
					'f' => isset($decoded['field_citation_ratio']) ? (float) $decoded['field_citation_ratio'] : null,
					's' => 'ok',
					't' => time(),
				));
				if (!empty($decoded['license'])) {
					$store['license'] = (string) $decoded['license'];
				}
				$streak = 0;
			} elseif ($code === 404) {
				// Not indexed by Dimensions (yet).
				$store['items'][$k] = array_merge(isset($store['items'][$k]) ? $store['items'][$k] : array(),
					array('c' => 0, 'r' => 0, 'f' => null, 's' => 'nf', 't' => time()));
				$streak = 0;
			} else {
				$errors++;
				$streak++;
				$this->markError($store['items'], $k, 'dimensions');
				if ($code === 429) {
					$store['backoffUntil'] = time() + 900;
					$stop = 'rateLimited';
					break;
				}
				if ($streak >= 5) {
					$stop = 'failing';
					break;
				}
			}
			if ($done % 20 === 0) {
				$this->writeStore($store);
			}
			$delay = (int) $this->settings['requestDelayMs'];
			if ($delay > 0) {
				usleep($delay * 1000);
			}
		}
		return array('done' => $done, 'errors' => $errors, 'stop' => $stop);
	}

	/**
	 * OpenAlex, up to 50 DOIs per request (one OR filter). Without an API key
	 * OpenAlex gives a small free daily budget per IP address, shared with
	 * other sites on the same server; a free key gives the journal its own.
	 * @return array {done, errors, stop}
	 */
	protected function refreshOpenAlex(array $queue, array &$store, $limit, $deadline) {
		$done = 0;
		$errors = 0;
		$streak = 0;
		$stop = null;
		$year = (int) date('Y');
		// DOIs with "," or "|" would break the OR filter; they are rare and looked up one by one.
		$batches = array();
		$batch = array();
		foreach (array_slice($queue, 0, $limit) as $entry) {
			if (strpbrk($entry[1], ',|') !== false) {
				$batches[] = array($entry);
				continue;
			}
			$batch[] = $entry;
			if (count($batch) === 50) {
				$batches[] = $batch;
				$batch = array();
			}
		}
		if ($batch) {
			$batches[] = $batch;
		}

		foreach ($batches as $batch) {
			if (microtime(true) >= $deadline) {
				break;
			}
			$params = array('select' => 'id,doi,cited_by_count,counts_by_year', 'per-page' => 100);
			if (count($batch) === 1 && strpbrk($batch[0][1], ',|') !== false) {
				$url = self::OPENALEX_API . '/works/' . rawurlencode('https://doi.org/' . $batch[0][1]);
				unset($params['per-page']);
			} else {
				$url = self::OPENALEX_API . '/works';
				$params['filter'] = 'doi:' . implode('|', array_map(function ($e) { return $e[1]; }, $batch));
			}
			if ($this->settings['openAlexKey'] !== '') {
				$params['api_key'] = $this->settings['openAlexKey'];
			} elseif ($this->settings['contactEmail'] !== '') {
				$params['mailto'] = $this->settings['contactEmail'];
			}
			list($code, $body, $headers) = $this->httpGet($url . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986));
			$done += count($batch);
			$decoded = $code === 200 ? json_decode($body, true) : null;
			if ($code === 200 && is_array($decoded)) {
				$results = isset($decoded['results']) ? $decoded['results'] : array($decoded);
				$found = array();
				foreach ($results as $w) {
					$doi = isset($w['doi']) ? self::normalizeDoi($w['doi']) : null;
					if ($doi === null) {
						continue;
					}
					$recent = 0;
					foreach (isset($w['counts_by_year']) ? $w['counts_by_year'] : array() as $y) {
						if ((int) $y['year'] >= $year - 1) {
							$recent += (int) $y['cited_by_count'];
						}
					}
					$found[strtolower($doi)] = array(
						'oc' => (int) (isset($w['cited_by_count']) ? $w['cited_by_count'] : 0),
						'or' => $recent,
						'oid' => isset($w['id']) ? preg_replace('~^https?://openalex\.org/~', '', (string) $w['id']) : null,
						'os' => 'ok',
						'ot' => time(),
					);
				}
				foreach ($batch as $entry) {
					$k = $entry[0];
					$values = isset($found[$k]) ? $found[$k] : array('oc' => 0, 'or' => 0, 'oid' => null, 'os' => 'nf', 'ot' => time());
					$store['items'][$k] = array_merge(isset($store['items'][$k]) ? $store['items'][$k] : array(), $values);
				}
				$streak = 0;
			} elseif ($code === 404 && count($batch) === 1) {
				$k = $batch[0][0];
				$store['items'][$k] = array_merge(isset($store['items'][$k]) ? $store['items'][$k] : array(),
					array('oc' => 0, 'or' => 0, 'oid' => null, 'os' => 'nf', 'ot' => time()));
			} else {
				$errors++;
				$streak++;
				foreach ($batch as $entry) {
					$this->markError($store['items'], $entry[0], 'openalex');
				}
				if ($code === 429 || $code === 402) {
					// Daily budget used up: wait until OpenAlex resets it (at most a day).
					$reset = isset($headers['x-ratelimit-reset']) ? (int) $headers['x-ratelimit-reset'] : 3600;
					$store['oaBackoffUntil'] = time() + max(900, min(86400, $reset));
					$stop = 'rateLimited';
					break;
				}
				if ($streak >= 3) {
					$stop = 'failing';
					break;
				}
			}
			$this->writeStore($store);
		}
		return array('done' => $done, 'errors' => $errors, 'stop' => $stop);
	}

	protected function report($status, array $articles, array $items, $done, $errors, array $extra = array()) {
		return array_merge(array(
			'status' => $status,
			'articles' => count($articles),
			'checked' => $this->countChecked($articles, $items),
			'processed' => $done,
			'errors' => $errors,
		), $extra);
	}

	/**
	 * @return int Articles that every enabled source (or only $only) has answered for.
	 */
	protected function countChecked(array $articles, array $items, $only = null) {
		$checked = 0;
		foreach ($articles as $a) {
			$m = isset($items[strtolower($a['doi'])]) ? $items[strtolower($a['doi'])] : null;
			$all = true;
			foreach ($only === null ? $this->sources() : array($only) as $source) {
				$all = $all && self::checkedBy($m, $source);
			}
			$checked += $all ? 1 : 0;
		}
		return $checked;
	}

	/**
	 * @param string $url
	 * @return array [int status code (0 on network error), string body, array lower-case headers]
	 */
	protected function httpGet($url) {
		$ua = 'OJS-CitationImpact/2.2' . ($this->settings['contactEmail'] ? ' (mailto:' . $this->settings['contactEmail'] . ')' : '');
		try {
			$client = Application::get()->getHttpClient();
			$response = $client->request('GET', $url, array(
				'http_errors' => false,
				'timeout' => 20,
				'connect_timeout' => 10,
				'headers' => array('User-Agent' => $ua, 'Accept' => 'application/json'),
			));
			$headers = array();
			foreach ($response->getHeaders() as $name => $values) {
				$headers[strtolower($name)] = implode(', ', $values);
			}
			return array((int) $response->getStatusCode(), (string) $response->getBody(), $headers);
		} catch (\Throwable $e) {
			error_log('Citation Impact: ' . $e->getMessage());
			return array(0, '', array());
		}
	}

	//
	// Statistics
	//

	/**
	 * Everything the public page needs.
	 * @param string $locale
	 * @return array
	 */
	public function build($locale, $allRows = false) {
		$articles = $this->getArticles();
		$store = $this->readStore();
		$items = $store['items'];

		$rows = array();
		$cites = array();
		$years = array();
		$total = 0;
		$recent = 0;
		$checked = 0;
		list($winFrom, $winTo) = self::windowYears($this->settings['windowIncludeCurrent']);
		$winArticles = 0;
		$winCites = 0;
		$winCited = 0;
		$winList = array();
		// OpenAlex figures, kept apart from the main (Dimensions) count in "separate" mode.
		$oa = array('checked' => 0, 'total' => 0, 'recent' => 0, 'cited' => 0, 'list' => array(), 'winCites' => 0, 'winCited' => 0, 'winList' => array());
		foreach ($articles as $a) {
			$m = $this->merge(isset($items[strtolower($a['doi'])]) ? $items[strtolower($a['doi'])] : null);
			$ok = $m['ok'];
			$c = $ok ? (int) $m['c'] : null;
			if ($ok) {
				$checked++;
				$total += $c;
				$recent += (int) $m['r'];
				$cites[] = $c;
			}
			$year = $a['date'] !== '' ? (int) substr($a['date'], 0, 4) : 0;
			if ($m['okOpenAlex']) {
				$co = (int) $m['co'];
				$oa['checked']++;
				$oa['total'] += $co;
				$oa['recent'] += (int) $m['ro'];
				$oa['cited'] += $co > 0 ? 1 : 0;
				$oa['list'][] = $co;
				if ($year >= $winFrom && $year <= $winTo) {
					$oa['winCites'] += $co;
					$oa['winCited'] += $co > 0 ? 1 : 0;
					$oa['winList'][] = $co;
				}
			}
			if ($year >= $winFrom && $year <= $winTo) {
				$winArticles++;
				if ($ok) {
					$winCites += $c;
					$winCited += $c > 0 ? 1 : 0;
					$winList[] = $c;
				}
			}
			if ($year) {
				if (!isset($years[$year])) {
					$years[$year] = array('year' => $year, 'articles' => 0, 'citations' => 0);
				}
				$years[$year]['articles']++;
				$years[$year]['citations'] += (int) $c;
			}
			$rows[] = array_merge($a, array(
				'year' => $year ?: null,
				'cites' => $c,
				'recent' => $ok ? (int) $m['r'] : null,
				'fcr' => $ok ? $m['f'] : null,
				'citesDimensions' => $m['cd'],
				'citesOpenAlex' => $m['co'],
				'recentOpenAlex' => $m['ro'],
				'openAlexId' => $m['oid'],
				'inWindow' => $year >= $winFrom && $year <= $winTo,
			));
		}

		$h = self::hIndex($cites);
		$winH = self::hIndex($winList);
		$winI10 = count(array_filter($winList, function ($c) { return $c >= 10; }));
		// How many checked articles fall in each citation band (0, 1-4, 5-9, 10-24, 25+).
		$bands = array(array(0, 0), array(1, 4), array(5, 9), array(10, 24), array(25, null));
		$distribution = array();
		foreach ($bands as $b) {
			$distribution[] = array('min' => $b[0], 'max' => $b[1], 'articles' => 0);
		}
		foreach ($cites as $c) {
			foreach ($bands as $i => $b) {
				if ($c >= $b[0] && ($b[1] === null || $c <= $b[1])) {
					$distribution[$i]['articles']++;
					break;
				}
			}
		}
		$i10 = 0;
		$cited = 0;
		foreach ($cites as $c) {
			$i10 += $c >= 10 ? 1 : 0;
			$cited += $c > 0 ? 1 : 0;
		}
		if ($years) {
			for ($y = min(array_keys($years)); $y <= max(array_keys($years)); $y++) {
				if (!isset($years[$y])) {
					$years[$y] = array('year' => $y, 'articles' => 0, 'citations' => 0);
				}
			}
			ksort($years);
			foreach ($years as $y => $v) {
				$years[$y]['inWindow'] = $y >= $winFrom && $y <= $winTo;
			}
		}

		if (!$allRows && $this->settings['listMode'] !== 'all') {
			// In "separate" mode an article cited only in OpenAlex is still listed (with Dimensions 0).
			$rows = array_values(array_filter($rows, function ($r) {
				return $r['cites'] > 0 || $r['citesOpenAlex'] > 0;
			}));
		}
		usort($rows, function ($a, $b) {
			$ca = $a['cites'] === null ? -1 : $a['cites'];
			$cb = $b['cites'] === null ? -1 : $b['cites'];
			if ($ca !== $cb) {
				return $cb - $ca;
			}
			$oa = $a['citesOpenAlex'] === null ? -1 : $a['citesOpenAlex'];
			$ob = $b['citesOpenAlex'] === null ? -1 : $b['citesOpenAlex'];
			if ($oa !== $ob) {
				return $ob - $oa;
			}
			return strcmp($b['date'], $a['date']);
		});

		// Titles and authors only for the rows shown.
		$fallback = array();
		foreach ($rows as $r) {
			$fallback[$r['publicationId']] = $r['locale'];
		}
		$display = $this->getDisplayData(array_keys($fallback), $locale, $fallback);
		foreach ($rows as $i => $r) {
			$d = isset($display[$r['publicationId']]) ? $display[$r['publicationId']] : array('title' => '', 'authors' => array());
			$rows[$i]['title'] = $d['title'];
			$rows[$i]['authors'] = $d['authors'];
		}

		return array(
			'stats' => array(
				'articles' => count($articles),
				'checked' => $checked,
				'citedArticles' => $cited,
				'totalCitations' => $total,
				'recentCitations' => $recent,
				'hIndex' => $h,
				'i10Index' => $i10,
				'windowFrom' => $winFrom,
				'windowTo' => $winTo,
				'windowCitations' => $winCites,
				'windowArticles' => $winArticles,
				'windowCitedArticles' => $winCited,
				'windowHIndex' => $winH,
				'windowI10Index' => $winI10,
				'maxCitations' => $cites ? max($cites) : 0,
			),
			'distribution' => $distribution,
			'byYear' => array_values($years),
			'rows' => $rows,
			'updatedAt' => isset($store['updatedAt']) ? (int) $store['updatedAt'] : null,
			'license' => isset($store['license']) ? $store['license'] : null,
			'withoutDoi' => $this->countWithoutDoi(),
			'sources' => $this->sources(),
			'separate' => $this->isSeparate(),
			'openAlex' => $this->isSeparate() && $oa['checked'] ? array(
				'checked' => $oa['checked'],
				'totalCitations' => $oa['total'],
				'recentCitations' => $oa['recent'],
				'citedArticles' => $oa['cited'],
				'hIndex' => self::hIndex($oa['list']),
				'i10Index' => count(array_filter($oa['list'], function ($c) { return $c >= 10; })),
				'windowCitations' => $oa['winCites'],
				'windowCitedArticles' => $oa['winCited'],
				'windowHIndex' => self::hIndex($oa['winList']),
			) : null,
			'pending' => $this->countChecked($articles, $items) < count($articles),
		);
	}

	/**
	 * Light status for the settings screen.
	 * @return array
	 */
	public function status() {
		$articles = $this->getArticles();
		$store = $this->readStore();
		return array(
			'articles' => count($articles),
			'checked' => $this->countChecked($articles, $store['items']),
			'oaWaitUntil' => !empty($store['oaBackoffUntil']) && $store['oaBackoffUntil'] > time() ? (int) $store['oaBackoffUntil'] : null,
			'withoutDoi' => $this->countWithoutDoi(),
			'updatedAt' => isset($store['updatedAt']) ? (int) $store['updatedAt'] : null,
		);
	}
}
