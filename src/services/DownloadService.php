<?php

declare(strict_types=1);

namespace Luremo\DataExportBuilder\services;

use Craft;
use craft\base\Component;
use Luremo\DataExportBuilder\helpers\ExportFileHelper;
use Luremo\DataExportBuilder\models\ExportRun;
use yii\web\NotFoundHttpException;
use yii\web\Response;

final class DownloadService extends Component
{
    public function sendRunFile(ExportRun $run): Response
    {
        if (!$run->isDownloadable() || $run->filePath === null || !is_file($run->filePath)) {
            throw new NotFoundHttpException('The requested export file is no longer available.');
        }

        if (!ExportFileHelper::isInsideExportPath($run->filePath)) {
            throw new NotFoundHttpException('Invalid export file path.');
        }

        return Craft::$app->getResponse()->sendFile(
            $run->filePath,
            $run->fileName,
            [
                'mimeType' => $run->fileMimeType ?? ExportFileHelper::fileMimeType($run->format),
                'inline' => false,
            ]
        );
    }
}
