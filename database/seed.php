<?php

declare(strict_types=1);

// Fills the demo database with fictional data. Run after importing schema.sql:
//   php database/seed.php
// - hours from 1 January until yesterday for every employee except the admin
// - approved vacation (booked as "Urlaub" days) and a few pending requests
// - a realistic "today": some people working or on a break, one on vacation
// Uses the same rules as the app, so weekends and holidays are skipped and
// worked hours and overtime match what the app would calculate.

$root = dirname(__DIR__);
require_once $root . '/Config/database.php';
require_once $root . '/Models/Database.php';
require_once $root . '/Models/WorkTime.php';

date_default_timezone_set(getenv('APP_TIMEZONE') ?: 'Europe/Berlin');
mt_srand(42); // same sample data on every run

$db = new Database($dbConfig);
$today = new DateTimeImmutable('today');
$year = (int) $today->format('Y');
$nowMinutes = (int) date('G') * 60 + (int) date('i');
$adminId = 1;
$monday = $today->modify('monday this week');

$time = static fn(int $minutes): string => sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
$date = static fn(int $month, int $day) => new DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, $day));

// Approved vacation per employee: [first day, last day]. Leyla is away this week.
$vacations = [
    2 => [[$date(3, 30), $date(4, 2)], [$date(8, 3), $date(8, 14)]],
    3 => [[$date(5, 26), $date(5, 29)], [$date(7, 20), $date(7, 31)]],
    4 => [[$date(2, 16), $date(2, 20)], [$date(9, 7), $date(9, 18)]],
    5 => [[$date(6, 8), $date(6, 12)], [$monday, $monday->modify('+4 days')]],
    6 => [[$date(10, 26), $date(10, 30)]],
];

// Pending requests the admin can decide on.
$pending = [
    2 => [$today->modify('+3 weeks monday'), $today->modify('+3 weeks monday')->modify('+4 days'), 'Familienbesuch'],
    6 => [$today->modify('+5 weeks monday'), $today->modify('+5 weeks monday')->modify('+2 days'), 'Prüfungsvorbereitung'],
];

$vacationDays = [];
foreach ($vacations as $employeeId => $blocks) {
    foreach ($blocks as [$from, $to]) {
        if ($from->format('Y') !== (string) $year) {
            continue; // "this week" may start in December of last year
        }
        $days = WorkTime::workingDays($from, $to);
        foreach ($days as $day) {
            $vacationDays[$employeeId][$day->format('Y-m-d')] = true;
        }
        $requestId = $db->createLeave($employeeId, $from->format('Y-m-d'), $to->format('Y-m-d'), count($days), 'Erholungsurlaub');
        $db->setLeaveStatus($requestId, 'approved', $adminId, '');
        $db->audit($adminId, $employeeId, 'leave_approved', sprintf('%s bis %s, %d Tage', $from->format('d.m.Y'), $to->format('d.m.Y'), count($days)));
    }
}
foreach ($pending as $employeeId => [$from, $to, $comment]) {
    $db->createLeave($employeeId, $from->format('Y-m-d'), $to->format('Y-m-d'), count(WorkTime::workingDays($from, $to)), $comment);
}

$rows = 0;
foreach ([2, 3, 4, 5, 6] as $employeeId) {
    $weekly = $db->getWeeklyHours($employeeId);
    $lastDay = $today->modify('last day of december this year'); // approved vacation can lie in the future

    for ($day = $today->modify('first day of january this year'); $day <= $lastDay; $day = $day->modify('+1 day')) {
        $key = $day->format('Y-m-d');
        $target = WorkTime::targetHours($day, $weekly);
        if ($target === 0.0) {
            continue; // weekend or public holiday
        }

        if (isset($vacationDays[$employeeId][$key])) {
            $db->editLog($employeeId, 0.0, null, null, 0.0, 'Urlaub', $key, 'Urlaub (genehmigt)', 0);
            $rows++;
            continue;
        }
        if ($day >= $today) {
            continue; // no hours in the future
        }

        $type = 'Normal';
        $comment = '';
        $pause = $target >= 6 ? 30 : 15;
        $start = 7 * 60 + 30 + 15 * mt_rand(0, 6);
        $worked = (int) ($target * 60) + 15 * mt_rand(-2, 4);
        $end = $start + $worked + $pause;

        if (mt_rand(1, 100) <= 3) {
            $db->editLog($employeeId, 0.0, null, null, 0.0, 'Krank', $key, 'Arzttermin', 0);
            $rows++;
            continue;
        }
        if (mt_rand(1, 100) <= 4) {
            $end = max($end, 19 * 60);
            $comment = 'Release vorbereitet';
        }

        $actual = WorkTime::actualHours($time($start), $time($end), $pause);
        $db->editLog($employeeId, $actual, $time($start), $time($end), WorkTime::overtime($actual, $day, $weekly), $type, $key, $comment, $pause);
        $rows++;
    }
}

// Today: Erika and Jonas are at work, depending on the time of day. Leyla is on vacation.
$todayKey = $today->format('Y-m-d');
if (WorkTime::targetHours($today) > 0) {
    $plans = [
        3 => ['start' => 7 * 60 + 45, 'break' => [12 * 60, 12 * 60 + 30], 'end' => 14 * 60 + 15],
        4 => ['start' => 8 * 60 + 30, 'break' => [12 * 60 + 30, 13 * 60 + 15], 'end' => 17 * 60 + 15],
    ];
    foreach ($plans as $employeeId => $plan) {
        if ($nowMinutes <= $plan['start']) {
            continue;
        }
        $db->clockIn($employeeId, $todayKey, $time($plan['start']) . ':00');
        [$breakStart, $breakEnd] = $plan['break'];
        if ($nowMinutes > $breakStart) {
            $db->startBreak($employeeId, $todayKey, $time($breakStart) . ':00');
            if ($nowMinutes > $breakEnd) {
                $db->endOpenBreak($employeeId, $todayKey, $time($breakEnd) . ':00');
            }
        }
        if ($nowMinutes > $plan['end']) {
            $pause = $breakEnd - $breakStart;
            $actual = WorkTime::actualHours($time($plan['start']), $time($plan['end']), $pause);
            $db->clockOut($employeeId, $todayKey, $time($plan['end']) . ':00', $pause, $actual,
                WorkTime::overtime($actual, $today, $db->getWeeklyHours($employeeId)));
        }
    }
}

echo "Inserted $rows days of sample hours, " . count($pending) . " pending vacation requests.\n";
