<?php

declare(strict_types=1);

namespace Luremo\DataExportBuilder;

use Craft;
use craft\base\Plugin as BasePlugin;
use craft\events\RegisterTemplateRootsEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\services\UserPermissions;
use Luremo\DataExportBuilder\services\DownloadService;
use Luremo\DataExportBuilder\services\ExportService;
use Luremo\DataExportBuilder\services\FieldDiscoveryService;
use Luremo\DataExportBuilder\services\TemplateService;
use yii\base\Event;

final class Plugin extends BasePlugin
{
    public const TRANSLATION_CATEGORY = 'data-export-builder';

    public static Plugin $plugin;

    public bool $hasCpSection = true;
    public string $schemaVersion = '1.0.0';

    public function init(): void
    {
        parent::init();

        self::$plugin = $this;

        Craft::setAlias('@luremo/dataexportbuilder', __DIR__);

        $this->setComponents([
            'templates' => TemplateService::class,
            'exports' => ExportService::class,
            'fieldDiscovery' => FieldDiscoveryService::class,
            'downloads' => DownloadService::class,
        ]);

        $this->registerTemplateRoots();
        $this->registerCpRoutes();
        $this->registerPermissions();
    }

    public function getCpNavItem(): array
    {
        $item = parent::getCpNavItem();
        $item['label'] = Craft::t(self::TRANSLATION_CATEGORY, 'Exports');
        $item['url'] = 'data-export-builder/exports';
        $item['badgeCount'] = $this->get('templates')->getFailedRunCount();

        return $item;
    }

    private function registerTemplateRoots(): void
    {
        Event::on(
            \craft\web\View::class,
            \craft\web\View::EVENT_REGISTER_CP_TEMPLATE_ROOTS,
            static function (RegisterTemplateRootsEvent $event): void {
                $event->roots['data-export-builder'] = __DIR__ . '/templates';
            }
        );
    }

    private function registerCpRoutes(): void
    {
        Event::on(
            \craft\web\UrlManager::class,
            \craft\web\UrlManager::EVENT_REGISTER_CP_URL_RULES,
            static function (RegisterUrlRulesEvent $event): void {
                $event->rules['data-export-builder/exports'] = 'data-export-builder/templates/index';
                $event->rules['data-export-builder/exports/new'] = 'data-export-builder/templates/edit';
                $event->rules['data-export-builder/exports/<templateId:\d+>'] = 'data-export-builder/templates/edit';
                $event->rules['data-export-builder/exports/<templateId:\d+>/download/<runId:\d+>'] = 'data-export-builder/export/download';
                $event->rules['data-export-builder/exports/<templateId:\d+>/run'] = 'data-export-builder/export/run';
                $event->rules['data-export-builder/exports/fields'] = 'data-export-builder/export/fields';
                $event->rules['data-export-builder/exports/<templateId:\d+>/duplicate'] = 'data-export-builder/templates/duplicate';
                $event->rules['data-export-builder/exports/<templateId:\d+>/delete'] = 'data-export-builder/templates/delete';
            }
        );
    }

    private function registerPermissions(): void
    {
        Event::on(
            UserPermissions::class,
            UserPermissions::EVENT_REGISTER_PERMISSIONS,
            static function (RegisterUserPermissionsEvent $event): void {
                $event->permissions[] = [
                    'heading' => Craft::t(self::TRANSLATION_CATEGORY, 'Data Export Builder'),
                    'permissions' => [
                        'manageDataExports' => [
                            'label' => Craft::t(self::TRANSLATION_CATEGORY, 'Manage export templates'),
                        ],
                        'runDataExports' => [
                            'label' => Craft::t(self::TRANSLATION_CATEGORY, 'Run exports'),
                        ],
                        'downloadDataExports' => [
                            'label' => Craft::t(self::TRANSLATION_CATEGORY, 'Download export files'),
                        ],
                    ],
                ];
            }
        );
    }
}
