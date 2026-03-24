<?php

declare(strict_types=1);

namespace Luremo\DataExportBuilder\Tests\Unit;

use Luremo\DataExportBuilder\helpers\CapabilityHelper;
use Luremo\DataExportBuilder\services\PresetService;
use PHPUnit\Framework\TestCase;

final class PresetServiceTest extends TestCase
{
    public function testCommerceProductPresetContainsCatalogFields(): void
    {
        $service = new PresetService();

        $presets = $service->getPresetsForElementType(CapabilityHelper::ELEMENT_TYPE_PRODUCTS);

        self::assertNotEmpty($presets);
        self::assertSame('catalog', $presets[0]['handle']);
        self::assertSame('defaultVariant.sku', $presets[0]['fields'][5]['path']);
    }
}
