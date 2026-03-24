<?php

declare(strict_types=1);

namespace Luremo\DataExportBuilder\Tests\Unit;

use DateTimeImmutable;
use Luremo\DataExportBuilder\models\ExportTemplate;
use Luremo\DataExportBuilder\services\ScheduleService;
use PHPUnit\Framework\TestCase;

final class ScheduleServiceTest extends TestCase
{
    public function testDailyScheduleProducesNextRun(): void
    {
        $service = new ScheduleService();
        $template = new ExportTemplate([
            'name' => 'Daily',
            'handle' => 'daily',
            'elementType' => 'entries',
            'format' => 'csv',
            'settings' => [
                'schedule' => [
                    'enabled' => true,
                    'frequency' => 'daily',
                    'hour' => 9,
                    'minute' => 30,
                ],
            ],
        ]);

        $next = $service->getNextRunDate($template, new DateTimeImmutable('2026-03-24 08:00:00'));

        self::assertSame('2026-03-24 09:30', $next?->format('Y-m-d H:i'));
    }

    public function testWeeklyScheduleUsesSelectedWeekdays(): void
    {
        $service = new ScheduleService();
        $template = new ExportTemplate([
            'name' => 'Weekly',
            'handle' => 'weekly',
            'elementType' => 'entries',
            'format' => 'csv',
            'settings' => [
                'schedule' => [
                    'enabled' => true,
                    'frequency' => 'weekly',
                    'hour' => 10,
                    'minute' => 0,
                    'weekdays' => ['wed'],
                ],
            ],
        ]);

        $next = $service->getNextRunDate($template, new DateTimeImmutable('2026-03-24 12:00:00'));

        self::assertSame('2026-03-25 10:00', $next?->format('Y-m-d H:i'));
    }
}
