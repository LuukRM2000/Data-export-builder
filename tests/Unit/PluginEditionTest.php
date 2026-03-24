<?php

declare(strict_types=1);

namespace Luremo\DataExportBuilder\Tests\Unit;

use Luremo\DataExportBuilder\Plugin;
use PHPUnit\Framework\TestCase;

final class PluginEditionTest extends TestCase
{
    public function testPluginDeclaresAscendingCraftEditions(): void
    {
        self::assertSame(
            [Plugin::EDITION_STANDARD, Plugin::EDITION_PRO],
            Plugin::editions()
        );
    }
}
