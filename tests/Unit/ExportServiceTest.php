<?php

declare(strict_types=1);

namespace Luremo\DataExportBuilder\Tests\Unit;

use Luremo\DataExportBuilder\models\ExportTemplate;
use Luremo\DataExportBuilder\services\ExportService;
use PHPUnit\Framework\TestCase;

final class ExportServiceTest extends TestCase
{
    public function testBuildCsvContentEscapesSpecialCharacters(): void
    {
        $service = new ExportService();

        $csv = $service->buildCsvContent(
            ['Title', 'Body'],
            [
                ['Quarterly "Review"', "Line one\nLine two"],
            ]
        );

        self::assertStringContainsString('"Quarterly ""Review"""', $csv);
        self::assertStringContainsString("\"Line one\nLine two\"", $csv);
    }

    public function testBuildJsonContentPreservesArrays(): void
    {
        $service = new ExportService();

        $json = $service->buildJsonContent([
            ['title' => 'Example', 'tags' => ['alpha', 'beta']],
        ]);

        self::assertJson($json);
        self::assertSame('alpha', json_decode($json, true, 512, JSON_THROW_ON_ERROR)[0]['tags'][0]);
    }

    public function testShouldQueueForLargeExports(): void
    {
        $service = new ExportService();
        $template = new ExportTemplate([
            'name' => 'Large Export',
            'handle' => 'large-export',
            'elementType' => 'entries',
            'format' => 'csv',
            'settings' => ['queueThreshold' => 100],
        ]);

        self::assertFalse($service->shouldQueueForCount($template, 100));
        self::assertTrue($service->shouldQueueForCount($template, 101));
    }

    public function testXlsxMimeTypeIsSupported(): void
    {
        self::assertSame(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            \Luremo\DataExportBuilder\helpers\ExportFileHelper::fileMimeType('xlsx')
        );
    }
}
