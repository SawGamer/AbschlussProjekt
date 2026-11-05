<?php

declare(strict_types=1);

require_once __DIR__ . '/../Models/Database.php';
require_once __DIR__ . '/../Models/WorkTime.php';
require_once __DIR__ . '/../Models/DayState.php';
require_once __DIR__ . '/../utils/HelperFunctions.php';
require_once __DIR__ . '/../utils/HttpException.php';

class UserController
{
    /** Day types offered in the monthly table. An empty type is allowed. */
    public const DAY_TYPES = ['', 'FehlTag', 'Normal', 'Urlaub', 'Krank', 'Feiertag', 'Wochenende', 'Notfall'];
    private const MAX_PAUSE_MINUTES = 24 * 60;
    private const MAX_COMMENT_LENGTH = 255;

    private Database $database;

    public function __construct(Database $database)
    {
        $this->database = $database;
    }

    /**
     * Name, weekly hours and today's clock state for the dashboard.
     */
    public function getUserDataHour(int $userId): void
    {
        $date = date('Y-m-d');
        $today = $this->database->getUserDataHour($userId, $date);
        if (!$today) {
            throw new HttpException(404, 'Employee not found');
        }

        $state = DayState::build($today, $this->database->getBreaks($userId, $date), date('H:i:s'));
        HelperFunctions::json(['Username' => $today['Username'], 'ShouldHours' => $today['ShouldHours']] + $state);
    }

    /**
     * Stamp clock with four actions: start, pause, resume, stop.
     * Uses the server's clock, so the browser cannot choose the time.
     */
    public function clock(int $userId, string $action): void
    {
        $date = date('Y-m-d');
        $time = date('H:i:s');
        $day = $this->database->getDay($userId, $date);
        $breaks = $this->database->getBreaks($userId, $date);
        $state = DayState::build($day ?: null, $breaks, $time);

        switch ($action) {
            case 'start':
                if ($day) {
                    throw new HttpException(409, $state['status'] === 'idle'
                        ? 'Today already has an entry. Change it in the monthly table.'
                        : 'Already clocked in today');
                }
                $this->database->clockIn($userId, $date, $time);
                break;

            case 'pause':
                if ($state['status'] !== 'working') {
                    throw new HttpException(409, 'A break can only start while you are working');
                }
                $this->database->startBreak($userId, $date, $time);
                break;

            case 'resume':
                if ($state['status'] !== 'paused') {
                    throw new HttpException(409, 'No break is running');
                }
                $this->database->endOpenBreak($userId, $date, $time);
                break;

            case 'stop':
                if (!in_array($state['status'], ['working', 'paused'], true)) {
                    throw new HttpException(409, $state['status'] === 'done' ? 'Already clocked out today' : 'Not clocked in');
                }
                $this->database->transaction(function () use ($userId, $date, $time, $day) {
                    $this->database->endOpenBreak($userId, $date, $time);
                    $pause = DayState::breakMinutes($this->database->getBreaks($userId, $date), $time);
                    $actual = WorkTime::actualHours($day['start'], $time, $pause);
                    $overtime = WorkTime::overtime($actual, new DateTimeImmutable($date), $this->database->getWeeklyHours($userId));
                    $this->database->clockOut($userId, $date, $time, $pause, $actual, $overtime);
                });
                break;

            default:
                throw new HttpException(422, 'action must be start, pause, resume or stop');
        }

        $this->getUserDataHour($userId);
    }

    public function getUserHoursLogMonth(int $year, int $month, int $userId): void
    {
        HelperFunctions::json($this->database->getHoursLogMonth($year, $month, $userId));
    }

