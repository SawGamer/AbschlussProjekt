<?php

declare(strict_types=1);

require_once __DIR__ . '/../Models/Database.php';
require_once __DIR__ . '/../Models/WorkTime.php';
require_once __DIR__ . '/UserController.php';
require_once __DIR__ . '/../utils/HelperFunctions.php';
require_once __DIR__ . '/../utils/HttpException.php';

/**
 * Vacation requests: employees ask, admins approve or reject.
 * An approved request books every working day in the range as "Urlaub".
 */
class LeaveController
{
    private const MAX_COMMENT_LENGTH = 255;

    private Database $database;

    public function __construct(Database $database)
    {
        $this->database = $database;
    }

    /** The employee's own requests plus this year's vacation figures. */
    public function listOwn(int $userId): void
    {
        HelperFunctions::json([
            'vacation' => $this->database->vacationFigures($userId, (int) date('Y')),
            'requests' => $this->database->listLeave($userId),
        ]);
    }

    public function create(int $userId, array $body): void
    {
        $start = UserController::parseDate($body['start_date'] ?? null, 'start_date');
        $end = UserController::parseDate($body['end_date'] ?? null, 'end_date');
        if ($end < $start) {
            throw new HttpException(422, 'The last day must not be before the first day');
        }
        if ($start->format('Y') !== $end->format('Y')) {
            throw new HttpException(422, 'Split requests that cross the turn of the year');
        }
        if ($start < new DateTimeImmutable('today')) {
            throw new HttpException(422, 'Vacation cannot start in the past');
        }

        $comment = trim((string) ($body['comment'] ?? ''));
        if (mb_strlen($comment) > self::MAX_COMMENT_LENGTH) {
            throw new HttpException(422, 'Comment is too long');
        }

        $days = count(WorkTime::workingDays($start, $end));
        if ($days === 0) {
            throw new HttpException(422, 'The range contains no working days');
        }

        $figures = $this->database->vacationFigures($userId, (int) $start->format('Y'));
        if ($days > $figures['remaining']) {
            throw new HttpException(422, sprintf('Only %d vacation days left', $figures['remaining']));
        }
        if ($this->database->leaveOverlaps($userId, $start->format('Y-m-d'), $end->format('Y-m-d'))) {
            throw new HttpException(409, 'Another request already covers some of these days');
        }

        $id = $this->database->createLeave($userId, $start->format('Y-m-d'), $end->format('Y-m-d'), $days, $comment);
        HelperFunctions::json(['id' => $id, 'days' => $days, 'status' => 'pending'], 201);
    }

    /** Employees can withdraw their own requests while they are pending. */
    public function cancelOwn(int $userId, int $requestId): void
    {
        $request = $this->database->getLeave($requestId);
        if (!$request || (int) $request['employee_id'] !== $userId) {
            throw new HttpException(404, 'Request not found');
        }
        if ($request['status'] !== 'pending') {
            throw new HttpException(409, 'Only pending requests can be withdrawn');
        }
        $this->database->setLeaveStatus($requestId, 'cancelled', $userId, 'Zurückgezogen');
        HelperFunctions::json(['status' => 'cancelled']);
    }

    /** All requests for the admin, pending ones first. */
    public function listAll(?string $statusGroup): void
    {
        HelperFunctions::json($this->database->listLeave(null, $statusGroup));
    }

    /**
     * approve, reject, or cancel (an approved request). Approving books the days;
     * cancelling an approved request removes them again.
     */
    public function decide(int $adminId, int $requestId, string $decision, array $body): void
    {
        $request = $this->database->getLeave($requestId);
        if (!$request) {
            throw new HttpException(404, 'Request not found');
        }
        $note = trim((string) ($body['note'] ?? ''));
        if (mb_strlen($note) > self::MAX_COMMENT_LENGTH) {
            throw new HttpException(422, 'Note is too long');
        }

        $employeeId = (int) $request['employee_id'];
        $range = sprintf('%s bis %s', self::german($request['start_date']), self::german($request['end_date']));

        switch ($decision) {
            case 'approve':
                $this->requireStatus($request, 'pending');
                $this->database->transaction(function () use ($request, $requestId, $employeeId, $adminId, $note, $range) {
                    $start = new DateTimeImmutable($request['start_date']);
                    $end = new DateTimeImmutable($request['end_date']);
                    $existing = $this->database->getHoursBetween($employeeId, $request['start_date'], $request['end_date']);
                    foreach (WorkTime::workingDays($start, $end) as $day) {
                        $entry = $existing[$day->format('Y-m-d')] ?? null;
                        if ($entry !== null && $entry['start'] !== null) {
                            throw new HttpException(409, sprintf('Hours are already recorded on %s', $day->format('d.m.Y')));
                        }
                        $this->database->editLog($employeeId, 0.0, null, null, 0.0, 'Urlaub', $day->format('Y-m-d'), 'Urlaub (genehmigt)', 0);
                    }
                    $this->database->setLeaveStatus($requestId, 'approved', $adminId, $note);
                    $this->database->audit($adminId, $employeeId, 'leave_approved', "$range, {$request['days']} Tage");
                });
                break;

            case 'reject':
                $this->requireStatus($request, 'pending');
                $this->database->setLeaveStatus($requestId, 'rejected', $adminId, $note);
                $this->database->audit($adminId, $employeeId, 'leave_rejected', $range . ($note !== '' ? ": $note" : ''));
                break;

            case 'cancel':
                $this->requireStatus($request, 'approved');
                $this->database->transaction(function () use ($request, $requestId, $employeeId, $adminId, $note, $range) {
                    $this->database->deleteVacationDays($employeeId, $request['start_date'], $request['end_date']);
                    $this->database->setLeaveStatus($requestId, 'cancelled', $adminId, $note);
                    $this->database->audit($adminId, $employeeId, 'leave_cancelled', $range);
                });
                break;

            default:
                throw new HttpException(404, 'Not found');
        }

        HelperFunctions::json($this->database->getLeave($requestId));
    }

    private function requireStatus(array $request, string $status): void
    {
        if ($request['status'] !== $status) {
            throw new HttpException(409, "Only $status requests can be changed this way");
        }
    }

    private static function german(string $date): string
    {
        return DateTimeImmutable::createFromFormat('Y-m-d', $date)->format('d.m.Y');
    }
}
