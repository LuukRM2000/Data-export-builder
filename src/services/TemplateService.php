<?php

declare(strict_types=1);

namespace Luremo\DataExportBuilder\services;

use Craft;
use craft\base\Component;
use DateTimeInterface;
use Luremo\DataExportBuilder\models\ExportField;
use Luremo\DataExportBuilder\models\ExportRun;
use Luremo\DataExportBuilder\models\ExportTemplate;
use Luremo\DataExportBuilder\records\ExportFieldRecord;
use Luremo\DataExportBuilder\records\ExportRunRecord;
use Luremo\DataExportBuilder\records\ExportTemplateRecord;
use yii\base\Exception;

final class TemplateService extends Component
{
    /**
     * @return ExportTemplate[]
     */
    public function getAllTemplates(): array
    {
        $records = ExportTemplateRecord::find()
            ->orderBy(['dateUpdated' => SORT_DESC])
            ->all();

        return array_map(fn(ExportTemplateRecord $record): ExportTemplate => $this->buildTemplateModel($record, includeFields: false), $records);
    }

    public function getTemplateById(int $templateId): ?ExportTemplate
    {
        $record = ExportTemplateRecord::findOne($templateId);

        return $record ? $this->buildTemplateModel($record) : null;
    }

    /**
     * @return ExportRun[]
     */
    public function getRunsForTemplate(int $templateId, int $limit = 20): array
    {
        $records = ExportRunRecord::find()
            ->where(['templateId' => $templateId])
            ->orderBy(['dateCreated' => SORT_DESC])
            ->limit($limit)
            ->all();

        return array_map([$this, 'buildRunModel'], $records);
    }

    public function getRunById(int $runId): ?ExportRun
    {
        $record = ExportRunRecord::findOne($runId);

        return $record ? $this->buildRunModel($record) : null;
    }

    public function getFailedRunCount(): int
    {
        if (!Craft::$app->getDb()->tableExists('{{%dataexportbuilder_export_runs}}')) {
            return 0;
        }

        return (int)ExportRunRecord::find()->where(['status' => ExportRun::STATUS_FAILED])->count();
    }

    public function saveTemplate(ExportTemplate $template, bool $validate = true): bool
    {
        if ($validate && !$template->validate()) {
            return false;
        }

        if ($template->fields === []) {
            $template->addError('fields', 'Select at least one export field.');

            return false;
        }

        foreach ($template->fields as $field) {
            if (!$field->validate()) {
                $template->addErrors($field->getErrors());

                return false;
            }
        }

        $existing = ExportTemplateRecord::find()->where(['handle' => $template->handle])->one();
        if ($existing !== null && (int)$existing->id !== (int)$template->id) {
            $template->addError('handle', 'Handle must be unique.');

            return false;
        }

        $record = $template->id ? ExportTemplateRecord::findOne($template->id) : new ExportTemplateRecord();
        if ($record === null) {
            throw new Exception('Unable to load export template record.');
        }

        $record->name = $template->name;
        $record->handle = $template->handle;
        $record->elementType = $template->elementType;
        $record->format = $template->format;
        $record->filtersJson = $template->filters;
        $record->settingsJson = $template->settings;
        $record->creatorId = $template->creatorId;
        $record->lastRunAt = $template->lastRunAt;
        $record->save(false);

        $template->id = (int)$record->id;
        $template->uid = $record->uid;

        ExportFieldRecord::deleteAll(['templateId' => $template->id]);
        foreach ($template->getFieldsSorted() as $sortOrder => $field) {
            $fieldRecord = new ExportFieldRecord();
            $fieldRecord->templateId = $template->id;
            $fieldRecord->fieldPath = $field->fieldPath;
            $fieldRecord->columnLabel = $field->columnLabel;
            $fieldRecord->sortOrder = $sortOrder + 1;
            $fieldRecord->settingsJson = $field->settings;
            $fieldRecord->save(false);

            $field->id = (int)$fieldRecord->id;
            $field->templateId = $template->id;
            $field->sortOrder = $sortOrder + 1;
            $field->uid = $fieldRecord->uid;
        }

        return true;
    }

    public function deleteTemplate(int $templateId): bool
    {
        return (bool)ExportTemplateRecord::deleteAll(['id' => $templateId]);
    }

