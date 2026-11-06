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

	<div class="cimpact">
		<header class="cimpact-head">
			<h1 class="cimpact-title">{$ciTitle|escape}</h1>
			<p class="cimpact-lead">{translate key="plugins.generic.citationImpact.lead" journal=$currentContext->getLocalizedName()|escape}</p>
			<p class="cimpact-meta">
				<span class="cimpact-chip">{translate key="plugins.generic.citationImpact.sourceChip"}</span>
				{if $ciUpdatedAt}<span>{translate key="plugins.generic.citationImpact.updated" date=$ciUpdatedAt|date_format:$dateFormatLong}</span>{/if}
				<span>{translate key="plugins.generic.citationImpact.coverage" checked=$ciStats.checked total=$ciStats.articles}</span>
			</p>
		</header>

		{if !$ciHasData}
			<div class="cimpact-card cimpact-empty">{translate key="plugins.generic.citationImpact.empty"}</div>
		{else}
			<section class="cimpact-overview" aria-label="{translate key="plugins.generic.citationImpact.summary"}">
				<div class="cimpact-card cimpact-hero">
					<div class="cimpact-hero-k">{translate key="plugins.generic.citationImpact.stat.total"}</div>
					<div class="cimpact-hero-v">{$ciStats.totalCitations}</div>
					<div class="cimpact-hero-sub">{translate key="plugins.generic.citationImpact.stat.recentLine" count=$ciStats.recentCitations}</div>
					<dl class="cimpact-mini">
						<div><dt>{translate key="plugins.generic.citationImpact.stat.mean"}</dt><dd>{$ciStats.mean}</dd></div>
						<div><dt>{translate key="plugins.generic.citationImpact.stat.max"}</dt><dd>{$ciStats.max}</dd></div>
					</dl>
					<div class="cimpact-ringrow">
						{$ciRing}
						<div>
							<div class="cimpact-ring-k">{translate key="plugins.generic.citationImpact.stat.citedLine" cited=$ciStats.citedArticles total=$ciStats.checked}</div>
							<div class="cimpact-ring-s">{translate key="plugins.generic.citationImpact.stat.citedNote"}</div>
						</div>
					</div>
				</div>

				<div class="cimpact-card cimpact-index">
					<h2>{translate key="plugins.generic.citationImpact.index.title"}</h2>
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

			<section class="cimpact-card cimpact-charts">
				<div class="cimpact-tabs" role="tablist" aria-label="{translate key="plugins.generic.citationImpact.charts"}">
					<button type="button" role="tab" id="cimpact-tab-years" aria-controls="cimpact-panel-years" aria-selected="true">{translate key="plugins.generic.citationImpact.byYear"}</button>
					<button type="button" role="tab" id="cimpact-tab-dist" aria-controls="cimpact-panel-dist" aria-selected="false" tabindex="-1">{translate key="plugins.generic.citationImpact.dist"}</button>
				</div>

				<div class="cimpact-panel" role="tabpanel" id="cimpact-panel-years" aria-labelledby="cimpact-tab-years">
					<p class="cimpact-sub">{translate key="plugins.generic.citationImpact.byYear.note"}</p>
					<div class="cimpact-chartbox">{$ciChartWide}{$ciChartNarrow}<div class="cimpact-tip" role="status" aria-live="polite"></div></div>
					<ul class="cimpact-legend">
						<li><span class="cimpact-key-ramp" style="background:linear-gradient(90deg,{$ciRampFrom},{$ciRampTo})"></span>{translate key="plugins.generic.citationImpact.legend.ramp" from=$ciYearFrom to=$ciYearTo}</li>
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
			</section>

			{if $ciTop}
			<section class="cimpact-topwrap" aria-labelledby="cimpact-top-h">
				<h2 id="cimpact-top-h">{translate key="plugins.generic.citationImpact.top.title"}</h2>
				<ol class="cimpact-top">
				{foreach from=$ciTop item=r}
					<li class="cimpact-card cimpact-topcard">
						<div class="cimpact-rank">#{$r.rank}</div>
						<div class="cimpact-top-n"><strong>{$r.cF|escape}</strong> {translate key="plugins.generic.citationImpact.chart.citations"}</div>
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
				<p class="cimpact-sub">{if $ciListMode != 'all'}{translate key="plugins.generic.citationImpact.list.onlyCited"} {/if}{translate key="plugins.generic.citationImpact.fcrHelp"}</p>
				<div class="cimpact-tools">
					<input type="search" class="cimpact-q" placeholder="{translate key="plugins.generic.citationImpact.search"}" aria-label="{translate key="plugins.generic.citationImpact.search"}">
					<select class="cimpact-years" aria-label="{translate key="plugins.generic.citationImpact.filter.years"}">
						<option value="all">{translate key="plugins.generic.citationImpact.filter.allYears"}</option>
						<option value="window">{translate key="plugins.generic.citationImpact.filter.window" from=$ciStats.windowFrom to=$ciStats.windowTo}</option>
					</select>
					<select class="cimpact-sort" aria-label="{translate key="plugins.generic.citationImpact.sort"}">
						<option value="c">{translate key="plugins.generic.citationImpact.sort.cites"}</option>
						<option value="r">{translate key="plugins.generic.citationImpact.sort.recent"}</option>
						<option value="y">{translate key="plugins.generic.citationImpact.sort.newest"}</option>
					</select>
				</div>
				<table class="cimpact-list">
					<thead><tr>
						<th scope="col" class="cimpact-rk">#</th>
						<th scope="col">{translate key="plugins.generic.citationImpact.col.article"}</th>
						<th scope="col" class="cimpact-hide-sm">{translate key="plugins.generic.citationImpact.col.year"}</th>
						<th scope="col" class="cimpact-cc">{translate key="plugins.generic.citationImpact.col.cites"}</th>
						<th scope="col" class="cimpact-hide-sm" title="{translate key="plugins.generic.citationImpact.stat.recent"}">{translate key="plugins.generic.citationImpact.col.recent"}</th>
						<th scope="col" class="cimpact-hide-sm" title="{translate key="plugins.generic.citationImpact.fcrHelp"}">FCR</th>
					</tr></thead>
					<tbody class="cimpact-rows">
					{foreach from=$ciRows item=r}
						<tr>
							<td class="cimpact-rk">{$r.rank}</td>
							<td>
								<a class="cimpact-t" href="{$r.u|escape}">{$r.t|escape}</a>
								<div class="cimpact-a">{$r.a|escape}</div>
								<div class="cimpact-s"><a href="https://doi.org/{$r.d|escape}" target="_blank" rel="noopener">doi:{$r.d|escape}</a> &middot; <a href="https://badge.dimensions.ai/details/doi/{$r.d|escape}" target="_blank" rel="noopener">{translate key="plugins.generic.citationImpact.dimensionsDetails"}</a></div>
							</td>
							<td class="cimpact-hide-sm">{$r.y|escape}</td>
							<td class="cimpact-cc">{if $r.cF === null}<span class="cimpact-muted">–</span>{else}<span class="cimpact-cbar"><b>{$r.cF|escape}</b><i style="width:{$r.cW|escape}%"></i></span>{/if}</td>
							<td class="cimpact-hide-sm">{if $r.rF === null}<span class="cimpact-muted">–</span>{else}{$r.rF|escape}{/if}</td>
							<td class="cimpact-hide-sm">{if $r.fF === null}<span class="cimpact-muted">–</span>{else}<span class="cimpact-fcr{if $r.fHigh} is-high{/if}">{$r.fF|escape}</span>{/if}</td>
						</tr>
					{/foreach}
					</tbody>
				</table>
				<p class="cimpact-empty cimpact-nomatch" hidden>{translate key="plugins.generic.citationImpact.noMatch"}</p>
				<button type="button" class="cimpact-more"{if $ciRowCount <= 25} hidden{/if}>{translate key="plugins.generic.citationImpact.more"}</button>
				<p class="cimpact-count">{$ciShown|escape}</p>
			</section>
		{/if}

		<footer class="cimpact-footer">
			<p>{translate key="plugins.generic.citationImpact.source"}</p>
			{if $ciHasData && $ciPending}<p>{translate key="plugins.generic.citationImpact.pending"}</p>{/if}
			{if $ciWithoutDoi}<p>{translate key="plugins.generic.citationImpact.withoutDoi" count=$ciWithoutDoi}</p>{/if}
			{if $ciLicense}<p>{$ciLicense|escape}</p>{/if}
		</footer>
	</div>
	<script type="application/json" id="cimpact-data">{$ciJson}</script>
</div>

{include file="frontend/components/footer.tpl"}
