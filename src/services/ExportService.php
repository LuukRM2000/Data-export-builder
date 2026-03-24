<?php

declare(strict_types=1);

namespace Luremo\DataExportBuilder\services;

use Craft;
use craft\base\Component;
use craft\base\ElementInterface;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\Db;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use Luremo\DataExportBuilder\helpers\CapabilityHelper;
use Luremo\DataExportBuilder\helpers\ExportFileHelper;
use Luremo\DataExportBuilder\helpers\FieldValueHelper;
use Luremo\DataExportBuilder\jobs\RunExportJob;
use Luremo\DataExportBuilder\models\ExportField;
use Luremo\DataExportBuilder\models\ExportRun;
use Luremo\DataExportBuilder\models\ExportTemplate;
use Luremo\DataExportBuilder\Plugin;
use Luremo\DataExportBuilder\records\ExportRunRecord;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use verbb\formie\elements\Form as FormieForm;
use verbb\formie\elements\Submission as FormieSubmission;
use wheelform\db\Form as WheelformForm;
use wheelform\db\Message as WheelformMessage;
use yii\base\Exception;

final class ExportService extends Component
{
    private int $defaultQueueThreshold = 1000;
    private int $batchSize = 200;

    public function runTemplate(ExportTemplate $template, ?int $userId, bool $forceQueue = false): ExportRun
    {
        $query = $this->buildSourceQuery($template);
        $estimatedCount = $this->estimateRowCount($query);
        $run = $this->createRunRecord($template, $userId);

        if ($forceQueue || $this->shouldQueueForCount($template, $estimatedCount)) {
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
            $this->assertEditionRuntimeAccess($template);

            $runRecord->status = ExportRun::STATUS_RUNNING;
            $runRecord->startedAt = Db::prepareDateForDb(new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
            $runRecord->errorMessage = null;
            $runRecord->save(false);

            $query = $this->buildSourceQuery($template);
            $total = $this->estimateRowCount($query);
            if (!in_array($template->elementType, [
                CapabilityHelper::ELEMENT_TYPE_WHEELFORM_SUBMISSIONS,
                CapabilityHelper::ELEMENT_TYPE_FORMIE_SUBMISSIONS,
            ], true)) {
                $eagerLoadPaths = Plugin::$plugin->get('fieldDiscovery')->getEagerLoadPaths(
                    array_map(static fn(ExportField $field): string => $field->fieldPath, $template->getFieldsSorted())
                );

                if ($eagerLoadPaths !== [] && method_exists($query, 'with')) {
                    $query->with($eagerLoadPaths);
                }
            }

            $filePath = ExportFileHelper::buildFilePath($template, new ExportRun(['id' => (int)$runRecord->id, 'format' => $template->format, 'templateId' => $template->id ?? 0]));
            $rowCount = match ($template->format) {
                'json' => $this->streamJsonExport($query, $template, $filePath, $total, $progressCallback),
                'xlsx' => $this->streamXlsxExport($query, $template, $filePath, $total, $progressCallback),
                default => $this->streamCsvExport($query, $template, $filePath, $total, $progressCallback),
            };

            $runRecord->status = ExportRun::STATUS_COMPLETED;
            $runRecord->rowCount = $rowCount;
            $runRecord->filePath = $filePath;
            $runRecord->fileName = basename($filePath);
            $runRecord->fileMimeType = ExportFileHelper::fileMimeType($template->format);
            $deliveryResult = Plugin::$plugin->get('deliveries')->deliverRun($template, new ExportRun([
                'id' => (int)$runRecord->id,
                'templateId' => $template->id ?? 0,
                'status' => ExportRun::STATUS_COMPLETED,
                'format' => $template->format,
                'rowCount' => $rowCount,
                'filePath' => $filePath,
                'fileName' => basename($filePath),
                'fileMimeType' => ExportFileHelper::fileMimeType($template->format),
            ]));
            $runRecord->storageType = $deliveryResult['storageType'];
            if (!$deliveryResult['keepLocalCopy'] && is_file($filePath)) {
                unlink($filePath);
                $runRecord->filePath = null;
            }
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

    private function assertEditionRuntimeAccess(ExportTemplate $template): void
    {
        if (!CapabilityHelper::supportsElementTypeHandle($template->elementType)) {
            throw new Exception('This export type requires the Pro edition.');
        }

        if (!CapabilityHelper::supportsFormat($template->format)) {
            throw new Exception('This export format requires the Pro edition.');
        }

        if ((int)($template->settings['queueThreshold'] ?? $this->defaultQueueThreshold) !== $this->defaultQueueThreshold
            && !CapabilityHelper::hasFeature(CapabilityHelper::FEATURE_ADVANCED_QUEUE)
        ) {
            throw new Exception('Custom queue thresholds require the Pro edition.');
        }
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

    public function buildSourceQuery(ExportTemplate $template): mixed
    {
        if ($template->elementType === CapabilityHelper::ELEMENT_TYPE_WHEELFORM_SUBMISSIONS) {
            return $this->buildWheelformMessageQuery($template);
        }

        if ($template->elementType === CapabilityHelper::ELEMENT_TYPE_FORMIE_SUBMISSIONS) {
            return $this->buildFormieSubmissionQuery($template);
        }

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
                $section = Craft::$app->getEntries()->getSectionByUid($sectionUid);
                if ($section !== null) {
                    $query->section($section->handle);
                }
            }
        }

        if ($template->elementType === CapabilityHelper::ELEMENT_TYPE_PRODUCTS && method_exists($query, 'type')) {
            $productTypeHandle = (string)($template->filters['productTypeHandle'] ?? '');
            if ($productTypeHandle !== '') {
                $query->type($productTypeHandle);
            }
        }

        $dateFrom = $this->normalizeDateFilter($template->filters['dateFrom'] ?? null);
        $dateTo = $this->normalizeDateFilter($template->filters['dateTo'] ?? null);
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

    private function createRunRecord(ExportTemplate $template, ?int $userId): ExportRun
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

    private function estimateRowCount(mixed $query): int
    {
        return (int)(clone $query)->count();
    }

    private function streamCsvExport(
        mixed $query,
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
        mixed $query,
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

    private function streamXlsxExport(
        mixed $query,
        ExportTemplate $template,
        string $filePath,
        int $total,
        ?callable $progressCallback = null
    ): int {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $fields = $template->getFieldsSorted();
        $columnWidths = [];

        foreach (array_values($fields) as $index => $field) {
            $sheet->setCellValue($this->xlsxCellAddress($index + 1, 1), $field->columnLabel);
            $columnWidths[$index] = $this->estimateColumnWidth($field->columnLabel);
        }

        $processed = 0;
        $rowNumber = 2;

        foreach ($query->batch($this->batchSize) as $elements) {
            foreach ($elements as $element) {
                $row = $this->buildRow($element, $fields, 'json');

                foreach (array_values($row) as $index => $value) {
                    if (is_array($value)) {
                        $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
                    }

                    $stringValue = (string)($value ?? '');
                    $sheet->setCellValue($this->xlsxCellAddress($index + 1, $rowNumber), $stringValue);
                    $columnWidths[$index] = max($columnWidths[$index] ?? 0, $this->estimateColumnWidth($stringValue));
                }

                $processed++;
                $rowNumber++;
            }

            if ($progressCallback !== null) {
                $progressCallback($processed, $total);
            }
        }

        foreach (array_values($fields) as $index => $field) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($index + 1))
                ->setWidth((float)min(max($columnWidths[$index] ?? 12, 12), 60));
        }

        $visibleColumnCount = count($fields);
        for ($columnIndex = $visibleColumnCount + 1; $columnIndex <= 16384; $columnIndex++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($columnIndex))->setVisible(false);
        }

        (new Xlsx($spreadsheet))->save($filePath);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

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

    private function xlsxCellAddress(int $columnIndex, int $rowNumber): string
    {
        return Coordinate::stringFromColumnIndex($columnIndex) . $rowNumber;
    }

    private function estimateColumnWidth(string $value): int
    {
        $length = function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);

        return $length + 2;
    }

    private function normalizeDateFilter(mixed $value): ?string
    {
        if (is_string($value)) {
            return $this->normalizeDateString($value);
        }

        if (!is_array($value)) {
            return null;
        }

        $year = trim((string)($value['year'] ?? ''));
        $month = trim((string)($value['month'] ?? ''));
        $day = trim((string)($value['day'] ?? ''));

        if ($year !== '' && $month !== '' && $day !== '') {
            return sprintf('%04d-%02d-%02d', (int)$year, (int)$month, (int)$day);
        }

        foreach ($this->flattenScalarValues($value) as $candidate) {
            $normalized = $this->normalizeDateString($candidate);
            if ($normalized !== null) {
                return $normalized;
            }
        }

        return null;
    }

    private function normalizeDateString(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
            return $value;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}(:\d{2})?$/', $value) === 1) {
            return substr($value, 0, 10);
        }

