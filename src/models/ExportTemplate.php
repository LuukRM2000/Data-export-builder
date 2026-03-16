<?php

declare(strict_types=1);

namespace Luremo\DataExportBuilder\models;

use craft\base\Model;

final class ExportTemplate extends Model
{
    public ?int $id = null;
    public ?string $uid = null;
    public string $name = '';
    public string $handle = '';
    public string $elementType = 'entries';
    public string $format = 'csv';
    public array $filters = [];
    public array $settings = [];
    public ?int $creatorId = null;
    public ?string $lastRunAt = null;

    /** @var ExportField[] */
    public array $fields = [];

    protected function defineRules(): array
    {
        return [
            [['name', 'handle', 'elementType', 'format'], 'required'],
            [['name', 'handle'], 'string', 'max' => 255],
            [['elementType'], 'string', 'max' => 50],
            [['format'], 'in', 'range' => ['csv', 'json']],
            [['creatorId'], 'integer'],
            [['filters', 'settings', 'fields'], 'safe'],
            ['handle', 'match', 'pattern' => '/^[a-zA-Z][a-zA-Z0-9_\\-]*$/'],
        ];
    }

    public function getFieldsSorted(): array
    {
        $fields = $this->fields;
        usort($fields, static fn(ExportField $a, ExportField $b): int => $a->sortOrder <=> $b->sortOrder);

        return $fields;
    }
}
