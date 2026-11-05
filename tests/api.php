<?php

declare(strict_types=1);

// Integration tests against a running server with freshly seeded demo data:
//   mysql -u root < database/schema.sql && php database/seed.php
//   php -S localhost:8000 -t public public/router.php
//   php tests/api.php            (or BASE_URL=http://... php tests/api.php)
// The tests change data, so seed again before the next run.

require_once dirname(__DIR__) . '/Models/WorkTime.php';

date_default_timezone_set(getenv('APP_TIMEZONE') ?: 'Europe/Berlin'); // same "today" as the server
$base = rtrim(getenv('BASE_URL') ?: 'http://127.0.0.1:8000', '/');
$isWorkingDay = WorkTime::targetHours(new DateTimeImmutable('today')) > 0;
$failures = 0;
$passed = 0;

function check(string $name, bool $condition, string $detail = ''): void
{
    global $failures, $passed;
    if ($condition) {
        $passed++;
        return;
    }
    $failures++;
    echo "FAIL  $name" . ($detail !== '' ? "\n      $detail" : '') . "\n";
}

/**
 * @return array{status: int, body: mixed, raw: string, headers: string}
 */
function request(string $method, string $path, $body = null, ?string $token = null, ?string $cookieJar = null, bool $form = false): array
{
    global $base;
    $curl = curl_init($base . $path);
    $headers = [];
    if ($token !== null) {
        $headers[] = "Authorization: Bearer $token";
    }
    if ($body !== null) {
        if ($form) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($body));
        } else {
            $headers[] = 'Content-Type: application/json';
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($body));
        }
    }
    if ($cookieJar !== null) {
        curl_setopt($curl, CURLOPT_COOKIEJAR, $cookieJar);
        curl_setopt($curl, CURLOPT_COOKIEFILE, $cookieJar);
    }
    curl_setopt_array($curl, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false,
    ]);
    $response = curl_exec($curl);
    $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $headerSize = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
    curl_close($curl);

    $raw = substr((string) $response, $headerSize);
    return [
        'status' => $status,
        'headers' => substr((string) $response, 0, $headerSize),
        'raw' => $raw,
        'body' => json_decode($raw, true),
    ];
}

/** Logs in through the form and collects the token, like the browser does. Returns null on failure. */
function login(string $username, string $password): ?string
{
    $jar = tempnam(sys_get_temp_dir(), 'wf');
    $response = request('POST', '/api/login', ['username' => $username, 'password' => $password], null, $jar, true);
    if ($response['status'] !== 303 || str_contains($response['headers'], 'login.php')) {
        return null;
    }
    $token = request('GET', '/api/authenticate', null, null, $jar)['body']['jwt'] ?? null;
    unlink($jar);
    return $token;
}

// --- Login and permissions ----------------------------------------------

$max = login('max', 'demo1234');
$admin = login('admin', 'admin1234');
check('employee can log in', is_string($max));
check('admin can log in', is_string($admin));
check('wrong password is rejected', login('max', 'wrong') === null);

check('no token is 401', request('GET', '/api/user/today?id=2')['status'] === 401);
check("employee cannot read a colleague's data", request('GET', '/api/user/today?id=3', null, $max)['status'] === 403);
check('employee cannot open the admin API', request('GET', '/api/admin/overview', null, $max)['status'] === 403);
check('admin can read any employee', request('GET', '/api/user/today?id=3', null, $admin)['status'] === 200);

// --- Stamp clock with server-side breaks --------------------------------

$clock = static fn(string $action) => request('POST', '/api/user/clock', ['id' => 2, 'action' => $action], $max);
check('day starts idle', request('GET', '/api/user/today?id=2', null, $max)['body']['status'] === 'idle');
check('pause before start is refused', $clock('pause')['status'] === 409);
$state = $clock('start')['body'];
check('start: working', ($state['status'] ?? null) === 'working', json_encode($state));
check('second start is refused', $clock('start')['status'] === 409);
$state = $clock('pause')['body'];
check('pause: paused, break recorded', ($state['status'] ?? null) === 'paused' && count($state['breaks']) === 1 && $state['break_started'] !== null);
check('pause while paused is refused', $clock('pause')['status'] === 409);
$state = $clock('resume')['body'];
check('resume: working, break closed', ($state['status'] ?? null) === 'working' && $state['breaks'][0]['end'] !== null);
$state = $clock('stop')['body'];
check('stop: done with end time', ($state['status'] ?? null) === 'done' && $state['end'] !== null, json_encode($state));
check('second stop is refused', $clock('stop')['status'] === 409);
check('unknown action is 422', $clock('lunch')['status'] === 422);

// --- Overview figures and year -------------------------------------------

