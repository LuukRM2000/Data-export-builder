<?php

declare(strict_types=1);

namespace Luremo\DataExportBuilder\Tests\Unit;

use Luremo\DataExportBuilder\services\TemplateService;
use PHPUnit\Framework\TestCase;

final class TemplateServiceTest extends TestCase
{
    public function testCreateTemplateFromRequestNormalizesHandlesDatesAndFields(): void
    {
        $service = new TemplateService();

        $template = $service->createTemplateFromRequest([
            'name' => ' Orders Export ',
            'handle' => 'Orders Export!!!',
            'elementType' => 'entries',
            'format' => 'json',
            'filters' => [
                'formId' => '42',
                'dateFrom' => [
                    'year' => '2026',
                    'month' => '03',
                    'day' => '20',
                ],
                'dateTo' => '2026-03-24 17:30:00',
            ],
            'settings' => [
                'queueThreshold' => '250',
            ],
            'fields' => [
                [
                    'fieldPath' => 'title',
                    'columnLabel' => '',
                    'sortOrder' => 2,
                ],
                [
                    'fieldPath' => 'author.email',
                    'columnLabel' => 'Author Email',
                    'sortOrder' => 1,
                ],
            ],
        ]);

        self::assertSame('Orders Export', $template->name);
        self::assertSame('orders-export', $template->handle);
        self::assertSame('json', $template->format);
        self::assertSame(42, $template->filters['formId']);
        self::assertSame('2026-03-20', $template->filters['dateFrom']);
        self::assertSame('2026-03-24', $template->filters['dateTo']);
        self::assertSame(250, $template->settings['queueThreshold']);
        self::assertSame('Author Email', $template->fields[0]->columnLabel);
        self::assertSame('title', $template->fields[1]->fieldPath);
        self::assertSame('title', $template->fields[1]->columnLabel);
    }

    public function testCreateTemplateFromRequestDefaultsInvalidOptionalValues(): void
    {
        $service = new TemplateService();

        $template = $service->createTemplateFromRequest([
            'name' => 'Basic Export',
            'handle' => '',
            'filters' => [
                'formId' => 'not-a-number',
                'dateFrom' => 'not-a-date',
            ],
            'fields' => [
                [
                    'fieldPath' => '',
                    'columnLabel' => 'Ignore me',
                ],
                [
                    'fieldPath' => 'slug',
                ],
            ],
        ]);

        self::assertSame('basic-export', $template->handle);
        self::assertNull($template->filters['formId']);
        self::assertNull($template->filters['dateFrom']);
        self::assertCount(1, $template->fields);
        self::assertSame('slug', $template->fields[0]->columnLabel);
    }
}
