<?php

declare(strict_types=1);

namespace Luremo\DataExportBuilder\helpers;

use Craft;
use craft\elements\Asset;
use craft\elements\Category;
use craft\elements\Entry;
use craft\elements\Tag;
use craft\elements\User;
use Luremo\DataExportBuilder\Plugin;

final class CapabilityHelper
{
    public const FEATURE_COMMERCE_ORDERS = 'commerceOrders';
    public const FEATURE_ADVANCED_QUEUE = 'advancedQueue';
    public const FEATURE_XLSX = 'xlsx';
    public const FEATURE_SCHEDULES = 'schedules';
    public const FEATURE_DELIVERY = 'delivery';
    public const ELEMENT_TYPE_WHEELFORM_SUBMISSIONS = 'wheelform-submissions';
    public const ELEMENT_TYPE_FORMIE_SUBMISSIONS = 'formie-submissions';
    public const ELEMENT_TYPE_PRODUCTS = 'products';
    public const ELEMENT_TYPE_VARIANTS = 'variants';

    public static function getEdition(): string
    {
        if (isset(Plugin::$plugin)) {
            foreach (array_reverse(Plugin::editions()) as $edition) {
                if (Plugin::$plugin->is($edition)) {
                    return $edition;
                }
            }
        }

        return Plugin::EDITION_STANDARD;
    }

    public static function isProEdition(): bool
    {
        return isset(Plugin::$plugin) && Plugin::$plugin->is(Plugin::EDITION_PRO);
    }

    public static function isCommerceInstalled(): bool
    {
        return class_exists(\craft\commerce\Plugin::class) && class_exists(\craft\commerce\elements\Order::class);
    }

    public static function isWheelFormInstalled(): bool
    {
        return class_exists(\wheelform\db\Form::class)
            && class_exists(\wheelform\db\Message::class)
            && self::isPluginEnabled('wheelform');
    }

    public static function isFormieInstalled(): bool
    {
        return class_exists(\verbb\formie\elements\Form::class)
            && class_exists(\verbb\formie\elements\Submission::class)
            && self::isPluginEnabled('formie');
    }

    public static function hasFeature(string $feature): bool
    {
        return self::editionHasFeature(self::getEdition(), $feature);
    }

    public static function editionHasFeature(string $edition, string $feature): bool
    {
        return match ($feature) {
            self::FEATURE_COMMERCE_ORDERS,
            self::FEATURE_ADVANCED_QUEUE,
            self::FEATURE_XLSX,
            self::FEATURE_SCHEDULES,
            self::FEATURE_DELIVERY => $edition === Plugin::EDITION_PRO,
            default => true,
        };
    }

    public static function supportsFormat(string $format): bool
    {
        return self::supportsFormatForEdition(self::getEdition(), $format);
    }

    public static function supportsFormatForEdition(string $edition, string $format): bool
    {
        return match ($format) {
            'csv', 'json' => true,
            'xlsx' => self::editionHasFeature($edition, self::FEATURE_XLSX),
            default => false,
        };
    }

    public static function supportsElementTypeHandle(string $handle): bool
    {
        if (in_array($handle, ['orders', self::ELEMENT_TYPE_PRODUCTS, self::ELEMENT_TYPE_VARIANTS], true)) {
            return self::isCommerceInstalled() && self::hasFeature(self::FEATURE_COMMERCE_ORDERS);
        }

        if ($handle === self::ELEMENT_TYPE_WHEELFORM_SUBMISSIONS) {
            return self::isWheelFormInstalled();
        }

        if ($handle === self::ELEMENT_TYPE_FORMIE_SUBMISSIONS) {
            return self::isFormieInstalled();
        }

        return in_array($handle, ['entries', 'users', 'categories', 'tags', 'assets'], true);
    }

    /**
     * @return array<string, array{label:string,class:string|null}>
     */
    public static function supportedElementTypes(): array
    {
        $types = [
            'entries' => ['label' => 'Entries', 'class' => Entry::class],
            'users' => ['label' => 'Users', 'class' => User::class],
            'categories' => ['label' => 'Categories', 'class' => Category::class],
            'tags' => ['label' => 'Tags', 'class' => Tag::class],
            'assets' => ['label' => 'Assets', 'class' => Asset::class],
        ];

        if (self::supportsElementTypeHandle('orders')) {
            $types['orders'] = ['label' => 'Commerce Orders', 'class' => \craft\commerce\elements\Order::class];
            $types[self::ELEMENT_TYPE_PRODUCTS] = ['label' => 'Commerce Products', 'class' => \craft\commerce\elements\Product::class];
            $types[self::ELEMENT_TYPE_VARIANTS] = ['label' => 'Commerce Variants', 'class' => \craft\commerce\elements\Variant::class];
        }

        if (self::supportsElementTypeHandle(self::ELEMENT_TYPE_WHEELFORM_SUBMISSIONS)) {
            $types[self::ELEMENT_TYPE_WHEELFORM_SUBMISSIONS] = [
                'label' => 'Wheel Form Submissions',
                'class' => \wheelform\db\Message::class,
            ];
        }

        if (self::supportsElementTypeHandle(self::ELEMENT_TYPE_FORMIE_SUBMISSIONS)) {
            $types[self::ELEMENT_TYPE_FORMIE_SUBMISSIONS] = [
                'label' => 'Formie Submissions',
                'class' => \verbb\formie\elements\Submission::class,
            ];
        }

        return $types;
    }

    private static function isPluginEnabled(string $handle): bool
    {
        try {
            $plugins = Craft::$app->getPlugins();
            return $plugins->isPluginInstalled($handle) && $plugins->isPluginEnabled($handle);
        } catch (\Throwable) {
            return false;
        }
    }
}
