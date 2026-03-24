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

        $customer = new class () {
            public string $email = 'customer@example.test';
            public string $fullName = 'Test Customer';
        };

        $site = new class () {
            public string $handle = 'english';
            public string $language = 'en-US';
        };

        $billingAddress = new class () {
            public string $fullName = 'Test Customer';
            public string $addressLine1 = 'Craft Street 12';
            public string $locality = 'Amsterdam';
            public string $postalCode = '1011AB';
            public string $countryCode = 'NL';
        };

        $shippingAddress = new class () {
            public string $fullName = 'Test Customer';
            public string $addressLine1 = 'Plugin Avenue 8';
            public string $locality = 'Rotterdam';
            public string $postalCode = '3011AA';
            public string $countryCode = 'NL';
        };

        $entry = new class ($author, $customer, $site, $billingAddress, $shippingAddress) {
            public function __construct(
                public object $author,
                public object $customer,
                public object $site,
                public object $billingAddress,
                public object $shippingAddress,
            )
            {
            }

            public DateTimeImmutable $dateCreated;
        };

        $entry->dateCreated = new DateTimeImmutable('2026-03-16 12:30:00');

        self::assertSame('author@example.test', FieldValueHelper::resolveFieldValue($entry, 'author.email', 'csv'));
        self::assertSame('customer@example.test', FieldValueHelper::resolveFieldValue($entry, 'customer.email', 'csv'));
        self::assertSame('Test Customer', FieldValueHelper::resolveFieldValue($entry, 'customer.fullName', 'csv'));
        self::assertSame('en-US', FieldValueHelper::resolveFieldValue($entry, 'site.language', 'csv'));
        self::assertSame('Craft Street 12', FieldValueHelper::resolveFieldValue($entry, 'billingAddress.addressLine1', 'csv'));
        self::assertSame('Rotterdam', FieldValueHelper::resolveFieldValue($entry, 'shippingAddress.locality', 'csv'));
        self::assertSame('2026-03-16 12:30:00', FieldValueHelper::resolveFieldValue($entry, 'dateCreated', 'csv'));
    }

    public function testResolveFieldValueFallsBackWhenFullNameIsMissing(): void
    {
        $customer = new class () {
            public ?string $fullName = null;
            public ?string $firstName = null;
            public ?string $lastName = null;
            public string $friendlyName = 'Admin User';
            public string $username = 'admin';
            public string $email = 'admin@example.test';
        };

        self::assertSame('Admin User', FieldValueHelper::resolveFieldValue($customer, 'fullName', 'csv'));
    }

    public function testResolveFieldValueFormatsArraysForCsvAndJson(): void
    {
        $value = [
            ['title' => 'One'],
            ['title' => 'Two'],
        ];

        self::assertSame('One, Two', FieldValueHelper::normalizeResolvedValue($value, 'csv'));
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
