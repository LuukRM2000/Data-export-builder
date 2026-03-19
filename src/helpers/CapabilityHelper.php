<?php

declare(strict_types=1);

namespace Luremo\DataExportBuilder\helpers;

use craft\elements\Asset;
use craft\elements\Category;
use craft\elements\Entry;
use craft\elements\User;

final class CapabilityHelper
{
    public const EDITION_LITE = 'lite';
    public const EDITION_PRO = 'pro';

    public const FEATURE_COMMERCE_ORDERS = 'commerceOrders';
    public const FEATURE_ADVANCED_QUEUE = 'advancedQueue';
    public const ELEMENT_TYPE_WHEELFORM_SUBMISSIONS = 'wheelform-submissions';

    public static function getEdition(): string
    {
        $edition = strtolower((string)(getenv('DATA_EXPORT_BUILDER_EDITION') ?: self::EDITION_PRO));

        return in_array($edition, [self::EDITION_LITE, self::EDITION_PRO], true) ? $edition : self::EDITION_PRO;
    }

    public static function isProEdition(): bool
    {
        return self::getEdition() === self::EDITION_PRO;
    }

    public static function isCommerceInstalled(): bool
    {
        return class_exists(\craft\commerce\Plugin::class) && class_exists(\craft\commerce\elements\Order::class);
    }

    public static function isWheelFormInstalled(): bool
    {
        return class_exists(\wheelform\db\Form::class) && class_exists(\wheelform\db\Message::class);
    }

    public static function hasFeature(string $feature): bool
    {
        return match ($feature) {
            self::FEATURE_COMMERCE_ORDERS,
            self::FEATURE_ADVANCED_QUEUE => self::isProEdition(),
            default => true,
        };
    }

    public static function supportsElementTypeHandle(string $handle): bool
    {
        if ($handle === 'orders') {
            return self::isCommerceInstalled() && self::hasFeature(self::FEATURE_COMMERCE_ORDERS);
        }

        if ($handle === self::ELEMENT_TYPE_WHEELFORM_SUBMISSIONS) {
            return self::isWheelFormInstalled();
        }

        return in_array($handle, ['entries', 'users', 'categories', 'assets'], true);
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
            'assets' => ['label' => 'Assets', 'class' => Asset::class],
        ];

        if (self::supportsElementTypeHandle('orders')) {
            $types['orders'] = ['label' => 'Commerce Orders', 'class' => \craft\commerce\elements\Order::class];
        }

        if (self::supportsElementTypeHandle(self::ELEMENT_TYPE_WHEELFORM_SUBMISSIONS)) {
            $types[self::ELEMENT_TYPE_WHEELFORM_SUBMISSIONS] = [
                'label' => 'Wheel Form Submissions',
                'class' => \wheelform\db\Message::class,
            ];
        }

        return $types;
    }
}
