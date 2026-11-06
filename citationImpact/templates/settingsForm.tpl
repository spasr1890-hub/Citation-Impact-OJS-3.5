{**
 * plugins/generic/citationImpact/templates/settingsForm.tpl
 *
 * Distributed under the GNU GPL v3.
 *
 * Citation Impact plugin settings, status and "refresh now".
 *}
<script>
	$(function() {ldelim}
		$('#citationImpactSettingsForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
	{rdelim});
</script>

<div class="citationImpactStatus" id="citationImpactStatus"
	data-url="{$refreshUrl|escape}"
	data-csrf="{csrf type="raw"}"
	data-total="{$impactStatus.articles|escape}"
	data-checked="{$impactStatus.checked|escape}"
	data-msg-harvest="{translate key="plugins.generic.citationImpact.refresh.running"}"
	data-msg-done="{translate key="plugins.generic.citationImpact.refresh.done"}"
	data-msg-justnow="{translate key="plugins.generic.citationImpact.status.justNow"}"
	data-msg-busy="{translate key="plugins.generic.citationImpact.refresh.busy"}"
	data-msg-backoff="{translate key="plugins.generic.citationImpact.refresh.backoff"}"
	data-msg-error="{translate key="plugins.generic.citationImpact.refresh.error"}"
	data-msg-stopped="{translate key="plugins.generic.citationImpact.refresh.stopped"}"
	style="border:1px solid #ddd;border-radius:4px;padding:1rem 1.25rem;margin-bottom:1.5rem;background:#fafafa">
	<p style="margin:0 0 .5rem;font-weight:bold">{translate key="plugins.generic.citationImpact.status.title"}</p>
	{if $impactError}
		<p style="margin:0 0 .75rem;padding:.5rem .75rem;background:#fdecea;border:1px solid #f5c2c0;border-radius:4px;color:#8a1f17">
			{translate key="plugins.generic.citationImpact.status.error"}<br><code style="font-size:.85em;word-break:break-word">{$impactError|escape}</code>
		</p>
	{/if}
	<p style="margin:0 0 .25rem">
		<span class="ci-checked" style="font-size:1.5rem;font-weight:bold">{$impactStatus.checked|escape}</span>
		{translate key="plugins.generic.citationImpact.status.checkedOf" total=$impactStatus.articles}
	</p>
	<div style="height:6px;background:#e5e5e5;border-radius:3px;overflow:hidden;margin:.5rem 0">
		<div class="ci-bar" style="height:100%;width:{$impactPercent|escape}%;background:#2a78d6;transition:width .4s"></div>
	</div>
	<p style="margin:0 0 .25rem;color:#555">
		<span class="ci-updated">{if $impactStatus.updatedAt}
			{translate key="plugins.generic.citationImpact.status.updated" date=$impactStatus.updatedAt|date_format:$datetimeFormatShort}
		{else}
			{translate key="plugins.generic.citationImpact.status.never"}
		{/if}</span>
		{if $impactStatus.withoutDoi}
			&middot; {translate key="plugins.generic.citationImpact.status.withoutDoi" count=$impactStatus.withoutDoi}
		{/if}
	</p>
	<p class="ci-msg" role="status" aria-live="polite" style="margin:.5rem 0;color:#555;min-height:1.2em"></p>
	<p style="margin:.75rem 0 0">
		<button type="button" class="pkp_button ci-run">{translate key="plugins.generic.citationImpact.refresh.button"}</button>
		<a href="{$impactPageUrl|escape}" target="_blank" rel="noopener" style="margin-left:1rem">{translate key="plugins.generic.citationImpact.status.viewPage"}</a>
	</p>
	<p style="margin:.5rem 0 0;color:#777;font-size:.85em">{translate key="plugins.generic.citationImpact.refresh.help"}</p>
</div>

<script>
{literal}
(function () {
	var box = document.getElementById('citationImpactStatus');
	if (!box) return;
	var $box = $(box), url = box.getAttribute('data-url'), csrf = box.getAttribute('data-csrf');
	var total = parseInt(box.getAttribute('data-total'), 10) || 0, running = false, stopped = false;
	var btn = $box.find('.ci-run'), msg = $box.find('.ci-msg');
	function t(key, vars) {
		var s = box.getAttribute('data-msg-' + key) || '';
		for (var k in (vars || {})) s = s.split('{$' + k + '}').join(vars[k]);
		return s;
	}
	function show(r) {
		if (r.articles != null) total = r.articles;
		if (r.checked != null) $box.find('.ci-checked').text(r.checked);
		if (r.processed) $box.find('.ci-updated').text(t('justnow'));
		if (total) $box.find('.ci-bar').css('width', Math.min(100, (r.checked || 0) / total * 100) + '%');
	}
	function step() {
		if (stopped) { running = false; btn.prop('disabled', false); return; }
		$.ajax({url: url, type: 'POST', dataType: 'json', data: {csrfToken: csrf}})
			.done(function (json) {
				var r = json && json.status ? json.content : null;
				if (!r) { msg.text(t('error')); running = false; btn.prop('disabled', false); return; }
				show(r);
				if (r.status === 'partial') { msg.text(t('harvest', {remaining: r.remaining})); return setTimeout(step, 300); }
				if (r.status === 'busy') { msg.text(t('busy')); return setTimeout(step, 5000); }
				running = false; btn.prop('disabled', false);
				if (r.status === 'ok') msg.text(t('done'));
				else if (r.status === 'backoff') msg.text(t('backoff'));
				else if (r.reason === 'exception') msg.text(r.message || t('stopped'));
				else msg.text(t('stopped'));
			})
			.fail(function () {
				msg.text(t('error'));
				setTimeout(step, 10000);
			});
	}
	btn.on('click', function () {
		if (running) return;
		running = true; stopped = false; btn.prop('disabled', true);
		msg.text(t('harvest', {remaining: '…'}));
		step();
	});
	// Stop the loop when the modal closes.
	$box.closest('.pkp_modal').on('pkpRemoveHandler remove', function () { stopped = true; });
	$(box).on('remove', function () { stopped = true; });
})();
{/literal}
</script>

<form class="pkp_form" id="citationImpactSettingsForm" method="post" action="{url router=PKP\core\PKPApplication::ROUTE_COMPONENT op="manage" category="generic" plugin=$pluginName verb="settings" save=true}">
	{csrf}
	{include file="controllers/notification/inPlaceNotification.tpl" notificationId="citationImpactSettingsFormNotification"}

	<p>{translate key="plugins.generic.citationImpact.settings.description"}</p>

	{fbvFormArea id="citationImpactSettingsArea"}
		{fbvFormSection title="plugins.generic.citationImpact.settings.pagePath"}
			{fbvElement type="text" id="pagePath" value=$pagePath size=$fbvStyles.size.SMALL required=true label="plugins.generic.citationImpact.settings.pagePath.description"}
		{/fbvFormSection}
		{fbvFormSection title="plugins.generic.citationImpact.settings.listMode"}
			{fbvElement type="select" id="listMode" from=$listModeOptions selected=$listMode size=$fbvStyles.size.MEDIUM translate=true}
		{/fbvFormSection}
		{fbvFormSection title="plugins.generic.citationImpact.settings.window"}
			{fbvElement type="select" id="windowIncludeCurrent" from=$windowOptions selected=$windowIncludeCurrent size=$fbvStyles.size.MEDIUM translate=false label="plugins.generic.citationImpact.settings.window.description"}
		{/fbvFormSection}
		{fbvFormSection title="plugins.generic.citationImpact.settings.ttlDays"}
			{fbvElement type="text" id="ttlDays" value=$ttlDays size=$fbvStyles.size.SMALL required=true label="plugins.generic.citationImpact.settings.ttlDays.description"}
		{/fbvFormSection}
		{fbvFormSection title="plugins.generic.citationImpact.settings.accentColor"}
			{if $themeColour}
				{capture assign="ciAccentHelp"}{translate key="plugins.generic.citationImpact.settings.accentColor.theme" colour=$themeColour}{/capture}
			{else}
				{capture assign="ciAccentHelp"}{translate key="plugins.generic.citationImpact.settings.accentColor.auto"}{/capture}
			{/if}
			{fbvElement type="text" id="accentColor" value=$accentColor size=$fbvStyles.size.SMALL maxlength="7" label=$ciAccentHelp subLabelTranslate=false}
			{if $themeColour}<p style="margin:.25rem 0 0"><span style="display:inline-block;width:1em;height:1em;border-radius:3px;vertical-align:-2px;margin-right:.35em;border:1px solid rgba(0,0,0,.2);background:{$themeColour|escape}"></span>{$themeColour|escape}</p>{/if}
		{/fbvFormSection}
		{fbvFormSection title="plugins.generic.citationImpact.settings.contactEmail"}
			{fbvElement type="email" id="contactEmail" value=$contactEmail size=$fbvStyles.size.MEDIUM label="plugins.generic.citationImpact.settings.contactEmail.description"}
		{/fbvFormSection}
	{/fbvFormArea}

	{fbvFormButtons}
</form>
