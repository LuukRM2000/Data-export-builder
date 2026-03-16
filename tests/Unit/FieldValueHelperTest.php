<?php

declare(strict_types=1);

namespace Luremo\DataExportBuilder\Tests\Unit;

use DateTimeImmutable;
use Luremo\DataExportBuilder\helpers\FieldValueHelper;
use PHPUnit\Framework\TestCase;

final class FieldValueHelperTest extends TestCase
{
    public function testResolveFieldValueHandlesNestedObjectsAndDates(): void
    {
        $author = new class () {
            public string $email = 'author@example.test';
        };

        $entry = new class ($author) {
            public function __construct(public object $author)
            {
            }

            public DateTimeImmutable $dateCreated;
        };

        $entry->dateCreated = new DateTimeImmutable('2026-03-16 12:30:00');

        self::assertSame('author@example.test', FieldValueHelper::resolveFieldValue($entry, 'author.email', 'csv'));
        self::assertSame('2026-03-16 12:30:00', FieldValueHelper::resolveFieldValue($entry, 'dateCreated', 'csv'));
    }

    public function testResolveFieldValueFormatsArraysForCsvAndJson(): void
    {
        $value = [
            ['title' => 'One'],
            ['title' => 'Two'],
        ];

        self::assertStringContainsString('"title":"One"', (string)FieldValueHelper::normalizeResolvedValue($value, 'csv'));
        self::assertIsArray(FieldValueHelper::normalizeResolvedValue($value, 'json'));
    }

    public function testResolveFieldValueNormalizesBooleansAndNulls(): void
    {
        self::assertSame('true', FieldValueHelper::normalizeResolvedValue(true, 'csv'));
        self::assertSame('', FieldValueHelper::normalizeResolvedValue(null, 'csv'));
        self::assertTrue(FieldValueHelper::normalizeResolvedValue(true, 'json'));
        self::assertNull(FieldValueHelper::normalizeResolvedValue(null, 'json'));
    }
}
