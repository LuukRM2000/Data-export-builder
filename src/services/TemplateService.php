<?php

declare(strict_types=1);

namespace Luremo\DataExportBuilder\services;

use Craft;
use craft\base\Component;
use craft\helpers\ArrayHelper;
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
        $template->name = trim((string)($payload['name'] ?? ''));
        $template->handle = $this->generateHandle((string)($payload['handle'] ?? $template->name));
        $template->elementType = (string)($payload['elementType'] ?? 'entries');
        $template->format = (string)($payload['format'] ?? 'csv');
        $template->filters = [
            'sectionUid' => $payload['filters']['sectionUid'] ?? null,
            'siteUid' => $payload['filters']['siteUid'] ?? null,
            'dateFrom' => $payload['filters']['dateFrom'] ?? null,
            'dateTo' => $payload['filters']['dateTo'] ?? null,
        ];
        $template->settings = [
            'queueThreshold' => (int)($payload['settings']['queueThreshold'] ?? 1000),
        ];
        $template->fields = $this->hydrateFieldsFromRequest($payload['fields'] ?? []);

        return $template;
    }

    public function touchLastRun(int $templateId, string $timestamp): void
    {
        ExportTemplateRecord::updateAll(['lastRunAt' => $timestamp], ['id' => $templateId]);
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

            $models[] = new ExportField([
                'fieldPath' => $fieldPath,
                'columnLabel' => trim((string)($field['columnLabel'] ?? ArrayHelper::last(explode('.', $fieldPath)) ?: $fieldPath)),
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
            'startedAt' => $record->startedAt,
            'finishedAt' => $record->finishedAt,
            'triggeredByUserId' => $record->triggeredByUserId !== null ? (int)$record->triggeredByUserId : null,
            'errorMessage' => $record->errorMessage,
            'dateCreated' => $record->dateCreated,
            'dateUpdated' => $record->dateUpdated,
        ]);
    }

    private function generateHandle(string $value): string
    {
        $handle = preg_replace('/[^a-zA-Z0-9_\\-]+/', '-', strtolower(trim($value))) ?: 'export-template';

        return trim($handle, '-');
    }
}
