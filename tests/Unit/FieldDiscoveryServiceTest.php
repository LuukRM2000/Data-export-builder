<?php

declare(strict_types=1);

namespace Luremo\DataExportBuilder\Tests\Unit;

use Luremo\DataExportBuilder\services\FieldDiscoveryService;
use PHPUnit\Framework\TestCase;

final class FieldDiscoveryServiceTest extends TestCase
{
    public function testEagerLoadPathsStayMinimalAndUnique(): void
    {
        $service = new FieldDiscoveryService();

        $paths = $service->getEagerLoadPaths([
            'author.email',
            'relatedArticles.title',
            'relatedArticles.slug',
            'matrixField.copy.heading',
            'section.handle',
            'site.handle',
        ]);

        self::assertSame(
            ['author', 'relatedArticles', 'matrixField'],
            $paths
        );
    }
}