    public function duplicateTemplate(ExportTemplate $template, int $creatorId): ExportTemplate
    {
        $duplicate = new ExportTemplate([
            'name' => $template->name . ' Copy',
            'handle' => $this->generateHandle($template->handle . '-copy'),
            'elementType' => $template->elementType,
            'format' => $template->format,
            'filters' => $template->filters,
            'settings' => $template->settings,
            'creatorId' => $creatorId,
            'fields' => array_map(static fn(ExportField $field): ExportField => new ExportField([
                'fieldPath' => $field->fieldPath,
                'columnLabel' => $field->columnLabel,
                'sortOrder' => $field->sortOrder,
                'settings' => $field->settings,
            ]), $template->getFieldsSorted()),
        ]);

        $this->saveTemplate($duplicate);

        return $duplicate;
    }

    public function createTemplateFromRequest(array $payload, ?ExportTemplate $template = null): ExportTemplate
    {
        $template ??= new ExportTemplate();
        $existingSettings = $template->settings;
        $settingsPayload = is_array($payload['settings'] ?? null) ? $payload['settings'] : [];
        $schedulePayload = is_array($settingsPayload['schedule'] ?? null) ? $settingsPayload['schedule'] : [];
        $deliveryPayload = is_array($settingsPayload['delivery'] ?? null) ? $settingsPayload['delivery'] : [];
        $scheduleFrequency = in_array(($schedulePayload['frequency'] ?? 'daily'), ['hourly', 'daily', 'weekly'], true)
            ? (string)($schedulePayload['frequency'] ?? 'daily')
            : 'daily';
        $template->name = trim((string)($payload['name'] ?? ''));
        $requestedHandle = trim((string)($payload['handle'] ?? ''));
        $template->handle = $this->generateHandle($requestedHandle !== '' ? $requestedHandle : $template->name);
        $template->elementType = (string)($payload['elementType'] ?? 'entries');
        $template->format = (string)($payload['format'] ?? 'csv');
        $template->filters = [
            'sectionUid' => $payload['filters']['sectionUid'] ?? null,
            'siteUid' => $payload['filters']['siteUid'] ?? null,
            'formId' => $this->normalizeIntegerInput($payload['filters']['formId'] ?? null),
            'dateFrom' => $this->normalizeDateInput($payload['filters']['dateFrom'] ?? null),
            'dateTo' => $this->normalizeDateInput($payload['filters']['dateTo'] ?? null),
        ];
        $template->settings = [
            'queueThreshold' => (int)($settingsPayload['queueThreshold'] ?? 1000),
            'schedule' => [
                'enabled' => !empty($schedulePayload['enabled']),
                'frequency' => $scheduleFrequency,
                'hour' => max(0, min(23, (int)($schedulePayload['hour'] ?? 2))),
                'minute' => max(0, min(59, (int)($schedulePayload['minute'] ?? 0))),
                'weekdays' => $this->normalizeWeekdays($schedulePayload['weekdays'] ?? []),
                'lastScheduledAt' => $existingSettings['schedule']['lastScheduledAt'] ?? null,
            ],
            'delivery' => [
                'emailRecipients' => $this->normalizeStringList($deliveryPayload['emailRecipients'] ?? []),
                'emailSubject' => trim((string)($deliveryPayload['emailSubject'] ?? '')),
                'webhookUrl' => trim((string)($deliveryPayload['webhookUrl'] ?? '')),
                'webhookSecret' => trim((string)($deliveryPayload['webhookSecret'] ?? '')),
                'remoteVolumeUid' => trim((string)($deliveryPayload['remoteVolumeUid'] ?? '')),
                'remoteSubpath' => trim((string)($deliveryPayload['remoteSubpath'] ?? '')),
                'keepLocalCopy' => !array_key_exists('keepLocalCopy', $deliveryPayload) || (bool)$deliveryPayload['keepLocalCopy'],
            ],
        ];
        $template->fields = $this->hydrateFieldsFromRequest($payload['fields'] ?? []);

        return $template;
    }

    public function touchLastRun(int $templateId, string $timestamp): void
    {
        ExportTemplateRecord::updateAll(['lastRunAt' => $timestamp], ['id' => $templateId]);
    }

    public function updateTemplateSettings(int $templateId, array $settings): void
    {
        ExportTemplateRecord::updateAll(['settingsJson' => $settings], ['id' => $templateId]);
    }

