{**
 * plugins/generic/citationImpact/templates/impact.tpl
 *
 * Distributed under the GNU GPL v3.
 *
 * Public Citation Impact page, rendered inside the journal theme.
 *}
{capture assign="ciTitle"}{translate key="plugins.generic.citationImpact.pageTitle"}{/capture}
{include file="frontend/components/header.tpl" pageTitleTranslated=$ciTitle}

<div class="page page_citation_impact">
	{include file="frontend/components/breadcrumbs.tpl" currentTitle=$ciTitle}

	<div class="cimpact{if !$ciHasDim} cimpact-nodim{/if}" style="{$ciPaletteStyle|escape}" data-ci-accent="{$ciAccent|escape}" data-ci-fallback="{$ciAccentFallback|escape}" data-ci-source="{$ciAccentSource|escape}">
		<header class="cimpact-head">
			<h1 class="cimpact-title">{$ciTitle|escape}</h1>
			<p class="cimpact-lead">{translate key="plugins.generic.citationImpact.lead" journal=$currentContext->getLocalizedName()|escape sources=$ciSources}</p>
			<p class="cimpact-meta">
				<span class="cimpact-chip">{translate key="plugins.generic.citationImpact.sourceChip" sources=$ciSources}</span>
				{if $ciSeparate}<span class="cimpact-chip cimpact-chip-muted">{translate key="plugins.generic.citationImpact.oa.chip"}</span>{/if}
				{if $ciUpdatedAt}<span>{translate key="plugins.generic.citationImpact.updated" date=$ciUpdatedAt|date_format:$dateFormatLong}</span>{/if}
				<span>{translate key="plugins.generic.citationImpact.coverage" checked=$ciStats.checked total=$ciStats.articles}</span>
			</p>
		</header>

		{if !$ciHasData}
			<div class="cimpact-card cimpact-empty">{translate key="plugins.generic.citationImpact.empty"}</div>
		{else}
			<section class="cimpact-overview" aria-label="{translate key="plugins.generic.citationImpact.summary"}">
				{* Hero: citations in the last 3 years (ARJUNA "Dampak Ilmiah"), the figure assessors look at. *}
				<div class="cimpact-card cimpact-hero">
					<div class="cimpact-hero-k">{translate key="plugins.generic.citationImpact.hero.window"}<span class="cimpact-win">{$ciStats.windowFrom}–{$ciStats.windowTo}</span>{if $ciSeparate} <span class="cimpact-src">Dimensions</span>{/if}</div>
					<div class="cimpact-hero-v">{$ciStats.windowCitations}</div>
					<div class="cimpact-hero-sub">{translate key="plugins.generic.citationImpact.hero.windowSub" from=$ciStats.windowFrom to=$ciStats.windowTo articles=$ciStats.windowArticles}</div>
					<div class="cimpact-hero-all">{translate key="plugins.generic.citationImpact.hero.allTime" number=$ciStats.totalCitations articles=$ciStats.checked}</div>
					<dl class="cimpact-mini">
						<div><dt>{translate key="plugins.generic.citationImpact.hero.windowH"}</dt><dd>{$ciStats.windowHIndex}</dd></div>
						<div><dt>{translate key="plugins.generic.citationImpact.hero.windowI10"}</dt><dd>{$ciStats.windowI10Index}</dd></div>
					</dl>
					<div class="cimpact-ringrow">
						{$ciRingWindow}
						<div>
							<div class="cimpact-ring-k">{translate key="plugins.generic.citationImpact.stat.citedLine" cited=$ciStats.windowCitedArticles total=$ciStats.windowArticles}</div>
							<div class="cimpact-ring-s">{translate key="plugins.generic.citationImpact.hero.windowCitedNote" from=$ciStats.windowFrom to=$ciStats.windowTo}</div>
						</div>
					</div>
				</div>

				<div class="cimpact-card cimpact-index">
					<h2>{translate key="plugins.generic.citationImpact.index.title"}{if $ciSeparate} <span class="cimpact-src">Dimensions</span>{/if}</h2>
					<table class="cimpact-itable">
						<thead><tr>
							<th scope="col"><span class="cimpact-sr">{translate key="plugins.generic.citationImpact.index.metric"}</span></th>
							<th scope="col">{translate key="plugins.generic.citationImpact.index.all"}</th>
							<th scope="col" class="cimpact-w">{$ciStats.windowFrom}–{$ciStats.windowTo}<span>{translate key="plugins.generic.citationImpact.index.last3"}</span></th>
						</tr></thead>
						<tbody>
							<tr><th scope="row">{translate key="plugins.generic.citationImpact.index.citations"}</th><td>{$ciStats.totalCitations}</td><td class="cimpact-w"><strong>{$ciStats.windowCitations}</strong></td></tr>
							<tr><th scope="row">{translate key="plugins.generic.citationImpact.index.articles"}</th><td>{$ciStats.checked}</td><td class="cimpact-w">{$ciStats.windowArticles}</td></tr>
							<tr><th scope="row">{translate key="plugins.generic.citationImpact.index.cited"}</th><td>{$ciStats.citedArticles} <small>({$ciStats.citedPct}%)</small></td><td class="cimpact-w">{$ciStats.windowCitedArticles} <small>({$ciStats.windowCitedPct}%)</small></td></tr>
							<tr><th scope="row">h-index</th><td>{$ciStats.hIndex}</td><td class="cimpact-w">{$ciStats.windowHIndex}</td></tr>
							<tr><th scope="row">i10-index</th><td>{$ciStats.i10Index}</td><td class="cimpact-w">{$ciStats.windowI10Index}</td></tr>
						</tbody>
					</table>
					<p class="cimpact-note">{translate key="plugins.generic.citationImpact.index.note" from=$ciStats.windowFrom to=$ciStats.windowTo}</p>
				</div>
			</section>

			{if $ciOa}
			{* OpenAlex for comparison only: never part of the figures above. *}
			<section class="cimpact-card cimpact-oa" aria-labelledby="cimpact-oa-h">
				<div class="cimpact-oa-head">
					<h2 id="cimpact-oa-h">{translate key="plugins.generic.citationImpact.oa.title"}</h2>
					<span class="cimpact-oa-tag">{translate key="plugins.generic.citationImpact.oa.tag"}</span>
				</div>
				<p class="cimpact-sub">{translate key="plugins.generic.citationImpact.oa.note"}</p>
				<dl class="cimpact-oa-grid">
					<div><dt>{translate key="plugins.generic.citationImpact.oa.window" from=$ciStats.windowFrom to=$ciStats.windowTo}</dt><dd>{$ciOa.windowCitations|escape}</dd></div>
					<div><dt>{translate key="plugins.generic.citationImpact.stat.total"}</dt><dd>{$ciOa.totalCitations|escape}</dd></div>
					<div><dt>{translate key="plugins.generic.citationImpact.index.cited"}</dt><dd>{$ciOa.citedArticles|escape} <small>/ {$ciOa.checked|escape}</small></dd></div>
					<div><dt>h-index</dt><dd>{$ciOa.hIndex|escape}</dd></div>
				</dl>
			</section>
			{/if}

			{if $ciSelf}
			<p class="cimpact-selfnote"><span class="cimpact-self">{translate key="plugins.generic.citationImpact.citing.self"}</span> {translate key="plugins.generic.citationImpact.self.line" self=$ciSelf.self citing=$ciSelf.citing pct=$ciSelf.pct}</p>
			{/if}

			<section class="cimpact-card cimpact-charts">
				<div class="cimpact-tabs" role="tablist" aria-label="{translate key="plugins.generic.citationImpact.charts"}">
					<button type="button" role="tab" id="cimpact-tab-years" aria-controls="cimpact-panel-years" aria-selected="true">{translate key="plugins.generic.citationImpact.byYear"}</button>
					{if $ciByIssue}<button type="button" role="tab" id="cimpact-tab-issue" aria-controls="cimpact-panel-issue" aria-selected="false" tabindex="-1">{translate key="plugins.generic.citationImpact.byIssue"}</button>{/if}
					<button type="button" role="tab" id="cimpact-tab-growth" aria-controls="cimpact-panel-growth" aria-selected="false" tabindex="-1">{translate key="plugins.generic.citationImpact.history"}</button>
					<button type="button" role="tab" id="cimpact-tab-dist" aria-controls="cimpact-panel-dist" aria-selected="false" tabindex="-1">{translate key="plugins.generic.citationImpact.dist"}</button>
				</div>

				<div class="cimpact-panel" role="tabpanel" id="cimpact-panel-years" aria-labelledby="cimpact-tab-years">
					<p class="cimpact-sub">{translate key="plugins.generic.citationImpact.byYear.note"}</p>
					<div class="cimpact-chartbox">{$ciChartWide}{$ciChartNarrow}<div class="cimpact-tip" role="status" aria-live="polite"></div></div>
					<ul class="cimpact-legend">
						<li><span class="cimpact-key-ramp" style="background:linear-gradient(90deg,var(--ci-r0),var(--ci-r8))"></span>{translate key="plugins.generic.citationImpact.legend.ramp" from=$ciYearFrom to=$ciYearTo}</li>
						<li><span class="cimpact-key-band"></span>{translate key="plugins.generic.citationImpact.legend.band" from=$ciStats.windowFrom to=$ciStats.windowTo articles=$ciStats.windowArticles citations=$ciStats.windowCitations}</li>
					</ul>
					<details class="cimpact-details">
						<summary>{translate key="plugins.generic.citationImpact.asTable"}</summary>
						<table class="cimpact-ytable">
							<thead><tr><th>{translate key="plugins.generic.citationImpact.col.year"}</th><th>{translate key="plugins.generic.citationImpact.chart.articlesTitle"}</th><th>{translate key="plugins.generic.citationImpact.chart.citationsTitle"}</th></tr></thead>
							<tbody>
							{foreach from=$ciByYear item=yr}
								<tr><td>{$yr.year|escape}</td><td>{$yr.articles|escape}</td><td>{$yr.citations|escape}</td></tr>
							{/foreach}
							</tbody>
						</table>
					</details>
				</div>

				<div class="cimpact-panel" role="tabpanel" id="cimpact-panel-dist" aria-labelledby="cimpact-tab-dist" hidden>
					<p class="cimpact-sub">{translate key="plugins.generic.citationImpact.dist.note" total=$ciStats.checked}</p>
					<ul class="cimpact-dist">
					{foreach from=$ciDistribution item=b}
						<li>
							<span class="cimpact-dist-k">{translate key="plugins.generic.citationImpact.dist.band" band=$b.label}</span>
							<span class="cimpact-dist-track"><i style="width:{$b.width|escape}%;background:{$b.color|escape}"></i></span>
							<span class="cimpact-dist-v"><strong>{$b.articles|escape}</strong> <small>{$b.pct|escape}%</small></span>
						</li>
					{/foreach}
					</ul>
				</div>

				{if $ciByIssue}
				<div class="cimpact-panel" role="tabpanel" id="cimpact-panel-issue" aria-labelledby="cimpact-tab-issue" hidden>
					<p class="cimpact-sub">{translate key="plugins.generic.citationImpact.byIssue.note"}</p>
					<div class="cimpact-scroll">
					<table class="cimpact-ytable cimpact-issues">
						<thead><tr>
							<th>{translate key="plugins.generic.citationImpact.col.issue"}</th>
							<th>{translate key="plugins.generic.citationImpact.chart.articlesTitle"}</th>
							<th>{translate key="plugins.generic.citationImpact.index.cited"}</th>
							<th>{if $ciSeparate}Dimensions{else}{translate key="plugins.generic.citationImpact.chart.citationsTitle"}{/if}</th>
							{if $ciSeparate}<th class="cimpact-col-oa">OpenAlex</th>{/if}
						</tr></thead>
						<tbody>
						{foreach from=$ciByIssue item=v}
							<tr><td>{$v.label|escape}</td><td>{$v.articles|escape}</td><td>{$v.cited|escape} <small>({$v.citedPct|escape}%)</small></td><td><strong>{$v.citations|escape}</strong></td>{if $ciSeparate}<td class="cimpact-col-oa">{$v.openAlex|escape}</td>{/if}</tr>
						{/foreach}
						</tbody>
					</table>
					</div>
				</div>
				{/if}

				<div class="cimpact-panel" role="tabpanel" id="cimpact-panel-growth" aria-labelledby="cimpact-tab-growth" hidden>
					<p class="cimpact-sub">{translate key="plugins.generic.citationImpact.history.note"}</p>
					{if $ciHistoryPoints >= 2}
						<div class="cimpact-chartbox">{$ciHistoryWide}{$ciHistoryNarrow}<div class="cimpact-tip" role="status" aria-live="polite"></div></div>
						<ul class="cimpact-legend">
							<li><span class="cimpact-key-line"></span>{if $ciSeparate}Dimensions{else}{translate key="plugins.generic.citationImpact.chart.citationsTitle"}{/if}</li>
							{if $ciSeparate}<li><span class="cimpact-key-line cimpact-key-oa"></span>OpenAlex ({translate key="plugins.generic.citationImpact.oa.tag"})</li>{/if}
						</ul>
					{else}
						<p class="cimpact-card cimpact-empty">{translate key="plugins.generic.citationImpact.history.wait"}</p>
					{/if}
					{if $ciHistoryTable}
					<details class="cimpact-details">
						<summary>{translate key="plugins.generic.citationImpact.asTable"}</summary>
						<table class="cimpact-ytable">
							<thead><tr><th>{translate key="plugins.generic.citationImpact.history.month"}</th><th>{translate key="plugins.generic.citationImpact.chart.citationsTitle"}</th><th>{translate key="plugins.generic.citationImpact.hero.window"}</th>{if $ciSeparate}<th>OpenAlex</th>{/if}</tr></thead>
							<tbody>
							{foreach from=$ciHistoryTable item=h}
								<tr><td>{$h.month|escape}</td><td>{$h.c|escape}</td><td>{$h.w|escape} <small>({$h.wf|escape}–{$h.wt|escape})</small></td>{if $ciSeparate}<td>{$h.o|escape}</td>{/if}</tr>
							{/foreach}
							</tbody>
						</table>
					</details>
					{/if}
				</div>
			</section>

			{* The top-three cards only add something when the list is longer than three. *}
			{if $ciTop && $ciRowCount > 3}
			<section class="cimpact-topwrap" aria-labelledby="cimpact-top-h">
				<h2 id="cimpact-top-h">{translate key="plugins.generic.citationImpact.top.title"}</h2>
				<ol class="cimpact-top">
				{foreach from=$ciTop item=r}
					<li class="cimpact-card cimpact-topcard">
						<div class="cimpact-rank">#{$r.rank}</div>
						<div class="cimpact-top-n"><strong>{$r.cF|escape}</strong> {if $r.c == 1}{translate key="plugins.generic.citationImpact.chart.citation"}{else}{translate key="plugins.generic.citationImpact.chart.citations"}{/if}</div>
						<a class="cimpact-top-t" href="{$r.u|escape}">{$r.t|escape}</a>
						<div class="cimpact-a">{$r.a|escape}</div>
						<div class="cimpact-top-meta">
							<span>{$r.y|escape}</span>
							{if $r.fF !== null}<span class="cimpact-fcr{if $r.fHigh} is-high{/if}" title="{translate key="plugins.generic.citationImpact.fcrHelp"}">FCR {$r.fF|escape}</span>{/if}
						</div>
					</li>
				{/foreach}
				</ol>
			</section>
			{/if}

			<section class="cimpact-card">
				<h2>{if $ciListMode == 'all'}{translate key="plugins.generic.citationImpact.list.all"}{else}{translate key="plugins.generic.citationImpact.list.cited"}{/if}</h2>
				<p class="cimpact-sub">{if $ciListMode != 'all'}{translate key="plugins.generic.citationImpact.list.onlyCited"} {/if}{if $ciHasDim}{translate key="plugins.generic.citationImpact.fcrHelp"}{/if}</p>
				<div class="cimpact-tools">
					<input type="search" class="cimpact-q" placeholder="{translate key="plugins.generic.citationImpact.search"}" aria-label="{translate key="plugins.generic.citationImpact.search"}">
					<select class="cimpact-years" aria-label="{translate key="plugins.generic.citationImpact.filter.years"}">
						<option value="all">{translate key="plugins.generic.citationImpact.filter.allYears"}</option>
						<option value="window">{translate key="plugins.generic.citationImpact.filter.window" from=$ciStats.windowFrom to=$ciStats.windowTo}</option>
					</select>
					<select class="cimpact-sort" aria-label="{translate key="plugins.generic.citationImpact.sort"}">
						<option value="c">{translate key="plugins.generic.citationImpact.sort.cites"}</option>
						<option value="r">{translate key="plugins.generic.citationImpact.sort.recent"}</option>
						{if $ciSeparate}<option value="o">{translate key="plugins.generic.citationImpact.sort.openalex"}</option>{/if}
						<option value="y">{translate key="plugins.generic.citationImpact.sort.newest"}</option>
					</select>
				</div>
				<table class="cimpact-list">
					<thead><tr>
						<th scope="col" class="cimpact-rk">#</th>
						<th scope="col">{translate key="plugins.generic.citationImpact.col.article"}</th>
						<th scope="col" class="cimpact-hide-sm">{translate key="plugins.generic.citationImpact.col.year"}</th>
						<th scope="col" class="cimpact-cc">{if $ciSeparate}Dimensions{else}{translate key="plugins.generic.citationImpact.col.cites"}{/if}</th>
						<th scope="col" class="cimpact-hide-sm" title="{translate key="plugins.generic.citationImpact.stat.recent"}">{translate key="plugins.generic.citationImpact.col.recent"}</th>
						<th scope="col" class="cimpact-hide-sm cimpact-col-fcr" title="{translate key="plugins.generic.citationImpact.fcrHelp"}">FCR</th>
						{if $ciSeparate}<th scope="col" class="cimpact-hide-sm cimpact-col-oa" title="{translate key="plugins.generic.citationImpact.oa.colHelp"}">OpenAlex</th>{/if}
					</tr></thead>
					<tbody class="cimpact-rows">
					{foreach from=$ciRows item=r}
						<tr>
							<td class="cimpact-rk">{$r.rank}</td>
							<td>
								<a class="cimpact-t" href="{$r.u|escape}">{$r.t|escape}</a>
								<div class="cimpact-a">{$r.a|escape}{if $r.is} &middot; <span class="cimpact-is">{$r.is|escape}</span>{/if}</div>
								<div class="cimpact-s"><a href="https://doi.org/{$r.d|escape}" target="_blank" rel="noopener">doi:{$r.d|escape}</a>{if $ciHasDim} &middot; <a href="https://badge.dimensions.ai/details/doi/{$r.d|escape}" target="_blank" rel="noopener">{if $ciMerged && $r.cd !== null}Dimensions: {$r.cd|escape}{else}{translate key="plugins.generic.citationImpact.dimensionsDetails"}{/if}</a>{/if}{if $r.oi} &middot; <a href="https://openalex.org/{if $r.co}works?filter=cites:{$r.oi|escape}{else}{$r.oi|escape}{/if}" target="_blank" rel="noopener">{if $ciMerged}OpenAlex: {$r.co|escape}{else}{translate key="plugins.generic.citationImpact.openAlexDetails"}{/if}</a>{/if}</div>
								{if $r.cw}<details class="cimpact-citing"><summary>{translate key="plugins.generic.citationImpact.citing.summary" n=$r.cw|count}{if $r.sc} &middot; <span class="cimpact-self">{translate key="plugins.generic.citationImpact.citing.selfN" n=$r.sc}</span>{/if}</summary><ol>{foreach from=$r.cw item=w}<li><a href="{if $w.d}https://doi.org/{$w.d|escape}{else}https://openalex.org/{$w.id|escape}{/if}" target="_blank" rel="noopener">{$w.t|escape}</a> <span class="cimpact-cm">{$w.a|escape}{if $w.s} &middot; <em>{$w.s|escape}</em>{/if}{if $w.y} &middot; {$w.y|escape}{/if}</span>{if $w.self} <span class="cimpact-self">{translate key="plugins.generic.citationImpact.citing.self"}</span>{/if}</li>{/foreach}</ol></details>{/if}
							</td>
							<td class="cimpact-hide-sm">{$r.y|escape}</td>
							<td class="cimpact-cc">{if $r.cF === null}<span class="cimpact-muted">–</span>{else}<span class="cimpact-cbar"><b>{$r.cF|escape}</b><i style="width:{$r.cW|escape}%"></i></span>{/if}</td>
							<td class="cimpact-hide-sm">{if $r.rF === null}<span class="cimpact-muted">–</span>{else}{$r.rF|escape}{/if}</td>
							<td class="cimpact-hide-sm cimpact-col-fcr">{if $r.fF === null}<span class="cimpact-muted">–</span>{else}<span class="cimpact-fcr{if $r.fHigh} is-high{/if}">{$r.fF|escape}</span>{/if}</td>
							{if $ciSeparate}<td class="cimpact-hide-sm cimpact-col-oa">{if $r.co === null}<span class="cimpact-muted">–</span>{else}{$r.co|escape}{/if}</td>{/if}
						</tr>
					{/foreach}
					</tbody>
				</table>
				<p class="cimpact-empty cimpact-nomatch" hidden>{translate key="plugins.generic.citationImpact.noMatch"}</p>
				<button type="button" class="cimpact-more"{if $ciRowCount <= 25} hidden{/if}>{translate key="plugins.generic.citationImpact.more"}</button>
				<p class="cimpact-count">{$ciShown|escape}</p>
				<p class="cimpact-dlrow"><a class="cimpact-dl" href="{$ciCsvUrl|escape}" download>{translate key="plugins.generic.citationImpact.csv.download"}</a>{if $ciHasOpenAlex} <a class="cimpact-dl cimpact-dl-2" href="{$ciCitingCsvUrl|escape}" download>{translate key="plugins.generic.citationImpact.csv.downloadCiting"}</a>{/if} <span>{translate key="plugins.generic.citationImpact.csv.help"}</span></p>
			</section>
		{/if}

		<footer class="cimpact-footer">
			<p>{translate key="plugins.generic.citationImpact.source" sources=$ciSourcesHtml}{if $ciMerged} {translate key="plugins.generic.citationImpact.sourceMerged"}{/if}{if $ciSeparate} {translate key="plugins.generic.citationImpact.sourceSeparate"}{/if}</p>
			{if $ciHasData && $ciPending}<p>{translate key="plugins.generic.citationImpact.pending"}</p>{/if}
			{if $ciWithoutDoi}<p>{translate key="plugins.generic.citationImpact.withoutDoi" number=$ciWithoutDoi}</p>{/if}
			{if $ciLicense}<p>{$ciLicense|escape}</p>{/if}
		</footer>
	</div>
	<script type="application/json" id="cimpact-data">{$ciJson}</script>
</div>

{include file="frontend/components/footer.tpl"}
