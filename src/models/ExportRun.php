<?php

declare(strict_types=1);

namespace Luremo\DataExportBuilder\models;

use craft\base\Model;

final class ExportRun extends Model
{
    public const STATUS_QUEUED = 'queued';
    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    public ?int $id = null;
    public ?string $uid = null;
    public int $templateId;
    public string $status = self::STATUS_QUEUED;
    public string $format = 'csv';
    public ?int $rowCount = null;
    public ?string $filePath = null;
    public ?string $fileName = null;
    public ?string $fileMimeType = null;
    public string $storageType = 'local';
    public ?string $startedAt = null;
    public ?string $finishedAt = null;
    public ?int $triggeredByUserId = null;
    public ?string $errorMessage = null;
    public ?string $dateCreated = null;
    public ?string $dateUpdated = null;

    protected function defineRules(): array
    {
        return [
            [['templateId', 'status', 'format'], 'required'],
            [['templateId', 'rowCount', 'triggeredByUserId'], 'integer'],
            [['status'], 'in', 'range' => [
                self::STATUS_QUEUED,
                self::STATUS_RUNNING,
                self::STATUS_COMPLETED,
                self::STATUS_FAILED,
            ]],
            [['format'], 'in', 'range' => ['csv', 'json']],
            [['fileName'], 'string', 'max' => 255],
            [['fileMimeType'], 'string', 'max' => 100],
            [['filePath', 'errorMessage'], 'string'],
        ];
    }

    public function isDownloadable(): bool
    {
        return $this->status === self::STATUS_COMPLETED && $this->filePath !== null && $this->fileName !== null;
    }
}