    /**
     * Per month: hours, overtime, target for the whole month and target up to today.
     * Vacation, sick and holiday entries are credited against the target.
     */
    public function getUserHoursLogYear(int $year, int $userId): void
    {
        $weekly = $this->database->getWeeklyHours($userId);
        $entries = $this->database->getHoursBetween($userId, "$year-01-01", "$year-12-31");
        $today = new DateTimeImmutable('today');

        $months = [];
        for ($month = 1; $month <= 12; $month++) {
            $first = new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
            $last = $first->modify('last day of this month');
            $full = WorkTime::balance($entries, $first, $last, $weekly);
            $toDate = $first > $today
                ? ['target' => 0.0]
                : WorkTime::balance($entries, $first, min($last, $today), $weekly);

            $overtime = 0.0;
            foreach ($entries as $date => $entry) {
                if ((int) substr($date, 5, 2) === $month) {
                    $overtime += (float) $entry['overtime'];
                }
            }

            $months[] = [
                'Month' => $month,
                'TotalHours' => $full['actual'],
                'Overtime' => round($overtime, 2),
                'Target' => $full['target'],
                'TargetToDate' => $toDate['target'],
                'AbsenceDays' => $full['absence_days'],
            ];
        }
        HelperFunctions::json($months);
    }

    /**
     * Figures for the employee overview: this week, this month, overtime account, vacation.
     */
    public function summary(int $userId): void
    {
        $weekly = $this->database->getWeeklyHours($userId);
        $today = new DateTimeImmutable('today');
        $yesterday = $today->modify('-1 day');
        $yearStart = $today->modify('first day of january this year');
        $monthStart = $today->modify('first day of this month');
        $monday = $today->modify('monday this week');
        $sunday = $monday->modify('+6 days');

        $entries = $this->database->getHoursBetween($userId, $yearStart->format('Y-m-d'), $sunday->format('Y-m-d'));

        // Count today's running hours too, so the figures move while the clock runs.
        $todayKey = $today->format('Y-m-d');
        $state = DayState::build(
            $this->database->getDay($userId, $todayKey) ?: null,
            $this->database->getBreaks($userId, $todayKey),
            date('H:i:s')
        );
        if (in_array($state['status'], ['working', 'paused'], true)) {
            $entries[$todayKey] = ['actual' => $state['worked_hours'], 'type' => 'Normal'] + ($entries[$todayKey] ?? []);
        }

        $week = WorkTime::balance($entries, $monday, $sunday, $weekly);
        $month = WorkTime::balance($entries, $monthStart, $monthStart->modify('last day of this month'), $weekly);
        // The overtime account runs to yesterday, plus today once the day is finished.
        $accountEnd = $state['status'] === 'done' ? $today : $yesterday;
        $account = $accountEnd < $yearStart
            ? ['difference' => 0.0]
            : WorkTime::balance($entries, $yearStart, $accountEnd, $weekly);

        $weekDays = [];
        for ($day = $monday; $day <= $sunday; $day = $day->modify('+1 day')) {
            $entry = $entries[$day->format('Y-m-d')] ?? null;
            $weekDays[] = [
                'date' => $day->format('Y-m-d'),
                'hours' => round((float) ($entry['actual'] ?? 0), 2),
                'target' => WorkTime::targetHours($day, $weekly),
                'type' => $entry['type'] ?? null,
            ];
        }

        HelperFunctions::json([
            'weekly_hours' => $weekly,
            'week' => ['hours' => $week['actual'], 'target' => $week['target'], 'days' => $weekDays],
            'month' => ['hours' => $month['actual'], 'target' => $month['target']],
            'overtime_balance' => $account['difference'],
            'vacation' => $this->database->vacationFigures($userId, (int) $today->format('Y')),
        ]);
    }

