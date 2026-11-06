<?php

/**
 * @file plugins/generic/citationImpact/CitationImpactPlugin.php
 *
 * Distributed under the GNU GPL v3.
 *
 * @class CitationImpactPlugin
 * @brief Adds a public "Citation Impact" page (citations from Dimensions) to
 *  the journal, a navigation menu item type for it, a settings form with a
 *  "refresh now" button, and an hourly scheduled task that keeps it current.
 *
 *  OJS 3.5 edition.
 */

namespace APP\plugins\generic\citationImpact;

use APP\core\Application;
use APP\plugins\generic\citationImpact\classes\CitationImpactService;
use APP\plugins\generic\citationImpact\classes\CitationImpactTask;
use PKP\core\JSONMessage;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;
use PKP\plugins\GenericPlugin;
use PKP\plugins\Hook;
use PKP\plugins\PluginRegistry;
use PKP\plugins\interfaces\HasTaskScheduler;
use PKP\scheduledTask\PKPScheduler;
use Throwable;

class CitationImpactPlugin extends GenericPlugin implements HasTaskScheduler
{
    public const NMI_TYPE_CITATION_IMPACT = 'NMI_TYPE_CITATION_IMPACT';

    /**
     * @copydoc Plugin::register()
     *
     * @param null|mixed $mainContextId
     */
    public function register($category, $path, $mainContextId = null)
    {
        $success = parent::register($category, $path, $mainContextId);
        if (!$success || Application::isUnderMaintenance()) {
            return $success;
        }
        if ($this->getEnabled($mainContextId)) {
            Hook::add('LoadHandler', $this->callbackLoadHandler(...));
            Hook::add('NavigationMenus::itemTypes', $this->addMenuItemTypes(...));
            Hook::add('NavigationMenus::displaySettings', $this->setMenuItemDisplayDetails(...));
            Hook::add('Templates::Article::Details', $this->articleBadge(...));
            // Sidebar block; add it under Settings > Website > Appearance > Setup > Sidebar.
            PluginRegistry::register('blocks', new CitationImpactBlockPlugin($this), $this->getPluginPath());
        }
        return $success;
    }

    public function getDisplayName()
    {
        return __('plugins.generic.citationImpact.displayName');
    }

    public function getDescription()
    {
        return __('plugins.generic.citationImpact.description');
    }

    /**
     * @return string Page path of the public page (default "impact").
     */
    public function getPagePath($contextId): string
    {
        $path = (string) $this->getSetting($contextId, 'pagePath');
        return preg_match('/^[a-z0-9][a-z0-9_-]{0,48}$/', $path) ? $path : 'impact';
    }

    /**
     * @copydoc HasTaskScheduler::registerSchedules()
     *
     * Hourly. The task itself skips journals that do not have the plugin
     * enabled and caps each run at about 25 seconds.
     */
    public function registerSchedules(PKPScheduler $scheduler): void
    {
        $scheduler
            ->addSchedule(new CitationImpactTask())
            ->hourly()
            ->name(CitationImpactTask::class)
            ->withoutOverlapping();
    }

    //
    // Hooks
    //

    /**
     * Serve /index.php/{journal}/{pagePath}[/json] with our handler.
     *
     * @hook LoadHandler [[&$page, &$op, &$sourceFile, &$handler]]
     */
    public function callbackLoadHandler(string $hookName, array $args): bool
    {
        $page = &$args[0];
        $handler = &$args[3];
        $context = Application::get()->getRequest()->getContext();
        if (!$context || $page !== $this->getPagePath($context->getId())) {
            return false;
        }
        $handler = new CitationImpactHandler($this);
        return true;
    }

    /**
     * Offer "Citation Impact" as a navigation menu item type.
     */
    public function addMenuItemTypes(string $hookName, array $args): bool
    {
        $types = &$args[0];
        $types[self::NMI_TYPE_CITATION_IMPACT] = [
            'title' => __('plugins.generic.citationImpact.menuItem.title'),
            'description' => __('plugins.generic.citationImpact.menuItem.description'),
        ];
        return false;
    }

    public function setMenuItemDisplayDetails(string $hookName, array $args): bool
    {
        $navigationMenuItem = $args[0];
        if ($navigationMenuItem->getType() !== self::NMI_TYPE_CITATION_IMPACT) {
            return false;
        }
        $request = Application::get()->getRequest();
        $context = $request->getContext();
        if (!$context) {
            $navigationMenuItem->setIsDisplayed(false);
            return false;
        }
        $navigationMenuItem->setUrl($request->getDispatcher()->url(
            $request,
            Application::ROUTE_PAGE,
            null,
            $this->getPagePath($context->getId())
        ));
        return false;
    }

