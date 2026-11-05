<?php

declare(strict_types=1);

require_once __DIR__ . '/../Models/Database.php';
require_once __DIR__ . '/../Models/WorkTime.php';
require_once __DIR__ . '/../Models/DayState.php';
require_once __DIR__ . '/AuthController.php';
require_once __DIR__ . '/../utils/HelperFunctions.php';
require_once __DIR__ . '/../utils/HttpException.php';

class AdminController
{
    private Database $database;

    public function __construct(Database $database)
    {
        $this->database = $database;
    }

    /**
     * Dashboard: who is working right now, this month's hours against the target,
     * and the pending vacation requests.
     */
    public function overview(): void
    {
        $date = date('Y-m-d');
        $now = date('H:i:s');
        $today = new DateTimeImmutable('today');
        $monthStart = $today->modify('first day of this month');
        $breaks = $this->database->getBreaksForDate($date);

        $board = [];
        $month = [];
        foreach ($this->database->getTodayAll($date) as $row) {
            $id = (int) $row['id'];
            $state = DayState::build($row, $breaks[$id] ?? [], $now);
            $board[] = ['id' => $id, 'first_name' => $row['first_name'], 'last_name' => $row['last_name']] + $state;

            $entries = $this->database->getHoursBetween($id, $monthStart->format('Y-m-d'), $date);
            if (in_array($state['status'], ['working', 'paused'], true)) {
                $entries[$date] = ['actual' => $state['worked_hours'], 'type' => 'Normal'];
            }
            $balance = WorkTime::balance($entries, $monthStart, $today, (float) $row['weekly_hours']);
            $month[] = [
                'id' => $id,
                'first_name' => $row['first_name'],
                'last_name' => $row['last_name'],
                'hours' => $balance['actual'],
                'target_to_date' => $balance['target'],
            ];
        }

        $pending = $this->database->listLeave(null, 'pending');
        HelperFunctions::json([
            'date' => $date,
            'now' => $now,
            'board' => $board,
            'month' => $month,
            'pending_leave' => $pending,
        ]);
    }

    public function listEmployees(): void
    {
        HelperFunctions::json($this->database->listEmployees((int) date('Y'), (int) date('n')));
    }

    public function createEmployee(int $adminId, array $body): void
    {
        $data = $this->validateEmployee($body, true);
        AuthController::assertPasswordStrength((string) ($body['password'] ?? ''));
        if ($this->database->usernameTaken($data['username'])) {
            throw new HttpException(409, 'Username is already taken');
        }

        $id = $this->database->createEmployee($data, password_hash((string) $body['password'], PASSWORD_DEFAULT));
        $this->database->audit($adminId, $id, 'employee_created', sprintf(
            '%s %s (%s), %s h/Woche, %d Urlaubstage%s',
            $data['first_name'],
            $data['last_name'],
            $data['username'],
            self::hours($data['weekly_hours']),
            $data['vacation_days'],
            $data['is_admin'] ? ', Admin' : ''
        ));
        HelperFunctions::json($this->database->getEmployee($id), 201);
    }

    /**
     * Changes the given fields. A new password logs the employee out everywhere.
     */
    public function updateEmployee(int $adminId, int $id, array $body): void
    {
        $before = $this->database->getEmployee($id);
        if (!$before) {
            throw new HttpException(404, 'Employee not found');
        }

        $fields = $this->validateEmployee($body, false);
        if ($id === $adminId && ((isset($fields['active']) && !$fields['active']) || (isset($fields['is_admin']) && !$fields['is_admin']))) {
            throw new HttpException(422, 'You cannot deactivate yourself or remove your own admin role');
        }
        if (isset($fields['username']) && $this->database->usernameTaken($fields['username'], $id)) {
            throw new HttpException(409, 'Username is already taken');
        }

        $changes = [];
        foreach ($fields as $field => $value) {
            $same = $field === 'weekly_hours'
                ? abs((float) $before[$field] - (float) $value) < 0.001
                : (string) $before[$field] === (string) (is_bool($value) ? (int) $value : $value);
            if (!$same) {
                $changes[] = self::describeChange($field, $before[$field], $value);
            }
        }

        $this->database->transaction(function () use ($id, $fields, $body, &$changes) {
            $this->database->updateEmployee($id, $fields);
            if (isset($body['password']) && $body['password'] !== '') {
                AuthController::assertPasswordStrength((string) $body['password']);
                $this->database->setPassword($id, password_hash((string) $body['password'], PASSWORD_DEFAULT));
                $changes[] = 'Passwort zurückgesetzt';
            }
            if (isset($fields['active']) && !$fields['active']) {
                $this->database->bumpTokenVersion($id);
            }
        });

        if ($changes) {
            $this->database->audit($adminId, $id, 'employee_updated', implode(', ', $changes));
        }
        HelperFunctions::json($this->database->getEmployee($id));
    }

    public function auditLog(): void
    {
        HelperFunctions::json($this->database->listAudit(200));
    }

    /**
     * Checks the employee fields. On create every field is required,
     * on update only the fields that are sent are checked.
     */
    private function validateEmployee(array $body, bool $create): array
    {
        $data = [];
        $has = static fn(string $key) => $create || array_key_exists($key, $body);

        if ($has('username')) {
            $username = trim((string) ($body['username'] ?? ''));
            if (!preg_match('/^[A-Za-z0-9]{3,50}$/', $username)) {
                throw new HttpException(422, 'Username must be 3 to 50 letters or digits');
            }
            $data['username'] = $username;
        }
        foreach (['first_name' => 'First name', 'last_name' => 'Last name'] as $key => $label) {
            if ($has($key)) {
                $value = trim((string) ($body[$key] ?? ''));
                if ($value === '' || mb_strlen($value) > 50) {
                    throw new HttpException(422, "$label must be 1 to 50 characters");
                }
                $data[$key] = $value;
            }
        }
        if ($has('weekly_hours')) {
            $hours = $body['weekly_hours'] ?? null;
            if (!is_numeric($hours) || $hours < 1 || $hours > 60) {
                throw new HttpException(422, 'Weekly hours must be between 1 and 60');
            }
            $data['weekly_hours'] = round((float) $hours, 2);
        }
        if ($has('vacation_days')) {
            $days = $body['vacation_days'] ?? null;
            if (!is_numeric($days) || (int) $days != $days || $days < 0 || $days > 60) {
                throw new HttpException(422, 'Vacation days must be a whole number between 0 and 60');
            }
            $data['vacation_days'] = (int) $days;
        }
        foreach (['is_admin', 'active'] as $flag) {
            if ($flag === 'active' && $create) {
                continue;
            }
            if ($has($flag)) {
                $data[$flag] = (bool) ($body[$flag] ?? false);
            }
        }
        return $data;
    }

    private static function describeChange(string $field, $old, $new): string
    {
        $labels = [
            'username' => 'Benutzername',
            'first_name' => 'Vorname',
            'last_name' => 'Nachname',
            'weekly_hours' => 'Wochenstunden',
            'vacation_days' => 'Urlaubstage',
            'is_admin' => 'Admin',
            'active' => 'Aktiv',
        ];
        $format = static function ($value) use ($field) {
            if ($field === 'is_admin' || $field === 'active') {
                return $value ? 'ja' : 'nein';
            }
            return $field === 'weekly_hours' ? self::hours($value) : (string) $value;
        };
        return sprintf('%s: %s → %s', $labels[$field], $format($old), $format($new));
    }

    private static function hours($value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2, ',', ''), '0'), ',');
    }
}
