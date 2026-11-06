<?php

/**
 * @file plugins/generic/citationImpact/CitationImpactSettingsForm.php
 *
 * Distributed under the GNU GPL v3.
 *
 * @class CitationImpactSettingsForm
 * @brief Settings form, status panel and "refresh now" control.
 */

namespace APP\plugins\generic\citationImpact;

use APP\core\Application;
use APP\plugins\generic\citationImpact\classes\CitationImpactService;
use APP\template\TemplateManager;
use PKP\form\Form;
use PKP\form\validation\FormValidatorCSRF;
use PKP\form\validation\FormValidatorCustom;
use PKP\form\validation\FormValidatorEmail;
use PKP\form\validation\FormValidatorInSet;
use PKP\form\validation\FormValidatorPost;
use PKP\form\validation\FormValidatorRegExp;
use Throwable;

class CitationImpactSettingsForm extends Form {

	/** @var CitationImpactPlugin */
	protected $plugin;

	/** @var int */
	protected $contextId;

	public function __construct($plugin, $contextId) {
		$this->plugin = $plugin;
		$this->contextId = (int) $contextId;
		parent::__construct($plugin->getTemplateResource('settingsForm.tpl'));

		$this->addCheck(new FormValidatorEmail($this, 'contactEmail', 'optional', 'plugins.generic.citationImpact.settings.contactEmail.invalid'));
		$this->addCheck(new FormValidatorInSet($this, 'listMode', 'required', 'form.required', array('cited', 'all')));
		$this->addCheck(new FormValidatorInSet($this, 'windowIncludeCurrent', 'required', 'form.required', array('0', '1')));
		$this->addCheck(new FormValidatorRegExp($this, 'pagePath', 'required', 'plugins.generic.citationImpact.settings.pagePath.invalid', '/^[a-z0-9][a-z0-9_-]{0,48}$/'));
		$this->addCheck(new FormValidatorCustom($this, 'ttlDays', 'required', 'plugins.generic.citationImpact.settings.ttlDays.invalid', function ($v) {
			return ctype_digit((string) $v) && (int) $v >= 1 && (int) $v <= 90;
		}));
		$this->addCheck(new FormValidatorPost($this));
		$this->addCheck(new FormValidatorCSRF($this));
	}

	public function initData() {
		$s = CitationImpactService::getSettings($this->contextId);
		if ($s['contactEmail'] === '') {
			$context = Application::get()->getRequest()->getContext();
			$s['contactEmail'] = $context ? (string) $context->getData('contactEmail') : '';
		}
		$this->_data = array(
			'contactEmail' => $s['contactEmail'],
			'listMode' => $s['listMode'],
			'ttlDays' => $s['ttlDays'],
			'pagePath' => $s['pagePath'],
			'windowIncludeCurrent' => $s['windowIncludeCurrent'] ? '1' : '0',
		);
	}

	public function readInputData() {
		$this->readUserVars(array('contactEmail', 'listMode', 'ttlDays', 'pagePath', 'windowIncludeCurrent'));
		$this->setData('pagePath', strtolower(trim((string) $this->getData('pagePath'))));
		$this->setData('contactEmail', trim((string) $this->getData('contactEmail')));
	}

	public function fetch($request, $template = null, $display = false) {
		$service = new CitationImpactService($this->contextId);
		$statusError = null;
		try {
			$status = $service->status();
		} catch (Throwable $e) {
			error_log('Citation Impact: ' . $e->getMessage());
			$status = array('articles' => 0, 'checked' => 0, 'withoutDoi' => 0, 'updatedAt' => null);
			$statusError = $e->getMessage();
		}
		$pageUrl = $request->getDispatcher()->url($request, Application::ROUTE_PAGE, null, $this->plugin->getPagePath($this->contextId));

		list($curFrom, $curTo) = CitationImpactService::windowYears(true);
		list($fullFrom, $fullTo) = CitationImpactService::windowYears(false);
		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign(array(
			'pluginName' => $this->plugin->getName(),
			'windowOptions' => array(
				'1' => __('plugins.generic.citationImpact.settings.window.current', array('from' => $curFrom, 'to' => $curTo)),
				'0' => __('plugins.generic.citationImpact.settings.window.full', array('from' => $fullFrom, 'to' => $fullTo)),
			),
			'listModeOptions' => array(
				'cited' => 'plugins.generic.citationImpact.settings.listMode.cited',
				'all' => 'plugins.generic.citationImpact.settings.listMode.all',
			),
			'impactStatus' => $status,
			'impactError' => $statusError,
			'impactPercent' => $status['articles'] ? min(100, round($status['checked'] / $status['articles'] * 100, 1)) : 0,
			'impactPageUrl' => $pageUrl,
			'refreshUrl' => $request->getDispatcher()->url($request, Application::ROUTE_COMPONENT, null, 'grid.settings.plugins.SettingsPluginGridHandler', 'manage', null,
				array('verb' => 'refresh', 'plugin' => $this->plugin->getName(), 'category' => 'generic')),
		));
		return parent::fetch($request, $template, $display);
	}

	public function execute(...$functionArgs) {
		$this->plugin->updateSetting($this->contextId, 'contactEmail', (string) $this->getData('contactEmail'), 'string');
		$this->plugin->updateSetting($this->contextId, 'listMode', (string) $this->getData('listMode'), 'string');
		$this->plugin->updateSetting($this->contextId, 'ttlDays', (int) $this->getData('ttlDays'), 'int');
		$this->plugin->updateSetting($this->contextId, 'pagePath', (string) $this->getData('pagePath'), 'string');
		$this->plugin->updateSetting($this->contextId, 'windowIncludeCurrent', $this->getData('windowIncludeCurrent') === '0' ? '0' : '1', 'string');
		parent::execute(...$functionArgs);
	}
}