    /**
     * Citation badge on the article page (default theme: the details column).
     * Shown once the article has at least one citation; read from the
     * stored data, so it costs no API call.
     *
     * @hook Templates::Article::Details [[&$params, $templateMgr, &$output]]
     */
    public function articleBadge(string $hookName, array $args): bool
    {
        try {
            $templateMgr = $args[1];
            $output = &$args[2];
            $request = Application::get()->getRequest();
            $context = $request->getContext();
            $publication = $templateMgr->getTemplateVars('publication');
            if (!$context || !$publication) {
                return false;
            }
            $settings = CitationImpactService::getSettings($context->getId());
            $doi = CitationImpactService::normalizeDoi((string) $publication->getDoi());
            if (!$settings['articleBadge'] || $doi === null) {
                return false;
            }
            $service = new CitationImpactService($context->getId(), $settings);
            $m = $service->merge($service->item($doi));
            $separate = $service->isSeparate() && count($service->sources()) > 1;
            // Separate mode: also show the badge when only OpenAlex has found a citation.
            if (!($m['ok'] && $m['c']) && !($separate && $m['co'])) {
                return false;
            }
            $links = [];
            if ($m['cd'] !== null) {
                $links[] = ['name' => 'Dimensions', 'count' => $m['cd'], 'url' => 'https://badge.dimensions.ai/details/doi/' . $doi];
            }
            if ($m['co'] !== null && $m['oid']) {
                $links[] = ['name' => 'OpenAlex', 'count' => $m['co'], 'url' => 'https://openalex.org/' . ($m['co'] ? 'works?filter=cites:' : '') . $m['oid']];
            }
            $templateMgr->assign([
                'ciBadge' => [
                    'count' => (int) $m['c'],
                    'recent' => $m['r'],
                    'fcr' => $m['f'],
                    'links' => $links,
                    'merged' => !$separate && count($service->sources()) > 1,
                    'separate' => $separate,
                    'pageUrl' => $request->getDispatcher()->url($request, Application::ROUTE_PAGE, null, $this->getPagePath($context->getId())),
                ],
            ]);
            $output .= $templateMgr->fetch($this->getTemplateResource('articleBadge.tpl'));
        } catch (Throwable $e) {
            error_log('Citation Impact: ' . $e->getMessage());
        }
        return false;
    }

    //
    // Settings
    //

    /**
     * @copydoc Plugin::getActions()
     */
    public function getActions($request, $actionArgs)
    {
        $actions = parent::getActions($request, $actionArgs);
        if (!$this->getEnabled()) {
            return $actions;
        }
        $router = $request->getRouter();
        array_unshift($actions, new LinkAction(
            'settings',
            new AjaxModal(
                $router->url($request, null, null, 'manage', null, ['verb' => 'settings', 'plugin' => $this->getName(), 'category' => 'generic']),
                $this->getDisplayName()
            ),
            __('manager.plugins.settings'),
            null
        ));
        return $actions;
    }

    /**
     * @copydoc Plugin::manage()
     */
    public function manage($args, $request)
    {
        $context = $request->getContext();
        if (!$context) {
            return parent::manage($args, $request);
        }
        switch ($request->getUserVar('verb')) {
            case 'settings':
                $form = new CitationImpactSettingsForm($this, $context->getId());
                if ($request->getUserVar('save')) {
                    $form->readInputData();
                    if ($form->validate()) {
                        $form->execute();
                        return new JSONMessage(true);
                    }
                } else {
                    $form->initData();
                }
                return new JSONMessage(true, $form->fetch($request));

            case 'refresh':
                // One batch of the "refresh now" loop in the settings modal.
                if (!$request->isPost() || !$request->checkCSRF()) {
                    return new JSONMessage(false);
                }
                @set_time_limit(90);
                ignore_user_abort(true);
                try {
                    $service = new CitationImpactService($context->getId());
                    $result = $service->refresh(1000, microtime(true) + 20, (bool) $request->getUserVar('force'));
                } catch (Throwable $e) {
                    error_log('Citation Impact: ' . $e->getMessage());
                    $result = ['status' => 'error', 'reason' => 'exception', 'message' => $e->getMessage()];
                }
                return new JSONMessage(true, $result);
        }
        return parent::manage($args, $request);
    }
}
