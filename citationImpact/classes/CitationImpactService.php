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
	 * Check stale DOIs against the Dimensions Metrics API.
	 * @param int $limit Max DOIs to request in this call.
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
			$items = $store['items'];

			if (!$force && !empty($store['backoffUntil']) && $store['backoffUntil'] > time()) {
				return $this->report('backoff', $articles, $items, 0, 0, array('until' => (int) $store['backoffUntil']));
			}

			// Forget DOIs that are no longer published.
			$keep = array();
			foreach ($articles as $a) {
				$keep[strtolower($a['doi'])] = true;
			}
			$items = array_intersect_key($items, $keep);

			$now = time();
			$ttl = (int) $this->settings['ttlDays'] * 86400;
			$queue = array();
			foreach ($articles as $a) {
				$k = strtolower($a['doi']);
				$m = isset($items[$k]) ? $items[$k] : null;
				if ($m === null) {
					$stale = true;
				} elseif ($force) {
					$stale = true;
				} elseif (isset($m['s']) && $m['s'] === 'err') {
					$stale = $now - (int) $m['t'] > 3600;
				} else {
					$stale = $now - (int) $m['t'] > $ttl;
				}
				if ($stale) {
					$queue[] = array($k, $a['doi'], $m === null ? 0 : (int) $m['t']);
				}
			}
			// Never-checked first, then the oldest.
			usort($queue, function ($x, $y) {
				return $x[2] - $y[2];
			});

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
					$items[$k] = array(
						'c' => (int) (isset($decoded['times_cited']) ? $decoded['times_cited'] : 0),
						'r' => (int) (isset($decoded['recent_citations']) ? $decoded['recent_citations'] : 0),
						'f' => isset($decoded['field_citation_ratio']) ? (float) $decoded['field_citation_ratio'] : null,
						's' => 'ok',
						't' => time(),
					);
					if (!empty($decoded['license'])) {
						$store['license'] = (string) $decoded['license'];
					}
					$streak = 0;
				} elseif ($code === 404) {
					// Not indexed by Dimensions (yet).
					$items[$k] = array('c' => 0, 'r' => 0, 'f' => null, 's' => 'nf', 't' => time());
					$streak = 0;
				} else {
					$errors++;
					$streak++;
					if (!isset($items[$k])) {
						$items[$k] = array('c' => 0, 'r' => 0, 'f' => null, 's' => 'err', 't' => time());
					} else {
						// Keep the old numbers, retry in about an hour.
						$items[$k]['t'] = time() - $ttl + 3600;
					}
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
					$store['items'] = $items;
					$this->writeStore($store);
				}
				$delay = (int) $this->settings['requestDelayMs'];
				if ($delay > 0) {
					usleep($delay * 1000);
				}
			}

			$store['items'] = $items;
			if ($done > 0) {
				$store['updatedAt'] = time();
			}
			$this->writeStore($store);

			$remaining = max(0, count($queue) - $done);
			$status = $stop !== null ? 'error' : ($remaining > 0 ? 'partial' : 'ok');
			return $this->report($status, $articles, $items, $done, $errors, array('remaining' => $remaining, 'reason' => $stop));
		} finally {
			flock($lock, LOCK_UN);
			fclose($lock);
		}
	}

	protected function report($status, array $articles, array $items, $done, $errors, array $extra = array()) {
		$checked = 0;
		foreach ($items as $it) {
			if (isset($it['s']) && ($it['s'] === 'ok' || $it['s'] === 'nf')) {
				$checked++;
			}
		}
		return array_merge(array(
			'status' => $status,
			'articles' => count($articles),
			'checked' => $checked,
			'processed' => $done,
			'errors' => $errors,
		), $extra);
	}

	/**
	 * @param string $url
	 * @return array [int status code (0 on network error), string body]
	 */
	protected function httpGet($url) {
		$ua = 'OJS-CitationImpact/2.0' . ($this->settings['contactEmail'] ? ' (mailto:' . $this->settings['contactEmail'] . ')' : '');
		try {
			$client = Application::get()->getHttpClient();
			$response = $client->request('GET', $url, array(
				'http_errors' => false,
				'timeout' => 20,
				'connect_timeout' => 10,
				'headers' => array('User-Agent' => $ua, 'Accept' => 'application/json'),
			));
			return array((int) $response->getStatusCode(), (string) $response->getBody());
		} catch (\Throwable $e) {
			error_log('Citation Impact: ' . $e->getMessage());
			return array(0, '');
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
	public function build($locale) {
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
		foreach ($articles as $a) {
			$m = isset($items[strtolower($a['doi'])]) ? $items[strtolower($a['doi'])] : null;
			$ok = $m && isset($m['s']) && ($m['s'] === 'ok' || $m['s'] === 'nf');
			$c = $ok ? (int) $m['c'] : null;
			if ($ok) {
				$checked++;
				$total += $c;
				$recent += (int) $m['r'];
				$cites[] = $c;
			}
			$year = $a['date'] !== '' ? (int) substr($a['date'], 0, 4) : 0;
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

		if ($this->settings['listMode'] !== 'all') {
			$rows = array_values(array_filter($rows, function ($r) {
				return $r['cites'] > 0;
			}));
		}
		usort($rows, function ($a, $b) {
			$ca = $a['cites'] === null ? -1 : $a['cites'];
			$cb = $b['cites'] === null ? -1 : $b['cites'];
			if ($ca !== $cb) {
				return $cb - $ca;
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
		);
	}

	/**
	 * Light status for the settings screen.
	 * @return array
	 */
	public function status() {
		$articles = $this->getArticles();
		$store = $this->readStore();
		$checked = 0;
		$keep = array();
		foreach ($articles as $a) {
			$keep[strtolower($a['doi'])] = true;
		}
		foreach (array_intersect_key($store['items'], $keep) as $it) {
			if (isset($it['s']) && ($it['s'] === 'ok' || $it['s'] === 'nf')) {
				$checked++;
			}
		}
		return array(
			'articles' => count($articles),
			'checked' => $checked,
			'withoutDoi' => $this->countWithoutDoi(),
			'updatedAt' => isset($store['updatedAt']) ? (int) $store['updatedAt'] : null,
		);
	}
}
