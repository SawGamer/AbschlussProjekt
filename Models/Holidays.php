<?php

declare(strict_types=1);

/**
 * Public holidays used for the daily target hours.
 * Fixed dates plus the movable feasts that depend on Easter Sunday,
 * so the list works for any year instead of a hand-written JSON file.
 */
final class Holidays
{
    /**
     * @return array<string, string> date (Y-m-d) => holiday name
     */
    public static function forYear(int $year): array
    {
        $easter = self::easterSunday($year);
        $relative = static fn(int $days): string => $easter->modify("$days days")->format('Y-m-d');

        $holidays = [
            sprintf('%04d-01-01', $year) => 'Neujahr',
            sprintf('%04d-01-06', $year) => 'Heilige Drei Könige',
            $relative(-2) => 'Karfreitag',
            $relative(1) => 'Ostermontag',
            sprintf('%04d-05-01', $year) => 'Tag der Arbeit',
            $relative(39) => 'Christi Himmelfahrt',
            $relative(50) => 'Pfingstmontag',
            $relative(60) => 'Fronleichnam',
            sprintf('%04d-10-03', $year) => 'Tag der Deutschen Einheit',
            sprintf('%04d-11-01', $year) => 'Allerheiligen',
            sprintf('%04d-12-25', $year) => '1. Weihnachtstag',
            sprintf('%04d-12-26', $year) => '2. Weihnachtstag',
        ];
        ksort($holidays);

        return $holidays;
    }

    public static function isHoliday(DateTimeInterface $date): bool
    {
        return isset(self::forYear((int) $date->format('Y'))[$date->format('Y-m-d')]);
    }

    /**
     * Easter Sunday in the Gregorian calendar
     * (anonymous Gregorian algorithm by Meeus, Jones and Butcher).
     */
    public static function easterSunday(int $year): DateTimeImmutable
    {
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day = (($h + $l - 7 * $m + 114) % 31) + 1;

        return new DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, $day));
    }
}
