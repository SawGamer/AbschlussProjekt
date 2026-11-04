<?php

declare(strict_types=1);

require_once __DIR__ . '/Holidays.php';

/**
 * Working-time rules shared by the clock and the manual entry.
 * The browser shows the same numbers, but the server is the source of truth.
 */
final class WorkTime
{
    public const DEFAULT_WEEKLY_HOURS = 40.0;
    public const WORKING_DAYS_PER_WEEK = 5;

    /**
     * Hours worked between start and end (HH:MM) minus the break.
     * An end time before the start time counts as a shift past midnight.
     */
    public static function actualHours(?string $start, ?string $end, int $pauseMinutes): float
    {
        $startMinutes = self::toMinutes($start);
        $endMinutes = self::toMinutes($end);
        if ($startMinutes === null || $endMinutes === null) {
            return 0.0;
        }

        $worked = $endMinutes - $startMinutes;
        if ($worked < 0) {
            $worked += 24 * 60;
        }

        return round(max($worked - $pauseMinutes, 0) / 60, 2);
    }

    /**
     * Target hours for a day: a fifth of the weekly contract hours on working days,
     * 0 on weekends and holidays.
     */
    public static function targetHours(DateTimeInterface $date, float $weeklyHours = self::DEFAULT_WEEKLY_HOURS): float
    {
        $weekday = (int) $date->format('N'); // 6 = Saturday, 7 = Sunday
        if ($weekday >= 6 || Holidays::isHoliday($date)) {
            return 0.0;
        }
        return round($weeklyHours / self::WORKING_DAYS_PER_WEEK, 2);
    }

    public static function overtime(float $actualHours, DateTimeInterface $date, float $weeklyHours = self::DEFAULT_WEEKLY_HOURS): float
    {
        return round(max($actualHours - self::targetHours($date, $weeklyHours), 0), 2);
    }

    /**
     * Converts "HH:MM" or "HH:MM:SS" to minutes after midnight.
     */
    public static function toMinutes(?string $time): ?int
    {
        if ($time === null || !preg_match('/^([01]\d|2[0-3]):([0-5]\d)(:[0-5]\d)?$/', $time, $m)) {
            return null;
        }
        return (int) $m[1] * 60 + (int) $m[2];
    }
}