    /**
     * Saves one day from the monthly table or the manual entry form.
     * Worked hours and overtime are calculated here, not trusted from the browser.
     */
    public function editLog(int $userId, array $body, int $actorId): void
    {
        $date = self::parseDate($body['date'] ?? null, 'date');
        $start = $this->parseTime($body['start_time'] ?? null, 'start_time');
        $end = $this->parseTime($body['end_time'] ?? null, 'end_time');
        $pause = $this->parsePause($body['pause'] ?? 0);

        $type = (string) ($body['type'] ?? '');
        if (!in_array($type, self::DAY_TYPES, true)) {
            throw new HttpException(422, 'Unknown day type');
        }

        $comment = trim((string) ($body['comment'] ?? ''));
        if (mb_strlen($comment) > self::MAX_COMMENT_LENGTH) {
            throw new HttpException(422, 'Comment is too long');
        }

        $actual = WorkTime::actualHours($start, $end, $pause);
        $overtime = WorkTime::overtime($actual, $date, $this->database->getWeeklyHours($userId));

        $this->database->editLog(
            $userId,
            $actual,
            $start,
            $end,
            $overtime,
            $type,
            $date->format('Y-m-d'),
            $comment,
            $pause
        );

        if ($actorId !== $userId) {
            $this->database->audit($actorId, $userId, 'hours_edited', sprintf(
                '%s: %s–%s, Pause %d Min., %s',
                $date->format('d.m.Y'),
                $start ?? '--:--',
                $end ?? '--:--',
                $pause,
                $type !== '' ? $type : 'ohne Art'
            ));
        }

        HelperFunctions::json([
            'message' => 'Hours logged successfully',
            'actual_hours' => $actual,
            'overtime' => $overtime,
        ]);
    }

    /**
     * One month as a CSV file that opens directly in German Excel:
     * UTF-8 with BOM, semicolons, decimal commas.
     */
    public function exportMonth(int $userId, int $year, int $month): void
    {
        $employee = $this->database->getEmployee($userId);
        $first = new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
        $last = $first->modify('last day of this month');
        $entries = $this->database->getHoursBetween($userId, $first->format('Y-m-d'), $last->format('Y-m-d'));
        $weekdays = ['Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'];
        $number = static fn($value) => number_format((float) $value, 2, ',', '');

        $rows = [['Datum', 'Wochentag', 'Beginn', 'Ende', 'Pause (Min.)', 'Stunden', 'Überstunden', 'Art', 'Kommentar']];
        $totalHours = 0.0;
        $totalOvertime = 0.0;
        for ($day = $first; $day <= $last; $day = $day->modify('+1 day')) {
            $entry = $entries[$day->format('Y-m-d')] ?? [];
            $totalHours += (float) ($entry['actual'] ?? 0);
            $totalOvertime += (float) ($entry['overtime'] ?? 0);
            $rows[] = [
                $day->format('d.m.Y'),
                $weekdays[(int) $day->format('N') - 1],
                $entry['start'] ?? '',
                $entry['end'] ?? '',
                $entry ? (string) $entry['pause'] : '',
                $entry ? $number($entry['actual']) : '',
                $entry ? $number($entry['overtime']) : '',
                $entry['type'] ?? '',
                $entry['comment'] ?? '',
            ];
        }
        $rows[] = ['Summe', '', '', '', '', $number($totalHours), $number($totalOvertime), '', ''];

        $filename = sprintf('arbeitszeiten-%s-%04d-%02d.csv', $employee['username'], $year, $month);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        foreach ($rows as $row) {
            fputcsv($out, array_map([self::class, 'csvSafe'], $row), ';', '"', '');
        }
        fclose($out);
    }

    /**
     * Stops spreadsheet formula injection: a cell that starts with = + - @
     * is prefixed with an apostrophe so Excel shows it as text.
     */
    public static function csvSafe(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/', $value) ? "'" . $value : $value;
    }

    public static function parseDate($value, string $field): DateTimeImmutable
    {
        $date = is_string($value) ? DateTimeImmutable::createFromFormat('!Y-m-d', $value) : false;
        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw new HttpException(422, "$field must be YYYY-MM-DD");
        }
        return $date;
    }

    private function parseTime($value, string $field): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_string($value) || WorkTime::toMinutes($value) === null) {
            throw new HttpException(422, "$field must be HH:MM");
        }
        return substr($value, 0, 5);
    }

    private function parsePause($value): int
    {
        if (!is_numeric($value) || (int) $value != $value || $value < 0 || $value > self::MAX_PAUSE_MINUTES) {
            throw new HttpException(422, 'pause must be whole minutes between 0 and 1440');
        }
        return (int) $value;
    }
}
