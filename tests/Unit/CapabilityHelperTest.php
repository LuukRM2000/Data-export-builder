<?php

declare(strict_types=1);

namespace Luremo\DataExportBuilder\Tests\Unit;

use Luremo\DataExportBuilder\helpers\CapabilityHelper;
use Luremo\DataExportBuilder\Plugin;
use PHPUnit\Framework\TestCase;

final class CapabilityHelperTest extends TestCase
{
    public function testStandardEditionDoesNotIncludeOperationalProFeatures(): void
    {
        self::assertFalse(CapabilityHelper::editionHasFeature(Plugin::EDITION_STANDARD, CapabilityHelper::FEATURE_XLSX));
        self::assertFalse(CapabilityHelper::editionHasFeature(Plugin::EDITION_STANDARD, CapabilityHelper::FEATURE_SCHEDULES));
        self::assertFalse(CapabilityHelper::editionHasFeature(Plugin::EDITION_STANDARD, CapabilityHelper::FEATURE_DELIVERY));
        self::assertFalse(CapabilityHelper::editionHasFeature(Plugin::EDITION_STANDARD, CapabilityHelper::FEATURE_ADVANCED_QUEUE));
    }

    public function testProEditionIncludesOperationalFeatures(): void
    {
        self::assertTrue(CapabilityHelper::editionHasFeature(Plugin::EDITION_PRO, CapabilityHelper::FEATURE_XLSX));
        self::assertTrue(CapabilityHelper::editionHasFeature(Plugin::EDITION_PRO, CapabilityHelper::FEATURE_SCHEDULES));
        self::assertTrue(CapabilityHelper::editionHasFeature(Plugin::EDITION_PRO, CapabilityHelper::FEATURE_DELIVERY));
        self::assertTrue(CapabilityHelper::editionHasFeature(Plugin::EDITION_PRO, CapabilityHelper::FEATURE_ADVANCED_QUEUE));
    }

    public function testFormatSupportMatchesEdition(): void
    {
        self::assertTrue(CapabilityHelper::supportsFormatForEdition(Plugin::EDITION_STANDARD, 'csv'));
        self::assertTrue(CapabilityHelper::supportsFormatForEdition(Plugin::EDITION_STANDARD, 'json'));
        self::assertFalse(CapabilityHelper::supportsFormatForEdition(Plugin::EDITION_STANDARD, 'xlsx'));
        self::assertTrue(CapabilityHelper::supportsFormatForEdition(Plugin::EDITION_PRO, 'xlsx'));
    }
}
