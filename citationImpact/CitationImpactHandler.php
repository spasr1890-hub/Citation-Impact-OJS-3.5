<?php

/**
 * @file plugins/generic/citationImpact/CitationImpactHandler.php
 *
 * Distributed under the GNU GPL v3.
 *
 * @class CitationImpactHandler
 * @brief Public "Citation Impact" page and its JSON data.
 */

namespace APP\plugins\generic\citationImpact;

use APP\core\Application;
use APP\handler\Handler;
use APP\plugins\generic\citationImpact\classes\CitationImpactService;
use APP\template\TemplateManager;
use PKP\facades\Locale;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class CitationImpactHandler extends Handler {

	const VERSION = '2.0.0';

	/**
	 * Ordinal blue ramp (light → dark), validated for a light surface:
	 * the lightest step still clears 2:1, one hue, monotone lightness.
	 */
	const RAMP = array('#86b6ef', '#6da7ec', '#5598e7', '#3987e5', '#2a78d6', '#256abf', '#1c5cab', '#184f95', '#104281');

	/** Neutral for "no citations" (not part of the magnitude ramp). */
	const ZERO = '#c3c2b7';

	/** @var CitationImpactPlugin */
	protected $plugin;

	public function __construct(CitationImpactPlugin $plugin) {
		parent::__construct();
		$this->plugin = $plugin;
	}

	/**
	 * The public page, rendered inside the journal theme.
	 */
	public function index($args, $request) {
		$context = $request->getContext();
		if (!$context) {
			throw new NotFoundHttpException();
		}
		$this->setupTemplate($request);
		$plugin = $this->plugin;

		$locale = Locale::getLocale();
		$service = new CitationImpactService($context->getId());
		try {
			$data = $service->build($locale);
		} catch (Throwable $e) {
			error_log('Citation Impact: ' . $e->getMessage());
			list($wf, $wt) = CitationImpactService::windowYears(true);
			$data = array(
				'stats' => array('articles' => 0, 'checked' => 0, 'citedArticles' => 0, 'totalCitations' => 0, 'recentCitations' => 0,
					'hIndex' => 0, 'i10Index' => 0, 'windowFrom' => $wf, 'windowTo' => $wt, 'windowCitations' => 0, 'windowArticles' => 0,
					'windowCitedArticles' => 0, 'windowHIndex' => 0, 'windowI10Index' => 0, 'maxCitations' => 0),
				'distribution' => array(), 'byYear' => array(), 'rows' => array(), 'updatedAt' => null, 'license' => null, 'withoutDoi' => 0,
			);
		}
		$dispatcher = $request->getDispatcher();
		$etAl = __('plugins.generic.citationImpact.etAl');

		$rows = array();
		foreach ($data['rows'] as $r) {
			$authors = $r['authors'];
			$authorText = count($authors) > 3 ? implode(', ', array_slice($authors, 0, 3)) . ' ' . $etAl : implode(', ', $authors);
			$rows[] = array(
				'd' => $r['doi'],
				't' => $r['title'] !== '' ? $r['title'] : $r['doi'],
				'a' => $authorText,
				'y' => $r['year'],
				'dt' => $r['date'],
				'u' => $dispatcher->url($request, Application::ROUTE_PAGE, null, 'article', 'view', array((string) ($r['urlPath'] ? $r['urlPath'] : $r['submissionId']))),
				'c' => $r['cites'],
				'r' => $r['recent'],
				'f' => $r['fcr'],
			);
		}

		$numberLocale = strpos($locale, 'id') === 0 ? 'id' : 'en';
		$fmt = function ($n, $dec = 0) use ($numberLocale) {
			return $numberLocale === 'id' ? number_format((float) $n, $dec, ',', '.') : number_format((float) $n, $dec, '.', ',');
		};
		$s = $data['stats'];
		$perPage = 25;
		$citedPct = $s['checked'] ? round($s['citedArticles'] / $s['checked'] * 100) : 0;
		$winCitedPct = $s['windowArticles'] ? round($s['windowCitedArticles'] / $s['windowArticles'] * 100) : 0;

		// Top three (only articles with at least one citation).
		$top = array();
		foreach (array_slice($rows, 0, 3) as $i => $r) {
			if (!$r['c']) {
				break;
			}
			$top[] = array_merge($r, array(
				'rank' => $i + 1,
				'cF' => $fmt($r['c']),
				'fF' => $r['f'] === null ? null : $fmt($r['f'], 1),
				'fHigh' => $r['f'] !== null && $r['f'] >= 1,
			));
		}

		$templateMgr = TemplateManager::getManager($request);
		$base = $request->getBaseUrl() . '/' . $plugin->getPluginPath();
		$templateMgr->addStyleSheet('citationImpact', $base . '/css/impact.css?v=' . self::VERSION, array('contexts' => 'frontend'));
		$templateMgr->addJavaScript('citationImpact', $base . '/js/impact.js?v=' . self::VERSION, array('contexts' => 'frontend'));
		$templateMgr->assign(array(
			'ciStats' => array(
				'articles' => $fmt($s['articles']),
				'checked' => $fmt($s['checked']),
				'citedArticles' => $fmt($s['citedArticles']),
				'citedPct' => $citedPct,
				'mean' => $fmt($s['checked'] ? $s['totalCitations'] / $s['checked'] : 0, 1),
				'max' => $fmt($s['maxCitations']),
				'totalCitations' => $fmt($s['totalCitations']),
				'recentCitations' => $fmt($s['recentCitations']),
				'hIndex' => (int) $s['hIndex'],
				'i10Index' => (int) $s['i10Index'],
				'windowCitations' => $fmt($s['windowCitations']),
				'windowArticles' => $fmt($s['windowArticles']),
				'windowCitedArticles' => $fmt($s['windowCitedArticles']),
				'windowCitedPct' => $winCitedPct,
				'windowHIndex' => (int) $s['windowHIndex'],
				'windowI10Index' => (int) $s['windowI10Index'],
				'windowFrom' => (int) $s['windowFrom'],
				'windowTo' => (int) $s['windowTo'],
			),
			'ciRing' => self::ring($citedPct),
			'ciHasData' => $s['checked'] > 0,
			'ciPending' => $s['checked'] < $s['articles'],
			'ciUpdatedAt' => $data['updatedAt'],
			'ciLicense' => $data['license'],
			'ciWithoutDoi' => $data['withoutDoi'] ? $fmt($data['withoutDoi']) : null,
			'ciListMode' => CitationImpactService::getSettings($context->getId())['listMode'],
			'ciByYear' => array_reverse(array_map(function ($y) use ($fmt) {
				return array('year' => $y['year'], 'articles' => $fmt($y['articles']), 'citations' => $fmt($y['citations']));
			}, $data['byYear'])),
			'ciChartWide' => self::yearChart($data['byYear'], $fmt, 720, 'wide'),
			'ciChartNarrow' => self::yearChart($data['byYear'], $fmt, 360, 'narrow'),
			'ciRampFrom' => self::RAMP[0],
			'ciRampTo' => self::RAMP[count(self::RAMP) - 1],
			'ciYearFrom' => $data['byYear'] ? $data['byYear'][0]['year'] : null,
			'ciYearTo' => $data['byYear'] ? $data['byYear'][count($data['byYear']) - 1]['year'] : null,
			'ciDistribution' => self::distribution($data['distribution'], $s['checked'], $fmt),
			'ciTop' => $top,
			'ciRowCount' => count($rows),
			'ciShown' => __('plugins.generic.citationImpact.shown', array('shown' => $fmt(min($perPage, count($rows))), 'total' => $fmt(count($rows)))),
			'ciJson' => json_encode(array(
				'rows' => $rows,
				'perPage' => $perPage,
				'max' => (int) $s['maxCitations'],
				'locale' => $numberLocale,
				'window' => array((int) $s['windowFrom'], (int) $s['windowTo']),
				'i18n' => array(
					'shown' => __('plugins.generic.citationImpact.shown'),
					'details' => __('plugins.generic.citationImpact.dimensionsDetails'),
					'fcrTimes' => __('plugins.generic.citationImpact.fcrTimes'),
				),
			), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
		));
		// Preformat the first page of rows (no-JS fallback).
		$first = array();
		$max = max(1, (int) $s['maxCitations']);
		foreach (array_slice($rows, 0, $perPage) as $i => $r) {
			$r['rank'] = $i + 1;
			$r['cF'] = $r['c'] === null ? null : $fmt($r['c']);
			$r['cW'] = $r['c'] === null ? 0 : round(max(2, $r['c'] / $max * 100), 1);
			$r['rF'] = $r['r'] === null ? null : $fmt($r['r']);
			$r['fF'] = $r['f'] === null ? null : $fmt($r['f'], 1);
			$r['fHigh'] = $r['f'] !== null && $r['f'] >= 1;
			$first[] = $r;
		}
		$templateMgr->assign('ciRows', $first);

		header('X-LiteSpeed-Cache-Control: no-cache');
		return $templateMgr->display($plugin->getTemplateResource('impact.tpl'));
	}

	/**
	 * Machine-readable data: /{journal}/{pagePath}/json
	 */
	public function json($args, $request) {
		$context = $request->getContext();
		if (!$context) {
			throw new NotFoundHttpException();
		}
		$service = new CitationImpactService($context->getId());
		$data = $service->build(Locale::getLocale());
		$dispatcher = $request->getDispatcher();
		$articles = array();
		foreach ($data['rows'] as $r) {
			$articles[] = array(
				'doi' => $r['doi'],
				'title' => $r['title'],
				'authors' => $r['authors'],
				'date' => $r['date'],
				'url' => $dispatcher->url($request, Application::ROUTE_PAGE, null, 'article', 'view', array((string) ($r['urlPath'] ? $r['urlPath'] : $r['submissionId']))),
				'times_cited' => $r['cites'],
				'recent_citations' => $r['recent'],
				'field_citation_ratio' => $r['fcr'],
			);
		}
		header('Content-Type: application/json; charset=utf-8');
		header('Access-Control-Allow-Origin: *');
		header('Cache-Control: no-cache');
		echo json_encode(array(
			'journal' => $context->getLocalizedName(),
			'source' => 'Dimensions (https://www.dimensions.ai)',
			'license' => $data['license'],
			'updated_at' => $data['updatedAt'] ? date('c', $data['updatedAt']) : null,
			'stats' => $data['stats'],
			'by_year' => $data['byYear'],
			'distribution' => $data['distribution'],
			'articles' => $articles,
		), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE);
		exit;
	}

	//
	// Drawing helpers
	//

	protected static function esc($s) {
		return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
	}

	/**
	 * Pick a step of the ramp for position $i of $n (0 = lightest).
	 */
	protected static function rampAt($i, $n) {
		$steps = count(self::RAMP);
		if ($n <= 1) {
			return self::RAMP[$steps - 1];
		}
		return self::RAMP[(int) round($i / ($n - 1) * ($steps - 1))];
	}

	/**
	 * @param int $v
	 * @return int Axis maximum with four whole-number steps.
	 */
	protected static function niceMax($v) {
		$raw = max(1, (int) ceil($v / 4));
		$exp = pow(10, (int) floor(log10($raw)));
		foreach (array(1, 2, 5, 10) as $m) {
			if ($m * $exp >= $raw) {
				return (int) ($m * $exp * 4);
			}
		}
		return $raw * 4;
	}

	/**
	 * Donut showing the share of checked articles that have been cited.
	 * @param int $pct 0–100
	 * @return string SVG
	 */
	protected static function ring($pct) {
		$r = 26;
		$c = 2 * M_PI * $r;
		$on = round($c * max(0, min(100, $pct)) / 100, 2);
		return '<svg class="ci-ring" viewBox="0 0 64 64" aria-hidden="true">'
			. '<circle cx="32" cy="32" r="' . $r . '" fill="none" stroke="#cde2fb" stroke-width="8"/>'
			. '<circle cx="32" cy="32" r="' . $r . '" fill="none" stroke="#2a78d6" stroke-width="8" stroke-linecap="round"'
			. ' stroke-dasharray="' . $on . ' ' . round($c, 2) . '" transform="rotate(-90 32 32)"/>'
			. '<text x="32" y="36.5" text-anchor="middle" class="ci-ring-t">' . (int) $pct . '%</text></svg>';
	}

	/**
	 * Bars of citations by publication year. Bars run light → dark from the
	 * oldest to the newest year; the "last 3 years" window sits on a tinted band
	 * and its bars carry their values.
	 * @return string SVG
	 */
	protected static function yearChart(array $years, $fmt, $W, $variant) {
		if (!$years) {
			return '';
		}
		$narrow = $W < 500;
		$H = $narrow ? 230 : 270;
		$L = 44; $R = 8; $T = 34; $B = 28;
		$pw = $W - $L - $R;
		$ph = $H - $T - $B;
		$maxCites = 0;
		$maxIdx = 0;
		foreach (array_values($years) as $i => $y) {
			if ($y['citations'] > $maxCites) {
				$maxCites = $y['citations'];
				$maxIdx = $i;
			}
		}
		$max = self::niceMax(max(1, $maxCites));
		$n = count($years);
		$slot = $pw / $n;
		$bw = max(4, min(30, $slot * 0.62));
		$every = (int) ceil($n / ($narrow ? 6 : 14));
		$labelAll = !$narrow && $n <= 14;
		$wordCites = __('plugins.generic.citationImpact.chart.citations');
		$wordArticles = __('plugins.generic.citationImpact.chart.articles');
		$esc = array(__CLASS__, 'esc');

		$svg = '<svg class="ci-chart ci-chart-' . $variant . '" viewBox="0 0 ' . $W . ' ' . $H . '" role="img" aria-label="' . $esc(__('plugins.generic.citationImpact.byYear')) . '">';

		// "Last 3 years" band behind the bars.
		$inWin = array();
		foreach (array_values($years) as $i => $y) {
			if (!empty($y['inWindow'])) {
				$inWin[] = $i;
			}
		}
		if ($inWin && count($inWin) < $n) {
			$x0 = round($L + $slot * min($inWin), 1);
			$x1 = round($L + $slot * (max($inWin) + 1), 1);
			$svg .= '<rect class="ci-band" x="' . $x0 . '" y="4" width="' . round($x1 - $x0, 1) . '" height="' . ($T + $ph - 4) . '" rx="6"/>';
			if (!$narrow) {
				$svg .= '<text class="ci-band-t" x="' . round(($x0 + $x1) / 2, 1) . '" y="17" text-anchor="middle">' . $esc(__('plugins.generic.citationImpact.chart.window')) . '</text>';
			}
		}

		for ($i = 0; $i <= 4; $i++) {
			$v = $max * $i / 4;
			$y = round($T + $ph - $ph * $i / 4, 1);
			$svg .= '<line class="ci-grid' . ($i === 0 ? ' ci-base' : '') . '" x1="' . $L . '" x2="' . ($W - $R) . '" y1="' . $y . '" y2="' . $y . '"/>';
			$svg .= '<text class="ci-tick" x="' . ($L - 8) . '" y="' . ($y + 4) . '" text-anchor="end">' . $esc($fmt($v)) . '</text>';
		}
		foreach (array_values($years) as $i => $yr) {
			$cx = $L + $slot * ($i + 0.5);
			$h = $yr['citations'] > 0 ? max(2, $ph * $yr['citations'] / $max) : 0;
			$x = round($cx - $bw / 2, 1);
			$y = round($T + $ph - $h, 1);
			$color = self::rampAt($i, $n);
			$tip = $yr['year'] . ' · ' . $fmt($yr['citations']) . ' ' . $wordCites . ' · ' . $fmt($yr['articles']) . ' ' . $wordArticles;
			$svg .= '<g class="ci-bar" tabindex="0" data-tip="' . $esc($tip) . '"><title>' . $esc($tip) . '</title>';
			$svg .= '<rect class="ci-hit" x="' . round($cx - $slot / 2, 1) . '" y="' . $T . '" width="' . round($slot, 1) . '" height="' . $ph . '"/>';
			if ($h > 0) {
				$r = min(4, $bw / 2, $h);
				$bwr = round($bw, 1);
				$hr = round($h, 1);
				$svg .= '<path class="ci-mark" style="fill:' . $color . '" d="M' . $x . ',' . ($y + $hr) . 'V' . ($y + $r) . 'Q' . $x . ',' . $y . ' ' . ($x + $r) . ',' . $y
					. 'H' . ($x + $bwr - $r) . 'Q' . ($x + $bwr) . ',' . $y . ' ' . ($x + $bwr) . ',' . ($y + $r) . 'V' . ($y + $hr) . 'Z"/>';
			}
			if ($yr['citations'] > 0 && ($labelAll || !empty($yr['inWindow']) || $i === $maxIdx)) {
				$svg .= '<text class="ci-val" x="' . round($cx, 1) . '" y="' . round($y - 6, 1) . '" text-anchor="middle">' . $esc($fmt($yr['citations'])) . '</text>';
			}
			$svg .= '</g>';
			// Anchor ticks to the newest year so it is always labelled.
			if (($n - 1 - $i) % $every === 0) {
				$svg .= '<text class="ci-tick' . (!empty($yr['inWindow']) ? ' ci-tick-w' : '') . '" x="' . round($cx, 1) . '" y="' . ($H - 8) . '" text-anchor="middle">' . (int) $yr['year'] . '</text>';
			}
		}
		return $svg . '</svg>';
	}

	/**
	 * Articles per citation band (0, 1–4, 5–9, 10–24, 25+), as HTML bars.
	 * @return array rows for the template
	 */
	protected static function distribution(array $bands, $checked, $fmt) {
		$out = array();
		$maxN = 0;
		foreach ($bands as $b) {
			$maxN = max($maxN, $b['articles']);
		}
		$colors = array(self::ZERO, self::RAMP[0], self::RAMP[3], self::RAMP[6], self::RAMP[8]);
		foreach ($bands as $i => $b) {
			if ($b['max'] === null) {
				$label = $b['min'] . '+';
			} elseif ($b['min'] === $b['max']) {
				$label = (string) $b['min'];
			} else {
				$label = $b['min'] . '–' . $b['max'];
			}
			$out[] = array(
				'label' => $label,
				'articles' => $fmt($b['articles']),
				'pct' => $checked ? round($b['articles'] / $checked * 100) : 0,
				'width' => $maxN ? round(max($b['articles'] ? 1.5 : 0, $b['articles'] / $maxN * 100), 1) : 0,
				'color' => isset($colors[$i]) ? $colors[$i] : self::RAMP[8],
			);
		}
		return $out;
	}
}
