<?php

declare(strict_types=1);

namespace Luremo\DataExportBuilder\helpers;

use Craft;
use craft\helpers\FileHelper;
use Luremo\DataExportBuilder\models\ExportRun;
use Luremo\DataExportBuilder\models\ExportTemplate;

final class ExportFileHelper
{
    public static function getBaseExportPath(): string
    {
        $path = Craft::$app->getPath()->getStoragePath() . DIRECTORY_SEPARATOR . 'data-export-builder' . DIRECTORY_SEPARATOR . 'exports';
        FileHelper::createDirectory($path);

        return $path;
    }

    public static function sanitizeFileName(string $value): string
    {
        $sanitized = preg_replace('/[^a-zA-Z0-9\\-_]+/', '-', strtolower(trim($value))) ?: 'export';

        return trim($sanitized, '-');
    }

    public static function buildFileName(ExportTemplate $template, ExportRun $run): string
    {
        $timestamp = gmdate('Ymd-His');
        $base = self::sanitizeFileName($template->handle ?: $template->name);

        return sprintf('%s-%s-%d.%s', $base, $timestamp, $run->id ?? 0, $template->format);
    }

    public static function buildFilePath(ExportTemplate $template, ExportRun $run): string
    {
        return self::getBaseExportPath() . DIRECTORY_SEPARATOR . self::buildFileName($template, $run);
    }

    public static function fileMimeType(string $format): string
    {
        return match ($format) {
            'json' => 'application/json',
            default => 'text/csv',
        };
    }

    public static function isInsideExportPath(string $path): bool
    {
        $realBase = realpath(self::getBaseExportPath());
        $realPath = realpath($path);

        return $realBase !== false && $realPath !== false && str_starts_with($realPath, $realBase);
    }
}
