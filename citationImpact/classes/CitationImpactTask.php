<?php

/**
 * @file plugins/generic/citationImpact/classes/CitationImpactTask.php
 *
 * Distributed under the GNU GPL v3.
 *
 * @class CitationImpactTask
 * @brief Hourly task: re-checks a batch of stale DOIs for every journal that
 *  has the plugin enabled. Each run is capped at about 25 seconds so it is
 *  safe on shared hosting (the OJS 3.5 web-based task runner runs it at the
 *  end of a page request).
 *
 *  Registered through CitationImpactPlugin::registerSchedules().
 */

namespace APP\plugins\generic\citationImpact\classes;

use Illuminate\Support\Facades\DB;
use PKP\scheduledTask\ScheduledTask;
use PKP\scheduledTask\ScheduledTaskHelper;
use Throwable;

class CitationImpactTask extends ScheduledTask
{
    /**
     * @copydoc ScheduledTask::getName()
     */
    public function getName(): string
    {
        return 'Citation Impact (Dimensions)';
    }

    /**
     * @copydoc ScheduledTask::executeActions()
     */
    protected function executeActions(): bool
    {
        $deadline = microtime(true) + 25;
        $contexts = DB::table('journals')
            ->where('enabled', '=', 1)
            ->orderBy('seq')
            ->select('journal_id', 'path')
            ->get();

        foreach ($contexts as $context) {
            if (microtime(true) >= $deadline) {
                break;
            }
            $contextId = (int) $context->journal_id;
            if (!CitationImpactService::isEnabledFor($contextId)) {
                continue;
            }
            try {
                $service = new CitationImpactService($contextId);
                $result = $service->refresh(500, $deadline);
                if (($result['status'] ?? '') === 'busy') {
                    $this->addExecutionLogEntry($context->path . ': busy', ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_NOTICE);
                    continue;
                }
                $this->addExecutionLogEntry(
                    sprintf(
                        '%s: %s, %d/%d checked, %d requested, %d errors',
                        $context->path,
                        $result['status'],
                        $result['checked'],
                        $result['articles'],
                        $result['processed'],
                        $result['errors']
                    ),
                    ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_NOTICE
                );
            } catch (Throwable $e) {
                $this->addExecutionLogEntry($context->path . ': ' . $e->getMessage(), ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_ERROR);
            }
        }
        return true;
    }
}
