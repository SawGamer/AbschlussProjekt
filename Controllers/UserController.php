<?php

declare(strict_types=1);

require_once __DIR__ . '/../Models/Database.php';
require_once __DIR__ . '/../Models/WorkTime.php';
require_once __DIR__ . '/../utils/HelperFunctions.php';
require_once __DIR__ . '/../utils/HttpException.php';

class UserController
{
    /** Day types offered in the monthly table. An empty type is allowed. */
    private const DAY_TYPES = ['', 'FehlTag', 'Normal', 'Urlaub', 'Krank', 'Feiertag', 'Wochenende', 'Notfall'];
    private const MAX_PAUSE_MINUTES = 24 * 60;
    private const MAX_COMMENT_LENGTH = 255;

    private Database $database;

    public function __construct(Database $database)
    {
        $this->database = $database;
    }

    /**
     * Name and today's clock times for the dashboard.
     */
    public function getUserDataHour(int $userId): void
    {
        $today = $this->database->getUserDataHour($userId, date('Y-m-d'));
        if (!$today) {
            throw new HttpException(404, 'Employee not found');
        }
        HelperFunctions::json($today);
    }

    public function getUserHoursLogMonth(int $year, int $month, int $userId): void
    {
        HelperFunctions::json($this->database->getHoursLogMonth($year, $month, $userId));
    }

    public function getUserHoursLogYear(int $year, int $userId): void
    {
        HelperFunctions::json($this->database->getHoursLogYear($year, $userId));
    }

    /**
     * Stamp clock: the first call of the day clocks in, the second clocks out.
     * Uses the server's clock, so the browser cannot choose the time.
     */
    public function logTime(int $userId, $pauseMinutes): void
    {
        $date = date('Y-m-d');
        $time = date('H:i:s'); // seconds keep the live timer exact
        $day = $this->database->getDay($userId, $date);

        if (!$day || $day['start'] === null) {
            if ($day) {
                throw new HttpException(409, 'Today already has a manual entry');
            }
            $this->database->clockIn($userId, $date, $time);
            HelperFunctions::json(['status' => 'started', 'start' => $time]);
            return;
        }

        if ($day['end'] !== null) {
            throw new HttpException(409, 'Already clocked out today');
        }

        $pause = $this->parsePause($pauseMinutes);
        $actual = WorkTime::actualHours($day['start'], $time, $pause);
        $overtime = WorkTime::overtime($actual, new DateTimeImmutable($date), $this->database->getWeeklyHours($userId));
        $this->database->clockOut($userId, $date, $time, $pause, $actual, $overtime);

        HelperFunctions::json([
            'status' => 'stopped',
            'start' => $day['start'],
            'end' => $time,
            'actual_hours' => $actual,
            'overtime' => $overtime,
        ]);
    }

    /**
     * Saves one day from the monthly table or the manual entry form.
     * Worked hours and overtime are calculated here, not trusted from the browser.
     */
    public function editLog(int $userId, array $body): void
    {
        $date = $this->parseDate($body['date'] ?? null);
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

        HelperFunctions::json([
            'message' => 'Hours logged successfully',
            'actual_hours' => $actual,
            'overtime' => $overtime,
        ]);
    }

    private function parseDate($value): DateTimeImmutable
    {
        $date = is_string($value) ? DateTimeImmutable::createFromFormat('!Y-m-d', $value) : false;
        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw new HttpException(422, 'date must be YYYY-MM-DD');
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