    /**
     * @param array<int, array<string, mixed>> $fields
     * @return ExportField[]
     */
    private function hydrateFieldsFromRequest(array $fields): array
    {
        $models = [];

        foreach ($fields as $index => $field) {
            $fieldPath = trim((string)($field['fieldPath'] ?? ''));
            if ($fieldPath === '') {
                continue;
            }

            $pathSegments = explode('.', $fieldPath);
            $defaultColumnLabel = (string)(end($pathSegments) ?: $fieldPath);
            $columnLabel = trim((string)($field['columnLabel'] ?? ''));

            $models[] = new ExportField([
                'fieldPath' => $fieldPath,
                'columnLabel' => $columnLabel !== '' ? $columnLabel : $defaultColumnLabel,
                'sortOrder' => (int)($field['sortOrder'] ?? ($index + 1)),
                'settings' => is_array($field['settings'] ?? null) ? $field['settings'] : [],
            ]);
        }

        usort($models, static fn(ExportField $a, ExportField $b): int => $a->sortOrder <=> $b->sortOrder);

        return $models;
    }

    private function buildTemplateModel(ExportTemplateRecord $record, bool $includeFields = true): ExportTemplate
    {
        $template = new ExportTemplate([
            'id' => (int)$record->id,
            'uid' => $record->uid,
            'name' => $record->name,
            'handle' => $record->handle,
            'elementType' => $record->elementType,
            'format' => $record->format,
            'filters' => is_array($record->filtersJson) ? $record->filtersJson : [],
            'settings' => is_array($record->settingsJson) ? $record->settingsJson : [],
            'creatorId' => $record->creatorId !== null ? (int)$record->creatorId : null,
            'lastRunAt' => $record->lastRunAt,
        ]);

        if ($includeFields) {
            $template->fields = array_map(
                static fn(ExportFieldRecord $fieldRecord): ExportField => new ExportField([
                    'id' => (int)$fieldRecord->id,
                    'uid' => $fieldRecord->uid,
                    'templateId' => (int)$fieldRecord->templateId,
                    'fieldPath' => $fieldRecord->fieldPath,
                    'columnLabel' => $fieldRecord->columnLabel,
                    'sortOrder' => (int)$fieldRecord->sortOrder,
                    'settings' => is_array($fieldRecord->settingsJson) ? $fieldRecord->settingsJson : [],
                ]),
                ExportFieldRecord::find()
                    ->where(['templateId' => $record->id])
                    ->orderBy(['sortOrder' => SORT_ASC])
                    ->all()
            );
        }

        return $template;
    }

    private function buildRunModel(ExportRunRecord $record): ExportRun
    {
        return new ExportRun([
            'id' => (int)$record->id,
            'uid' => $record->uid,
            'templateId' => (int)$record->templateId,
            'status' => $record->status,
            'format' => $record->format,
            'rowCount' => $record->rowCount !== null ? (int)$record->rowCount : null,
            'filePath' => $record->filePath,
            'fileName' => $record->fileName,
            'fileMimeType' => $record->fileMimeType,
            'storageType' => $record->storageType,
            'startedAt' => $this->normalizeDateTimeValue($record->startedAt),
            'finishedAt' => $this->normalizeDateTimeValue($record->finishedAt),
            'triggeredByUserId' => $record->triggeredByUserId !== null ? (int)$record->triggeredByUserId : null,
            'errorMessage' => $record->errorMessage,
            'dateCreated' => $this->normalizeDateTimeValue($record->dateCreated),
            'dateUpdated' => $this->normalizeDateTimeValue($record->dateUpdated),
        ]);
    }

    private function generateHandle(string $value): string
    {
        $handle = preg_replace('/[^a-zA-Z0-9_\\-]+/', '-', strtolower(trim($value))) ?: 'export-template';

        return trim($handle, '-');
    }

    private function normalizeDateInput(mixed $value): ?string
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

    private function normalizeDateTimeValue(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_string($value)) {
            $value = trim($value);

            return $value !== '' ? $value : null;
        }

        return null;
    }

    private function normalizeIntegerInput(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (int)$value : null;
    }

    /**
     * @return string[]
     */
    private function normalizeStringList(mixed $value): array
    {
        $values = is_array($value)
            ? $value
            : (preg_split('/[\r\n,]+/', trim((string)$value)) ?: []);

        return array_values(array_filter(array_map(
            static fn(mixed $item): string => trim((string)$item),
            $values
        ), static fn(string $item): bool => $item !== ''));
    }

    /**
     * @return string[]
     */
    private function normalizeWeekdays(mixed $value): array
    {
        return array_values(array_filter(array_map(
            static fn(mixed $item): string => strtolower(trim((string)$item)),
            is_array($value) ? $value : []
        ), static fn(string $item): bool => in_array($item, ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'], true)));
    }
}