$summary = request('GET', '/api/user/summary?id=2', null, $max)['body'];
check('summary has week, month, balance, vacation', isset($summary['week']['days'], $summary['month']['target'], $summary['overtime_balance'], $summary['vacation']['remaining']));
check('week has seven days', count($summary['week']['days'] ?? []) === 7);
check('vacation: 30 days, 14 taken, 5 pending', ($summary['vacation'] ?? []) == ['entitlement' => 30, 'taken' => 14, 'pending' => 5, 'remaining' => 11], json_encode($summary['vacation'] ?? null));

$year = (int) date('Y');
$months = request('GET', "/api/user/hours?id=2&year=$year", null, $max)['body'];
check('year view has twelve months with targets', count($months) === 12 && isset($months[0]['Target'], $months[0]['TargetToDate']));
check('August target is credited for 10 vacation days', $months[7]['AbsenceDays'] >= 10, json_encode($months[7]));

// --- Vacation requests ---------------------------------------------------

$monday = new DateTimeImmutable('monday next week +8 weeks');
$leave = static fn(array $body, ?string $token = null) => request('POST', '/api/user/leave', $body + ['id' => 2], $token ?? $max);
$tooMany = $leave(['start_date' => $monday->format('Y-m-d'), 'end_date' => $monday->modify('+9 weeks')->format('Y-m-d')]);
check('request beyond remaining days is refused', $tooMany['status'] === 422, $tooMany['raw']);
check('request in the past is refused', $leave(['start_date' => '2020-01-06', 'end_date' => '2020-01-07'])['status'] === 422);
check('end before start is refused', $leave(['start_date' => $monday->modify('+1 day')->format('Y-m-d'), 'end_date' => $monday->format('Y-m-d')])['status'] === 422);

$created = $leave(['start_date' => $monday->format('Y-m-d'), 'end_date' => $monday->modify('+1 day')->format('Y-m-d'), 'comment' => 'Umzug']);
$expectedDays = WorkTime::workingDays($monday, $monday->modify('+1 day'));
check('valid request counts working days', $created['status'] === 201 && ($created['body']['days'] ?? 0) === count($expectedDays), $created['raw']);
$firstDay = $expectedDays[0] ?? $monday;
$requestId = $created['body']['id'] ?? 0;
check('overlapping request is refused', $leave(['start_date' => $monday->format('Y-m-d'), 'end_date' => $monday->format('Y-m-d')])['status'] === 409);

$adminList = request('GET', '/api/admin/leave?status=pending', null, $admin)['body'];
check('admin sees the new pending request', in_array($requestId, array_column($adminList, 'id')));

$approved = request('POST', "/api/admin/leave/$requestId/approve", ['note' => 'Viel Erfolg'], $admin);
check('admin approves the request', ($approved['body']['status'] ?? null) === 'approved', $approved['raw']);
$monthRows = request('GET', sprintf('/api/user/hours?id=2&year=%d&month=%d', $firstDay->format('Y'), $firstDay->format('n')), null, $max)['body'];
$booked = array_filter($monthRows, static fn($row) => $row['type'] === 'Urlaub' && (int) $row['Day'] === (int) $firstDay->format('j'));
check('approval books the days as Urlaub', count($booked) === 1);
check('approving twice is refused', request('POST', "/api/admin/leave/$requestId/approve", [], $admin)['status'] === 409);

$cancelled = request('POST', "/api/admin/leave/$requestId/cancel", [], $admin);
check('admin cancels the approved request', ($cancelled['body']['status'] ?? null) === 'cancelled');
$monthRows = request('GET', sprintf('/api/user/hours?id=2&year=%d&month=%d', $firstDay->format('Y'), $firstDay->format('n')), null, $max)['body'];
check('cancelling removes the booked days', !array_filter($monthRows, static fn($row) => $row['type'] === 'Urlaub' && (int) $row['Day'] === (int) $firstDay->format('j')));

$second = $leave(['start_date' => $monday->format('Y-m-d'), 'end_date' => $monday->format('Y-m-d')]);
$secondId = $second['body']['id'] ?? 0;
check('employee withdraws a pending request', (request('POST', "/api/user/leave/$secondId/cancel", ['id' => 2], $max)['body']['status'] ?? null) === 'cancelled');
check("employee cannot withdraw a colleague's request", request('POST', '/api/user/leave/11/cancel', ['id' => 2], $max)['status'] === 404);

// --- Admin: overview, employees, activity log ------------------------------

$overview = request('GET', '/api/admin/overview', null, $admin)['body'];
check('overview lists every active employee', count($overview['board'] ?? []) === 6);
check('overview shows Max as done today', array_values(array_filter($overview['board'], static fn($row) => $row['id'] === 2))[0]['status'] === 'done');
if ($isWorkingDay) {
    check('overview shows Leyla on vacation', array_values(array_filter($overview['board'], static fn($row) => $row['id'] === 5))[0]['status'] === 'absent');
}

