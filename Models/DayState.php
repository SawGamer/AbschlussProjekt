<?php

declare(strict_types=1);

require_once __DIR__ . '/WorkTime.php';

/**
 * Turns one day's entry and its breaks into the state the clock and the
 * attendance board show: idle, working, paused, done or absent.
 */
final class DayState
{
    /**
     * @param array|null $entry  Hours row with start, end, pause, type, actual_hours (times as HH:MM:SS)
     * @param array      $breaks list of ['start' => HH:MM:SS, 'end' => HH:MM:SS|null]
     * @param string     $now    current time HH:MM:SS
     */
    public static function build(?array $entry, array $breaks, string $now): array
    {
        $start = $entry['start'] ?? null;
        $end = $entry['end'] ?? null;
        $type = ($entry['type'] ?? '') ?: null;

        if ($start === null) {
            $absent = $type !== null && in_array($type, WorkTime::ABSENCE_TYPES, true);
            return [
                'status' => $absent ? 'absent' : 'idle',
                'type' => $type,
                'start' => null,
                'end' => null,
                'breaks' => [],
                'break_started' => null,
                'pause_minutes' => 0,
                'worked_hours' => 0.0,
            ];
        }

        $breakSeconds = 0;
        $openBreak = null;
        foreach ($breaks as $break) {
            $breakEnd = $break['end'] ?? null;
            if ($breakEnd === null) {
                $openBreak = $break['start'];
                $breakEnd = $end ?? $now;
            }
            $breakSeconds += max(self::seconds($breakEnd) - self::seconds($break['start']), 0);
        }

        if ($end !== null) {
            $pauseMinutes = (int) ($entry['pause'] ?? 0);
            $worked = isset($entry['actual_hours'])
                ? (float) $entry['actual_hours']
                : WorkTime::actualHours($start, $end, $pauseMinutes);
            $status = 'done';
        } else {
            $pauseMinutes = intdiv($breakSeconds, 60);
            $worked = round(max(self::seconds($now) - self::seconds($start) - $breakSeconds, 0) / 3600, 2);
            $status = $openBreak !== null ? 'paused' : 'working';
        }

        return [
            'status' => $status,
            'type' => $type,
            'start' => $start,
            'end' => $end,
            'breaks' => array_map(static fn(array $b) => ['start' => $b['start'], 'end' => $b['end'] ?? null], $breaks),
            'break_started' => $status === 'paused' ? $openBreak : null,
            'pause_minutes' => $pauseMinutes,
            'worked_hours' => $worked,
        ];
    }

    /** Total break time in whole minutes, counting a running break up to $now. */
    public static function breakMinutes(array $breaks, string $now): int
    {
        $seconds = 0;
        foreach ($breaks as $break) {
            $seconds += max(self::seconds($break['end'] ?? $now) - self::seconds($break['start']), 0);
        }
        return (int) round($seconds / 60);
    }

    private static function seconds(string $time): int
    {
        $parts = array_map('intval', explode(':', $time)) + [0, 0, 0];
        return $parts[0] * 3600 + $parts[1] * 60 + $parts[2];
    }
}
