<?php

declare(strict_types=1);

namespace Luremo\DataExportBuilder\services;

use Craft;
use craft\base\Component;
use craft\elements\Asset;
use craft\helpers\FileHelper;
use craft\models\VolumeFolder;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Luremo\DataExportBuilder\models\ExportRun;
use Luremo\DataExportBuilder\models\ExportTemplate;
use yii\base\Exception;

final class DeliveryService extends Component
{
    /**
     * @return array{emailRecipients:array<int,string>,emailSubject:string,webhookUrl:string,webhookSecret:string,remoteVolumeUid:string,remoteSubpath:string,keepLocalCopy:bool}
     */
    public function normalizeSettings(array $settings): array
    {
        $delivery = is_array($settings['delivery'] ?? null) ? $settings['delivery'] : [];

        return [
            'emailRecipients' => array_values(array_filter(array_map(
                static fn(mixed $value): string => trim((string)$value),
                is_array($delivery['emailRecipients'] ?? null) ? $delivery['emailRecipients'] : []
            ), static fn(string $value): bool => $value !== '')),
            'emailSubject' => trim((string)($delivery['emailSubject'] ?? '')),
            'webhookUrl' => trim((string)($delivery['webhookUrl'] ?? '')),
            'webhookSecret' => trim((string)($delivery['webhookSecret'] ?? '')),
            'remoteVolumeUid' => trim((string)($delivery['remoteVolumeUid'] ?? '')),
            'remoteSubpath' => trim((string)($delivery['remoteSubpath'] ?? '')),
            'keepLocalCopy' => (bool)($delivery['keepLocalCopy'] ?? true),
        ];
    }

    /**
     * @return array<int, array{label:string,value:string}>
     */
    public function getVolumeOptions(): array
    {
        $options = [['label' => 'Keep files local only', 'value' => '']];

        foreach (Craft::$app->getVolumes()->getAllVolumes() as $volume) {
            $options[] = [
                'label' => $volume->name,
                'value' => $volume->uid,
            ];
        }

        return $options;
    }

    /**
     * @return array{storageType:string,keepLocalCopy:bool}
     * @throws Exception
     */
    public function deliverRun(ExportTemplate $template, ExportRun $run): array
    {
        $settings = $this->normalizeSettings($template->settings);

        if ($run->filePath === null || !is_file($run->filePath)) {
            return ['storageType' => 'local', 'keepLocalCopy' => true];
        }

        $storageType = 'local';

        if ($settings['emailRecipients'] !== []) {
            $this->sendEmail($template, $run, $settings);
        }

        if ($settings['webhookUrl'] !== '') {
            $this->sendWebhook($template, $run, $settings);
        }

        if ($settings['remoteVolumeUid'] !== '') {
            $this->uploadToVolume($run, $settings['remoteVolumeUid'], $settings['remoteSubpath']);
            $storageType = 'volume';
        }

        return [
            'storageType' => $storageType,
            'keepLocalCopy' => $settings['keepLocalCopy'] || $storageType === 'local',
        ];
    }

    /**
     * @param array{emailRecipients:array<int,string>,emailSubject:string} $settings
     */
    private function sendEmail(ExportTemplate $template, ExportRun $run, array $settings): void
    {
        $subject = $settings['emailSubject'] !== ''
            ? $settings['emailSubject']
            : sprintf('Craft export ready: %s', $template->name);

        $message = Craft::$app->getMailer()
            ->compose()
            ->setTo($settings['emailRecipients'])
            ->setSubject($subject)
            ->setTextBody(sprintf(
                "Your export \"%s\" is ready.\n\nFormat: %s\nRows: %s\nFile: %s",
                $template->name,
                strtoupper($run->format),
                $run->rowCount ?? 'unknown',
                $run->fileName ?? 'export'
            ))
            ->attach($run->filePath);

        if (!$message->send()) {
            throw new Exception('Export email delivery failed.');
        }
    }