        $timestamp = strtotime($value);

        return $timestamp !== false ? date('Y-m-d', $timestamp) : null;
    }

    /**
     * @return string[]
     */
    private function flattenScalarValues(array $value): array
    {
        $results = [];

        array_walk_recursive($value, static function (mixed $item) use (&$results): void {
            if (is_scalar($item) || $item instanceof \Stringable) {
                $results[] = (string)$item;
            }
        });

        return $results;
    }

    private function buildWheelformMessageQuery(ExportTemplate $template): mixed
    {
        if (!CapabilityHelper::isWheelFormInstalled()) {
            throw new Exception('Wheel Form is not installed.');
        }

        $formId = (int)($template->filters['formId'] ?? 0);
        if ($formId <= 0) {
            throw new Exception('Select a Wheel Form before running this export.');
        }

        $form = WheelformForm::findOne($formId);
        if ($form === null) {
            throw new Exception(sprintf('Wheel Form form %d could not be found.', $formId));
        }

        $query = WheelformMessage::find()
            ->where(['form_id' => $formId])
            ->with(['value.field', 'form'])
            ->orderBy(['dateCreated' => SORT_DESC, 'id' => SORT_DESC]);

        $dateFrom = $this->normalizeDateFilter($template->filters['dateFrom'] ?? null);
        if ($dateFrom !== null) {
            $query->andWhere(['>=', 'dateCreated', $dateFrom . ' 00:00:00']);
        }

        $dateTo = $this->normalizeDateFilter($template->filters['dateTo'] ?? null);
        if ($dateTo !== null) {
            $query->andWhere(['<=', 'dateCreated', $dateTo . ' 23:59:59']);
        }

        return $query;
    }

    private function buildFormieSubmissionQuery(ExportTemplate $template): mixed
    {
        if (!CapabilityHelper::isFormieInstalled()) {
            throw new Exception('Formie is not installed.');
        }

        $formId = (int)($template->filters['formId'] ?? 0);
        if ($formId <= 0) {
            throw new Exception('Select a Formie form before running this export.');
        }

        $form = FormieForm::find()->status(null)->id($formId)->one();
        if ($form === null) {
            throw new Exception(sprintf('Formie form %d could not be found.', $formId));
        }

        $query = FormieSubmission::find()
            ->status(null)
            ->formId($formId)
            ->orderBy(['elements.dateCreated' => SORT_DESC, 'elements.id' => SORT_DESC]);

        $dateFrom = $this->normalizeDateFilter($template->filters['dateFrom'] ?? null);
        $dateTo = $this->normalizeDateFilter($template->filters['dateTo'] ?? null);
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
}
