<?php

declare(strict_types=1);

namespace Luremo\DataExportBuilder\services;

use Craft;
use craft\base\Component;
use craft\base\ElementInterface;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\Db;
use Luremo\DataExportBuilder\helpers\CapabilityHelper;
use Luremo\DataExportBuilder\helpers\ExportFileHelper;
use Luremo\DataExportBuilder\helpers\FieldValueHelper;
use Luremo\DataExportBuilder\jobs\RunExportJob;
use Luremo\DataExportBuilder\models\ExportField;
use Luremo\DataExportBuilder\models\ExportRun;
use Luremo\DataExportBuilder\models\ExportTemplate;
use Luremo\DataExportBuilder\Plugin;
use Luremo\DataExportBuilder\records\ExportRunRecord;
use yii\base\Exception;

final class ExportService extends Component
{
    private int $defaultQueueThreshold = 1000;
    private int $batchSize = 200;

    public function runTemplate(ExportTemplate $template, int $userId): ExportRun
    {
        $query = $this->buildElementQuery($template);
        $estimatedCount = $this->estimateRowCount($query);
        $run = $this->createRunRecord($template, $userId);

        if ($this->shouldQueueForCount($template, $estimatedCount)) {
            Craft::$app->getQueue()->push(new RunExportJob(['runId' => $run->id]));

            return $run;
        }

        return $this->performRun((int)$run->id);
    }

