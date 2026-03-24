<?php

declare(strict_types=1);

namespace Luremo\DataExportBuilder\controllers;

use Craft;
use craft\web\Controller;
use Luremo\DataExportBuilder\Plugin;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

final class ExportController extends Controller
{
    protected array|bool|int $allowAnonymous = false;

    public function actionRun(int $templateId): Response
    {
        $this->requirePostRequest();
        $this->enforcePermission('runDataExports');

        $template = Plugin::$plugin->get('templates')->getTemplateById($templateId);
        if ($template === null) {
            throw new NotFoundHttpException('Export template not found.');
        }

        $run = Plugin::$plugin->get('exports')->runTemplate($template, (int)Craft::$app->getUser()->getId());
        $message = $run->status === 'queued'
            ? 'Export queued. Refresh the template page to monitor progress.'
            : 'Export completed and is ready to download.';
        Craft::$app->getSession()->setNotice($message);

        return $this->redirect('data-export-builder/exports/' . $templateId);
    }

    public function actionRetry(int $templateId, int $runId): Response
    {
        $this->requirePostRequest();
        $this->enforcePermission('runDataExports');

        $template = Plugin::$plugin->get('templates')->getTemplateById($templateId);
        $run = Plugin::$plugin->get('templates')->getRunById($runId);
        if ($template === null || $run === null || $run->templateId !== $templateId) {
            throw new NotFoundHttpException('Export run not found.');
        }

        Plugin::$plugin->get('exports')->runTemplate($template, (int)Craft::$app->getUser()->getId(), true);
        Craft::$app->getSession()->setNotice('Export retry queued.');

        return $this->redirect('data-export-builder/exports/' . $templateId);
    }

    public function actionDownload(int $templateId, int $runId): Response
    {
        $this->enforcePermission('downloadDataExports');

        $template = Plugin::$plugin->get('templates')->getTemplateById($templateId);
        $run = Plugin::$plugin->get('templates')->getRunById($runId);

        if ($template === null || $run === null || $run->templateId !== $templateId) {
            throw new NotFoundHttpException('Export run not found.');
        }

        $currentUser = Craft::$app->getUser()->getIdentity();
        $isAdmin = $currentUser?->admin ?? false;
        if (!$isAdmin && $run->triggeredByUserId !== (int)$currentUser?->id && $template->creatorId !== (int)$currentUser?->id) {
            throw new ForbiddenHttpException('You do not have permission to download this export.');
        }

        return Plugin::$plugin->get('downloads')->sendRunFile($run);
    }

    public function actionFields(): Response
    {
        $this->requireAcceptsJson();
        $this->enforcePermission('manageDataExports');

        $elementType = (string)Craft::$app->getRequest()->getRequiredQueryParam('elementType');
        $sectionUid = (string)Craft::$app->getRequest()->getQueryParam('sectionUid', '');
        $onlyPopulated = Craft::$app->getRequest()->getQueryParam('onlyPopulated') === '1';
        $formId = (int)Craft::$app->getRequest()->getQueryParam('formId');

        return $this->asJson(Plugin::$plugin->get('fieldDiscovery')->getDiscoveryPayload(
            $elementType,
            $sectionUid,
            $onlyPopulated,
            $formId > 0 ? $formId : null
        ));
    }

    private function enforcePermission(string $permission): void
    {
        if (!Craft::$app->getUser()->checkPermission($permission)) {
            throw new ForbiddenHttpException('You do not have permission to perform this action.');
        }
    }
}
