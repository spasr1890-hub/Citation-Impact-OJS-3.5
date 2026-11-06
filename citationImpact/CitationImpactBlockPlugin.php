<?php

/**
 * @file plugins/generic/citationImpact/CitationImpactBlockPlugin.php
 *
 * Distributed under the GNU GPL v3.
 *
 * @class CitationImpactBlockPlugin
 * @brief Sidebar block: citations in the last 3 years, total and h-index,
 *  linking to the Citation Impact page. Reads the summary saved by the last
 *  refresh, so it adds no API call and almost no database work to a page.
 *  Add it under Settings > Website > Appearance > Setup > Sidebar.
 */

namespace APP\plugins\generic\citationImpact;

use APP\core\Application;
use APP\plugins\generic\citationImpact\classes\CitationImpactService;
use PKP\plugins\BlockPlugin;
use Throwable;

class CitationImpactBlockPlugin extends BlockPlugin
{
    public function __construct(protected CitationImpactPlugin $parentPlugin)
    {
        parent::__construct();
    }

    public function getName(): string
    {
        return substr(static::class, strlen(__NAMESPACE__) + 1);
    }

    public function getHideManagement(): bool
    {
        return true;
    }

    public function getDisplayName(): string
    {
        return __('plugins.generic.citationImpact.block.name');
    }

    public function getDescription(): string
    {
        return __('plugins.generic.citationImpact.block.description');
    }

    public function getPluginPath(): string
    {
        return $this->parentPlugin->getPluginPath();
    }

    /**
     * The parent plugin's template folder. OJS registers a Smarty resource per
     * plugin path, and this block shares the parent's path: a different folder
     * here would replace the parent's resource and break all its templates.
     */
    public function getTemplatePath($inCore = false)
    {
        return $this->parentPlugin->getTemplatePath($inCore);
    }

    public function getBlockTemplateFilename(): string
    {
        return 'block.tpl';
    }

    /**
     * @copydoc BlockPlugin::getContents()
     */
    public function getContents($templateMgr, $request = null)
    {
        $request = $request ?: Application::get()->getRequest();
        $context = $request->getContext();
        if (!$context) {
            return '';
        }
        try {
            $service = new CitationImpactService($context->getId());
            $s = $service->summary();
        } catch (Throwable $e) {
            error_log('Citation Impact: ' . $e->getMessage());
            return '';
        }
        if (empty($s['checked'])) {
            return '';
        }
        $fmt = function ($n) {
            $locale = \PKP\facades\Locale::getLocale();
            return strpos($locale, 'id') === 0 ? number_format((float) $n, 0, ',', '.') : number_format((float) $n, 0, '.', ',');
        };
        $templateMgr->assign('ciBlock', [
            'window' => $fmt($s['windowCitations']),
            'windowFrom' => (int) $s['windowFrom'],
            'windowTo' => (int) $s['windowTo'],
            'total' => $fmt($s['totalCitations']),
            'hIndex' => (int) $s['hIndex'],
            'cited' => $fmt($s['citedArticles']),
            'articles' => $fmt($s['articles']),
            'source' => $service->isSeparate() || $service->sources() === ['dimensions'] ? 'Dimensions'
                : CitationImpactHandler::sourcesLabel($service->sources(), false),
            'url' => $request->getDispatcher()->url($request, Application::ROUTE_PAGE, null, $this->parentPlugin->getPagePath($context->getId())),
        ]);
        return parent::getContents($templateMgr, $request);
    }
}
