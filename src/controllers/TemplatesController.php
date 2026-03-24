<?php

declare(strict_types=1);

namespace Luremo\DataExportBuilder\controllers;

use Craft;
use craft\web\Controller;
use Luremo\DataExportBuilder\helpers\CapabilityHelper;
use Luremo\DataExportBuilder\Plugin;
use Luremo\DataExportBuilder\web\assets\cp\CpAsset;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

final class TemplatesController extends Controller
{
    protected array|bool|int $allowAnonymous = false;

    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        if (!Craft::$app->getUser()->checkPermission('manageDataExports')) {
            throw new ForbiddenHttpException('You do not have permission to manage export templates.');
        }

        return true;
    }

    public function actionIndex(): Response
    {
        Craft::$app->getView()->registerAssetBundle(CpAsset::class);

        return $this->renderTemplate('data-export-builder/_cp/exports/index', [
            'templates' => Plugin::$plugin->get('templates')->getAllTemplates(),
            'isProEdition' => CapabilityHelper::isProEdition(),
        ]);
    }

    public function actionEdit(?int $templateId = null): Response
    {
        Craft::$app->getView()->registerAssetBundle(CpAsset::class);

        $template = $templateId
            ? Plugin::$plugin->get('templates')->getTemplateById($templateId)
            : Plugin::$plugin->get('templates')->createTemplateFromRequest([]);

        if ($template === null) {
            throw new NotFoundHttpException('Export template not found.');
        }

        $fieldPayload = Plugin::$plugin->get('fieldDiscovery')->getDiscoveryPayload(
            $template->elementType,
            (string)($template->filters['sectionUid'] ?? ''),
            false,
            isset($template->filters['formId']) ? (int)$template->filters['formId'] : null
        );

        return $this->renderTemplate('data-export-builder/_cp/exports/_edit', [
            'template' => $template,
            'fieldPayload' => $fieldPayload,
            'isProEdition' => CapabilityHelper::isProEdition(),
            'elementTypeOptions' => Plugin::$plugin->get('fieldDiscovery')->getElementTypeOptions(),
            'runs' => $template->id ? Plugin::$plugin->get('templates')->getRunsForTemplate($template->id) : [],
            'volumeOptions' => Plugin::$plugin->get('deliveries')->getVolumeOptions(),
            'nextScheduledRun' => Plugin::$plugin->get('schedules')->getNextRunDate($template),
        ]);
    }

    public function actionSave(): ?Response
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $templateId = $request->getBodyParam('templateId');
        $existing = $templateId ? Plugin::$plugin->get('templates')->getTemplateById((int)$templateId) : null;

        if ($templateId && $existing === null) {
            throw new NotFoundHttpException('Export template not found.');
        }

        $template = Plugin::$plugin->get('templates')->createTemplateFromRequest(
            $request->getBodyParams(),
            $existing
        );

        if ($template->creatorId === null) {
            $template->creatorId = (int)Craft::$app->getUser()->getId();
        }

        if (!Plugin::$plugin->get('templates')->saveTemplate($template)) {
            Craft::$app->getView()->registerAssetBundle(CpAsset::class);
            Craft::$app->getSession()->setError('Could not save export template.');

            return $this->renderTemplate('data-export-builder/_cp/exports/_edit', [
                'template' => $template,
                'fieldPayload' => Plugin::$plugin->get('fieldDiscovery')->getDiscoveryPayload(
                    $template->elementType,
                    (string)($template->filters['sectionUid'] ?? ''),
                    false,
                    isset($template->filters['formId']) ? (int)$template->filters['formId'] : null
                ),
                'isProEdition' => CapabilityHelper::isProEdition(),
                'elementTypeOptions' => Plugin::$plugin->get('fieldDiscovery')->getElementTypeOptions(),
                'runs' => $template->id ? Plugin::$plugin->get('templates')->getRunsForTemplate((int)$template->id) : [],
                'volumeOptions' => Plugin::$plugin->get('deliveries')->getVolumeOptions(),
                'nextScheduledRun' => Plugin::$plugin->get('schedules')->getNextRunDate($template),
            ]);
        }

        Craft::$app->getSession()->setNotice('Export template saved.');

        return $this->redirect('data-export-builder/exports/' . $template->id);
    }

    public function actionDuplicate(?int $templateId = null): Response
    {
        $this->requirePostRequest();

        $templateId ??= (int)Craft::$app->getRequest()->getRequiredBodyParam('templateId');

        $template = Plugin::$plugin->get('templates')->getTemplateById($templateId);
        if ($template === null) {
            throw new NotFoundHttpException('Export template not found.');
        }

        $duplicate = Plugin::$plugin->get('templates')->duplicateTemplate($template, (int)Craft::$app->getUser()->getId());
        Craft::$app->getSession()->setNotice('Export template duplicated.');

        return $this->redirect('data-export-builder/exports/' . $duplicate->id);
    }

    public function actionDelete(?int $templateId = null): Response
    {
        $this->requirePostRequest();

        $templateId ??= (int)Craft::$app->getRequest()->getRequiredBodyParam('templateId');

        Plugin::$plugin->get('templates')->deleteTemplate($templateId);
        Craft::$app->getSession()->setNotice('Export template deleted.');

        return $this->redirect('data-export-builder/exports');
    }
}
