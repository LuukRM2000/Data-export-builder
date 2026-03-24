<?php

declare(strict_types=1);

namespace Luremo\DataExportBuilder\console\controllers;

use craft\console\Controller;
use Luremo\DataExportBuilder\Plugin;
use yii\console\ExitCode;

final class SchedulerController extends Controller
{
    public function actionRun(): int
    {
        $count = Plugin::$plugin->get('schedules')->enqueueDueScheduledTemplates();
        $this->stdout(sprintf("Queued %d scheduled export(s).\n", $count));

        return ExitCode::OK;
    }
}
