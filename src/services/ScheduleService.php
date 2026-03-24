<?php

declare(strict_types=1);

namespace Luremo\DataExportBuilder\services;

use Craft;
use craft\base\Component;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Luremo\DataExportBuilder\models\ExportTemplate;
use Luremo\DataExportBuilder\Plugin;

final class ScheduleService extends Component
{
    /**
     * @return array{enabled:bool,frequency:string,hour:int,minute:int,weekdays:array<int,string>,lastScheduledAt:?string}
     */
    public function normalizeSettings(array $settings): array
    {
        $schedule = is_array($settings['schedule'] ?? null) ? $settings['schedule'] : [];
        $weekdays = array_values(array_filter(array_map(
            static fn(mixed $value): string => strtolower(trim((string)$value)),
            is_array($schedule['weekdays'] ?? null) ? $schedule['weekdays'] : []
        ), static fn(string $value): bool => in_array($value, ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'], true)));

        return [
            'enabled' => (bool)($schedule['enabled'] ?? false),
            'frequency' => in_array(($schedule['frequency'] ?? 'daily'), ['hourly', 'daily', 'weekly'], true)
                ? (string)$schedule['frequency']
                : 'daily',
            'hour' => max(0, min(23, (int)($schedule['hour'] ?? 2))),
            'minute' => max(0, min(59, (int)($schedule['minute'] ?? 0))),
            'weekdays' => $weekdays,
            'lastScheduledAt' => ($schedule['lastScheduledAt'] ?? null) ?: null,
        ];
    }

    public function isDue(ExportTemplate $template, ?DateTimeImmutable $now = null): bool
    {
        $settings = $this->normalizeSettings($template->settings);
        if (!$settings['enabled']) {
            return false;
        }

        $now ??= new DateTimeImmutable('now', $this->timeZone());
        $latestSlot = $this->latestRunSlot($settings, $now);
        if ($latestSlot === null || $latestSlot > $now) {
            return false;
        }

        $lastScheduledAt = $settings['lastScheduledAt'] !== null
            ? new DateTimeImmutable($settings['lastScheduledAt'], $this->timeZone())
            : null;

        return $lastScheduledAt === null || $lastScheduledAt < $latestSlot;
    }

    public function getNextRunDate(ExportTemplate $template, ?DateTimeImmutable $from = null): ?DateTimeImmutable
    {
        $settings = $this->normalizeSettings($template->settings);
        if (!$settings['enabled']) {
            return null;
        }

        $from ??= new DateTimeImmutable('now', $this->timeZone());

        return match ($settings['frequency']) {
            'hourly' => $this->nextHourlyRun($settings['minute'], $from),
            'weekly' => $this->nextWeeklyRun($settings['weekdays'], $settings['hour'], $settings['minute'], $from),
            default => $this->nextDailyRun($settings['hour'], $settings['minute'], $from),
        };
    }

    public function enqueueDueScheduledTemplates(): int
    {
        $count = 0;

        foreach (Plugin::$plugin->get('templates')->getAllTemplates() as $template) {
            if (!$this->isDue($template)) {
                continue;
            }

            Plugin::$plugin->get('exports')->runTemplate($template, null, true);
            $this->markScheduled($template);
            $count++;
        }

        return $count;
    }

    public function markScheduled(ExportTemplate $template, ?DateTimeImmutable $at = null): void
    {
        if (!$template->id) {
            return;
        }

        $at ??= new DateTimeImmutable('now', $this->timeZone());
        $settings = $template->settings;
        $settings['schedule'] = array_merge(
            $this->normalizeSettings($settings),
            ['lastScheduledAt' => $at->format(DATE_ATOM)]
        );

        Plugin::$plugin->get('templates')->updateTemplateSettings($template->id, $settings);
    }

    private function latestRunSlot(array $settings, DateTimeImmutable $now): ?DateTimeImmutable
    {
        return match ($settings['frequency']) {
            'hourly' => $this->latestHourlyRun($settings['minute'], $now),
            'weekly' => $this->latestWeeklyRun($settings['weekdays'], $settings['hour'], $settings['minute'], $now),
            default => $this->latestDailyRun($settings['hour'], $settings['minute'], $now),
        };
    }

    private function latestHourlyRun(int $minute, DateTimeImmutable $now): DateTimeImmutable
    {
        $candidate = $now->setTime((int)$now->format('H'), $minute, 0);

        return $candidate > $now ? $candidate->sub(new DateInterval('PT1H')) : $candidate;
    }

    private function latestDailyRun(int $hour, int $minute, DateTimeImmutable $now): DateTimeImmutable
    {
        $candidate = $now->setTime($hour, $minute, 0);

        return $candidate > $now ? $candidate->sub(new DateInterval('P1D')) : $candidate;
    }

    private function latestWeeklyRun(array $weekdays, int $hour, int $minute, DateTimeImmutable $now): ?DateTimeImmutable
    {
        $weekdays = $weekdays !== [] ? $weekdays : [$this->weekdayToken($now)];

        for ($daysBack = 0; $daysBack <= 7; $daysBack++) {
            $candidate = $now->sub(new DateInterval(sprintf('P%dD', $daysBack)))->setTime($hour, $minute, 0);

            if (in_array($this->weekdayToken($candidate), $weekdays, true) && $candidate <= $now) {
                return $candidate;
            }
        }

        return null;
    }

    private function nextHourlyRun(int $minute, DateTimeImmutable $from): DateTimeImmutable
    {
        $candidate = $from->setTime((int)$from->format('H'), $minute, 0);

        return $candidate <= $from ? $candidate->add(new DateInterval('PT1H')) : $candidate;
    }

    private function nextDailyRun(int $hour, int $minute, DateTimeImmutable $from): DateTimeImmutable
    {
        $candidate = $from->setTime($hour, $minute, 0);

        return $candidate <= $from ? $candidate->add(new DateInterval('P1D')) : $candidate;
    }

    private function nextWeeklyRun(array $weekdays, int $hour, int $minute, DateTimeImmutable $from): DateTimeImmutable
    {
        $weekdays = $weekdays !== [] ? $weekdays : [$this->weekdayToken($from)];

        for ($daysAhead = 0; $daysAhead <= 7; $daysAhead++) {
            $candidate = $from->add(new DateInterval(sprintf('P%dD', $daysAhead)))->setTime($hour, $minute, 0);

            if (in_array($this->weekdayToken($candidate), $weekdays, true) && $candidate > $from) {
                return $candidate;
            }
        }

        return $from->add(new DateInterval('P7D'))->setTime($hour, $minute, 0);
    }

    private function weekdayToken(DateTimeImmutable $date): string
    {
        return strtolower(substr($date->format('D'), 0, 3));
    }

    private function timeZone(): DateTimeZone
    {
        $timeZone = Craft::$app?->getTimeZone();

        return new DateTimeZone($timeZone ?: date_default_timezone_get());
    }
}