$newEmployee = ['username' => 'nina', 'first_name' => 'Nina', 'last_name' => 'Neumann', 'weekly_hours' => 25, 'vacation_days' => 28, 'is_admin' => false, 'password' => 'start12345'];
check('weak password is refused', request('POST', '/api/admin/employees', ['password' => 'short'] + $newEmployee, $admin)['status'] === 422);
check('invalid username is refused', request('POST', '/api/admin/employees', ['username' => 'n!'] + $newEmployee, $admin)['status'] === 422);
$createdEmployee = request('POST', '/api/admin/employees', $newEmployee, $admin);
check('admin creates an employee', $createdEmployee['status'] === 201, $createdEmployee['raw']);
$ninaId = $createdEmployee['body']['id'] ?? 0;
check('duplicate username is refused', request('POST', '/api/admin/employees', $newEmployee, $admin)['status'] === 409);
check('new employee can log in', is_string($nina = login('nina', 'start12345')));

$updated = request('PATCH', "/api/admin/employees/$ninaId", ['weekly_hours' => 30], $admin);
check('admin changes weekly hours', (float) ($updated['body']['weekly_hours'] ?? 0) === 30.0);
check('admin deactivates the employee', request('PATCH', "/api/admin/employees/$ninaId", ['active' => false], $admin)['status'] === 200);
check("deactivated employee's token stops working", request('GET', "/api/user/today?id=$ninaId", null, $nina)['status'] === 401);
check('deactivated employee cannot log in', login('nina', 'start12345') === null);
check('admin cannot deactivate themselves', request('PATCH', '/api/admin/employees/1', ['active' => false], $admin)['status'] === 422);
check('unknown employee is 404', request('PATCH', '/api/admin/employees/999', ['active' => true], $admin)['status'] === 404);
check('employee list includes the new account', count(request('GET', '/api/admin/employees', null, $admin)['body']) === 7);

$edit = request('POST', '/api/user/hours', ['id' => 2, 'date' => date('Y-m-d', strtotime('monday last week')), 'start_time' => '08:00', 'end_time' => '16:30', 'pause' => 30, 'type' => 'Normal', 'comment' => '=HYPERLINK("x")'], $admin);
check('admin edits an employee day', $edit['status'] === 200 && $edit['body']['actual_hours'] == 8);
$actions = array_column(request('GET', '/api/admin/audit', null, $admin)['body'], 'action');
foreach (['hours_edited', 'employee_created', 'employee_updated', 'leave_approved', 'leave_cancelled'] as $action) {
    check("activity log records $action", in_array($action, $actions, true));
}

// --- CSV export ------------------------------------------------------------

$lastWeek = new DateTimeImmutable('monday last week');
$csv = request('GET', sprintf('/api/user/export?id=2&year=%d&month=%d', $lastWeek->format('Y'), $lastWeek->format('n')), null, $max);
check('export is a CSV download', $csv['status'] === 200 && str_contains($csv['headers'], 'text/csv') && str_contains($csv['headers'], 'arbeitszeiten-max-'));
check('export starts with a BOM and the header row', str_starts_with($csv['raw'], "\xEF\xBB\xBFDatum;Wochentag;Beginn"));
check('export ends with a sum row', str_contains($csv['raw'], "\nSumme;"));
check('export neutralises formulas', str_contains($csv['raw'], "'=HYPERLINK") && !str_contains($csv['raw'], ';=HYPERLINK'));

// --- Password change and logout --------------------------------------------

check('wrong current password is refused', request('POST', '/api/user/password', ['id' => 2, 'current_password' => 'nope', 'new_password' => 'neuesPasswort1'], $max)['status'] === 422);
check("cannot change a colleague's password", request('POST', '/api/user/password', ['id' => 3, 'current_password' => 'demo1234', 'new_password' => 'neuesPasswort1'], $max)['status'] === 403);
$changed = request('POST', '/api/user/password', ['id' => 2, 'current_password' => 'demo1234', 'new_password' => 'neuesPasswort1'], $max);
check('password change returns a fresh token', $changed['status'] === 200 && isset($changed['body']['jwt']));
check('old token stops working after the change', request('GET', '/api/user/today?id=2', null, $max)['status'] === 401);
$max = $changed['body']['jwt'] ?? '';
check('fresh token works', request('GET', '/api/user/today?id=2', null, $max)['status'] === 200);
check('login with the new password works', is_string(login('max', 'neuesPasswort1')));

check('logout answers 204', request('POST', '/api/logout', null, $max)['status'] === 204);
check('token is revoked after logout', request('GET', '/api/user/today?id=2', null, $max)['status'] === 401);

echo $failures === 0 ? "OK  $passed API checks passed\n" : "\n$failures failed, $passed passed\n";
exit($failures === 0 ? 0 : 1);
