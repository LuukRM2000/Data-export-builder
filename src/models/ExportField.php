<?php

declare(strict_types=1);

namespace Luremo\DataExportBuilder\models;

use craft\base\Model;

final class ExportField extends Model
{
    public ?int $id = null;
    public ?string $uid = null;
    public ?int $templateId = null;
    public string $fieldPath = '';
    public string $columnLabel = '';
    public int $sortOrder = 1;
    public array $settings = [];

    protected function defineRules(): array
    {
        return [
            [['fieldPath', 'columnLabel'], 'required'],
            [['fieldPath', 'columnLabel'], 'string', 'max' => 255],
            [['sortOrder', 'templateId'], 'integer'],
            ['settings', 'safe'],
        ];
    }

    public function toArray(array $fields = [], array $expand = [], bool $recursive = true): array
    {
        return [
            'id' => $this->id,
            'uid' => $this->uid,
            'templateId' => $this->templateId,
            'fieldPath' => $this->fieldPath,
            'columnLabel' => $this->columnLabel,
            'sortOrder' => $this->sortOrder,
            'settings' => $this->settings,
        ];
    }
}