    public function performRun(int $runId, ?callable $progressCallback = null): ExportRun
    {
        $runRecord = ExportRunRecord::findOne($runId);
        if ($runRecord === null) {
            throw new Exception(sprintf('Export run %d could not be found.', $runId));
        }

        $template = Plugin::$plugin->get('templates')->getTemplateById((int)$runRecord->templateId);
        if ($template === null) {
            throw new Exception(sprintf('Template %d could not be found.', (int)$runRecord->templateId));
        }

        try {
            $runRecord->status = ExportRun::STATUS_RUNNING;
            $runRecord->startedAt = Db::prepareDateForDb(new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
            $runRecord->errorMessage = null;
            $runRecord->save(false);

            $query = $this->buildElementQuery($template);
            $total = $this->estimateRowCount($query);
            $eagerLoadPaths = Plugin::$plugin->get('fieldDiscovery')->getEagerLoadPaths(
                array_map(static fn(ExportField $field): string => $field->fieldPath, $template->getFieldsSorted())
            );

            if ($eagerLoadPaths !== [] && method_exists($query, 'with')) {
                $query->with($eagerLoadPaths);
            }

            $filePath = ExportFileHelper::buildFilePath($template, new ExportRun(['id' => (int)$runRecord->id, 'format' => $template->format, 'templateId' => $template->id ?? 0]));
            $rowCount = $template->format === 'json'
                ? $this->streamJsonExport($query, $template, $filePath, $total, $progressCallback)
                : $this->streamCsvExport($query, $template, $filePath, $total, $progressCallback);

            $runRecord->status = ExportRun::STATUS_COMPLETED;
            $runRecord->rowCount = $rowCount;
            $runRecord->filePath = $filePath;
            $runRecord->fileName = basename($filePath);
            $runRecord->fileMimeType = ExportFileHelper::fileMimeType($template->format);
            $runRecord->finishedAt = Db::prepareDateForDb(new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
            $runRecord->save(false);

            Plugin::$plugin->get('templates')->touchLastRun($template->id ?? 0, (string)$runRecord->finishedAt);
        } catch (\Throwable $exception) {
            Craft::error($exception->getMessage(), 'data-export-builder');

            $runRecord->status = ExportRun::STATUS_FAILED;
            $runRecord->errorMessage = $exception->getMessage();
            $runRecord->finishedAt = Db::prepareDateForDb(new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
            $runRecord->save(false);
        }

        return Plugin::$plugin->get('templates')->getRunById((int)$runRecord->id)
            ?? throw new Exception('Unable to reload export run.');
    }

    public function shouldQueueForCount(ExportTemplate $template, int $count): bool
    {
        $threshold = (int)($template->settings['queueThreshold'] ?? $this->defaultQueueThreshold);

        return $count > $threshold;
    }

    public function buildCsvContent(array $headers, array $rows): string
    {
        $handle = fopen('php://temp', 'rb+');
        fputcsv($handle, $headers);

        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        rewind($handle);
        $content = stream_get_contents($handle) ?: '';
        fclose($handle);

        return $content;
    }

    public function buildJsonContent(array $rows): string
    {
        return json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
    }

    public function buildElementQuery(ExportTemplate $template): ElementQueryInterface
    {
        $supported = CapabilityHelper::supportedElementTypes();
        $elementClass = $supported[$template->elementType]['class'] ?? null;

        if ($elementClass === null || !is_subclass_of($elementClass, ElementInterface::class)) {
            throw new Exception(sprintf('Unsupported element type "%s".', $template->elementType));
        }

        /** @var class-string<ElementInterface> $elementClass */
        $query = $elementClass::find();

        if (method_exists($query, 'status')) {
            $query->status(null);
        }

        if (method_exists($query, 'site')) {
            $siteUid = (string)($template->filters['siteUid'] ?? '');
            $site = $siteUid !== '' ? Craft::$app->getSites()->getSiteByUid($siteUid) : null;
            $query->site($site?->handle ?? '*');
        }

        if ($template->elementType === 'entries' && method_exists($query, 'section')) {
            $sectionUid = (string)($template->filters['sectionUid'] ?? '');
            if ($sectionUid !== '') {
                $section = Craft::$app->getSections()->getSectionByUid($sectionUid);
                if ($section !== null) {
                    $query->section($section->handle);
                }
            }
        }

        $dateFrom = $template->filters['dateFrom'] ?? null;
        $dateTo = $template->filters['dateTo'] ?? null;
        if (($dateFrom || $dateTo) && method_exists($query, 'dateCreated')) {
            $range = [];
            if ($dateFrom) {
                $range[] = '>= ' . $dateFrom . ' 00:00:00';
            }
            if ($dateTo) {
                $range[] = '<= ' . $dateTo . ' 23:59:59';
            }
            $query->dateCreated($range);
        }

        return $query;
    }

    private function createRunRecord(ExportTemplate $template, int $userId): ExportRun
    {
        $record = new ExportRunRecord();
        $record->templateId = $template->id;
        $record->status = ExportRun::STATUS_QUEUED;
        $record->format = $template->format;
        $record->triggeredByUserId = $userId;
        $record->save(false);

        return Plugin::$plugin->get('templates')->getRunById((int)$record->id)
            ?? throw new Exception('Unable to create export run.');
    }

    private function estimateRowCount(ElementQueryInterface $query): int
    {
        return (int)(clone $query)->count();
    }

    private function streamCsvExport(
        ElementQueryInterface $query,
        ExportTemplate $template,
        string $filePath,
        int $total,
        ?callable $progressCallback = null
    ): int {
        $handle = fopen($filePath, 'wb');
        if ($handle === false) {
            throw new Exception(sprintf('Could not open export file "%s" for writing.', $filePath));
        }

        $fields = $template->getFieldsSorted();
        fputcsv($handle, array_map(static fn(ExportField $field): string => $field->columnLabel, $fields));

        $processed = 0;

        foreach ($query->batch($this->batchSize) as $elements) {
            foreach ($elements as $element) {
                fputcsv($handle, $this->buildRow($element, $fields, 'csv'));
                $processed++;
            }

            if ($progressCallback !== null) {
                $progressCallback($processed, $total);
            }
        }

        fclose($handle);

        return $processed;
    }

    private function streamJsonExport(
        ElementQueryInterface $query,
        ExportTemplate $template,
        string $filePath,
        int $total,
        ?callable $progressCallback = null
    ): int {
        $handle = fopen($filePath, 'wb');
        if ($handle === false) {
            throw new Exception(sprintf('Could not open export file "%s" for writing.', $filePath));
        }

        $fields = $template->getFieldsSorted();
        fwrite($handle, '[');

        $processed = 0;
        $isFirstRow = true;

        foreach ($query->batch($this->batchSize) as $elements) {
            foreach ($elements as $element) {
                $row = $this->buildAssocRow($element, $fields, 'json');
                fwrite($handle, ($isFirstRow ? '' : ',') . PHP_EOL . (json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}'));
                $isFirstRow = false;
                $processed++;
            }

            if ($progressCallback !== null) {
                $progressCallback($processed, $total);
            }
        }

        fwrite($handle, $processed > 0 ? PHP_EOL . ']' : ']');
        fclose($handle);

        return $processed;
    }

    /**
     * @param ExportField[] $fields
     * @return array<int, mixed>
     */
    private function buildRow(mixed $element, array $fields, string $format): array
    {
        return array_map(
            static fn(ExportField $field): mixed => FieldValueHelper::resolveFieldValue($element, $field->fieldPath, $format),
            $fields
        );
    }

    /**
     * @param ExportField[] $fields
     * @return array<string, mixed>
     */
    private function buildAssocRow(mixed $element, array $fields, string $format): array
    {
        $row = [];

        foreach ($fields as $field) {
            $row[$field->columnLabel] = FieldValueHelper::resolveFieldValue($element, $field->fieldPath, $format);
        }

        return $row;
    }
}