    /**
     * @param array{webhookUrl:string,webhookSecret:string} $settings
     * @throws Exception
     */
    private function sendWebhook(ExportTemplate $template, ExportRun $run, array $settings): void
    {
        try {
            (new Client(['timeout' => 20]))->post($settings['webhookUrl'], [
                'headers' => array_filter([
                    'X-Data-Export-Builder-Signature' => $settings['webhookSecret'] !== ''
                        ? hash_hmac('sha256', (string)$run->id, $settings['webhookSecret'])
                        : null,
                ]),
                'multipart' => [
                    [
                        'name' => 'payload',
                        'contents' => json_encode([
                            'templateId' => $template->id,
                            'templateName' => $template->name,
                            'runId' => $run->id,
                            'format' => $run->format,
                            'rowCount' => $run->rowCount,
                            'fileName' => $run->fileName,
                        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}',
                        'headers' => ['Content-Type' => 'application/json'],
                    ],
                    [
                        'name' => 'file',
                        'contents' => fopen($run->filePath, 'rb'),
                        'filename' => $run->fileName ?? basename($run->filePath),
                    ],
                ],
            ]);
        } catch (GuzzleException $exception) {
            throw new Exception('Export webhook delivery failed: ' . $exception->getMessage(), 0, $exception);
        }
    }

    /**
     * @throws Exception
     */
    private function uploadToVolume(ExportRun $run, string $volumeUid, string $subpath): void
    {
        $volume = Craft::$app->getVolumes()->getVolumeByUid($volumeUid);
        if ($volume === null) {
            throw new Exception(sprintf('Remote volume "%s" could not be found.', $volumeUid));
        }

        $folder = Craft::$app->getAssets()->getRootFolderByVolumeId((int)$volume->id);
        if ($folder === null) {
            throw new Exception(sprintf('Volume "%s" does not have a root folder.', $volume->name));
        }

        if ($subpath !== '') {
            $folder = $this->ensureFolderPath($folder, $subpath);
        }

        $tempPath = Craft::$app->getPath()->getTempPath() . DIRECTORY_SEPARATOR . ($run->fileName ?? basename($run->filePath));
        FileHelper::createDirectory(dirname($tempPath));
        if (!copy($run->filePath, $tempPath)) {
            throw new Exception(sprintf('Could not stage export file "%s" for remote upload.', $run->filePath));
        }

        try {
            $asset = new Asset();
            $asset->tempFilePath = $tempPath;
            $asset->newFolderId = $folder->id;
            $asset->newFilename = $run->fileName ?? basename($run->filePath);
            $asset->avoidFilenameConflicts = true;
            $asset->setScenario(Asset::SCENARIO_CREATE);

            if (!Craft::$app->getElements()->saveElement($asset)) {
                throw new Exception('Could not save export file to the selected asset volume.');
            }
        } finally {
            if (is_file($tempPath)) {
                FileHelper::unlink($tempPath);
            }
        }
    }

    /**
     * @throws Exception
     */
    private function ensureFolderPath(VolumeFolder $rootFolder, string $subpath): VolumeFolder
    {
        $assets = Craft::$app->getAssets();
        $folder = $rootFolder;

        foreach (array_values(array_filter(explode('/', str_replace('\\', '/', $subpath)))) as $segment) {
            $path = trim(($folder->path ? rtrim($folder->path, '/') . '/' : '') . $segment, '/') . '/';
            $existing = $assets->findFolder([
                'volumeId' => $folder->volumeId,
                'path' => $path,
            ]);

            if ($existing !== null) {
                $folder = $existing;
                continue;
            }

            $newFolder = new VolumeFolder([
                'parentId' => $folder->id,
                'volumeId' => $folder->volumeId,
                'name' => $segment,
                'path' => $path,
            ]);
            $assets->createFolder($newFolder);

            $folder = $assets->findFolder([
                'volumeId' => $rootFolder->volumeId,
                'path' => $path,
            ]) ?? throw new Exception(sprintf('Could not create export folder "%s".', $path));
        }

        return $folder;
    }
}
