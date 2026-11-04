<?php

declare(strict_types=1);

// Fills the demo database with sample hours from 1 January of this year until yesterday.
// Run after importing schema.sql:  php database/seed.php
// Uses the same rules as the app, so weekends and holidays are skipped
// and worked hours and overtime match what the app would calculate.

$root = dirname(__DIR__);
require_once $root . '/Config/database.php';
require_once $root . '/Models/Database.php';
require_once $root . '/Models/WorkTime.php';

date_default_timezone_set(getenv('APP_TIMEZONE') ?: 'Europe/Berlin');
mt_srand(42); // same sample data on every run

$database = new Database($dbConfig);
$today = new DateTimeImmutable('today');
$employeeIds = [2, 3]; // Max (full time) and Erika (part time), see schema.sql
$rows = 0;

foreach ($employeeIds as $employeeId) {
    $weeklyHours = $database->getWeeklyHours($employeeId);

    for ($date = $today->modify('first day of january this year'); $date < $today; $date = $date->modify('+1 day')) {
        $target = WorkTime::targetHours($date, $weeklyHours);
        if ($target === 0.0) {
            continue; // weekend or public holiday
        }

        // Start between 07:30 and 09:00, work the target plus or minus up to an hour.
        $pause = 30;
        $startMinutes = 7 * 60 + 30 + 15 * mt_rand(0, 6);
        $workedMinutes = (int) ($target * 60) + 15 * mt_rand(-2, 4);
        $start = sprintf('%02d:%02d', intdiv($startMinutes, 60), $startMinutes % 60);
        $endMinutes = $startMinutes + $workedMinutes + $pause;
        $end = sprintf('%02d:%02d', intdiv($endMinutes, 60), $endMinutes % 60);
        $type = 'Normal';
        $comment = '';

        $roll = mt_rand(1, 100);
        if ($roll <= 4) {
            [$type, $start, $end, $pause, $comment] = ['Krank', null, null, 0, 'Arzttermin'];
        } elseif ($roll <= 10) {
            [$type, $start, $end, $pause, $comment] = ['Urlaub', null, null, 0, ''];
        } elseif ($roll <= 13) {
            $end = '19:00';
            $comment = 'Release vorbereitet';
        }

        $actual = WorkTime::actualHours($start, $end, $pause);
        $overtime = WorkTime::overtime($actual, $date, $weeklyHours);
        $database->editLog($employeeId, $actual, $start, $end, $overtime, $type, $date->format('Y-m-d'), $comment, $pause);
        $rows++;
    }
}

echo "Inserted or updated $rows days of sample hours.\n";
