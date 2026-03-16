<?php

declare(strict_types=1);

namespace Luremo\DataExportBuilder\jobs;

use craft\queue\BaseJob;
use Luremo\DataExportBuilder\Plugin;

final class RunExportJob extends BaseJob
{
    public int $runId;

    public function execute($queue): void
    {
        Plugin::$plugin->get('exports')->performRun(
            $this->runId,
            function (int $processed, int $total) use ($queue): void {
                $progress = $total > 0 ? min(1, $processed / $total) : 1;
                $this->setProgress($queue, $progress, sprintf('Exported %d of %d rows', $processed, $total));
            }
        );
    }

    protected function defaultDescription(): ?string
    {
        return 'Running Data Export Builder export';
    }
}
