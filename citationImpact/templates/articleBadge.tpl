{**
 * plugins/generic/citationImpact/templates/articleBadge.tpl
 *
 * Distributed under the GNU GPL v3.
 *
 * Citation count on the article page. Colours follow the theme (currentColor).
 *}
<section class="item cimpact-badge">
	<h2 class="label">{translate key="plugins.generic.citationImpact.badge.title"}</h2>
	<div class="value">
		<a class="cimpact-badge-main" href="{$ciBadge.pageUrl|escape}">
			<strong>{$ciBadge.count|escape}</strong>
			<span>{if $ciBadge.count == 1}{translate key="plugins.generic.citationImpact.chart.citation"}{else}{translate key="plugins.generic.citationImpact.chart.citations"}{/if}</span>
		</a>
		{if $ciBadge.recent}<div class="cimpact-badge-sub">{translate key="plugins.generic.citationImpact.stat.recentLine" number=$ciBadge.recent}</div>{/if}
		{if $ciBadge.links}
			<ul class="cimpact-badge-links">
				{foreach from=$ciBadge.links item=l}
					<li><a href="{$l.url|escape}" target="_blank" rel="noopener">{$l.name|escape}{if $ciBadge.merged}: {$l.count|escape}{/if}</a></li>
				{/foreach}
			</ul>
		{/if}
		{if $ciBadge.merged}<div class="cimpact-badge-note">{translate key="plugins.generic.citationImpact.badge.merged"}</div>{/if}
	</div>
</section>
<style>
.cimpact-badge .cimpact-badge-main { display: inline-flex; align-items: baseline; gap: .35em; text-decoration: none; }
.cimpact-badge .cimpact-badge-main strong { font-size: 2em; line-height: 1; }
.cimpact-badge .cimpact-badge-main:hover span, .cimpact-badge .cimpact-badge-main:focus span { text-decoration: underline; }
.cimpact-badge .cimpact-badge-sub, .cimpact-badge .cimpact-badge-note { font-size: .85em; opacity: .8; margin-top: .3em; }
.cimpact-badge .cimpact-badge-links { list-style: none; margin: .5em 0 0; padding: 0; display: flex; flex-wrap: wrap; gap: .4em; }
.cimpact-badge .cimpact-badge-links li { margin: 0; }
.cimpact-badge .cimpact-badge-links a {
	display: inline-block; padding: .15em .65em; border: 1px solid currentColor; border-radius: 999px; font-size: .85em; text-decoration: none;
}
.cimpact-badge .cimpact-badge-links a:hover, .cimpact-badge .cimpact-badge-links a:focus { text-decoration: underline; }
</style>
