{**
 * plugins/generic/citationImpact/templates/block.tpl
 *
 * Distributed under the GNU GPL v3.
 *
 * Sidebar block. Colours come from the theme (currentColor, inherit).
 *}
<div class="pkp_block block_citation_impact">
	<h2 class="title">{translate key="plugins.generic.citationImpact.block.title"}</h2>
	<div class="content">
		<a class="cib-main" href="{$ciBlock.url|escape}">
			<span class="cib-v">{$ciBlock.window|escape}</span>
			<span class="cib-k">{translate key="plugins.generic.citationImpact.hero.window"} <small>({$ciBlock.windowFrom|escape}–{$ciBlock.windowTo|escape})</small></span>
		</a>
		<ul class="cib-list">
			<li><span>{translate key="plugins.generic.citationImpact.block.total"}</span><strong>{$ciBlock.total|escape}</strong></li>
			<li><span>h-index</span><strong>{$ciBlock.hIndex|escape}</strong></li>
			<li><span>{translate key="plugins.generic.citationImpact.index.cited"}</span><strong>{$ciBlock.cited|escape} / {$ciBlock.articles|escape}</strong></li>
		</ul>
		<a class="cib-more" href="{$ciBlock.url|escape}">{translate key="plugins.generic.citationImpact.block.more"}</a>
		<div class="cib-src">{translate key="plugins.generic.citationImpact.sourceChip" sources=$ciBlock.source}</div>
	</div>
</div>
<style>
.block_citation_impact .cib-main { display: block; text-decoration: none; margin-bottom: .6em; }
.block_citation_impact .cib-v { display: block; font-size: 2.4em; line-height: 1.05; font-weight: 400; }
.block_citation_impact .cib-k { display: block; font-size: .85em; color: inherit; opacity: .85; }
.block_citation_impact .cib-list { list-style: none; margin: 0 0 .6em; padding: 0; font-size: .9em; }
.block_citation_impact .cib-list li { display: flex; justify-content: space-between; gap: .5em; padding: .3em 0; border-top: 1px solid rgba(127, 127, 127, .25); }
.block_citation_impact .cib-more { font-size: .9em; font-weight: 600; }
.block_citation_impact .cib-src { margin-top: .4em; font-size: .75em; opacity: .7; }
</style>
