<?php

declare(strict_types=1);

// Dependency-free unit tests.  Run:  php tests/run.php

$root = dirname(__DIR__);
require_once $root . '/Models/Holidays.php';
require_once $root . '/Models/WorkTime.php';
require_once $root . '/Models/JwtHandler.php';
require_once $root . '/utils/Router.php';

$failures = 0;
$passed = 0;

function check(string $name, $expected, $actual): void
{
    global $failures, $passed;
    if ($expected === $actual) {
        $passed++;
        return;
    }
    $failures++;
    echo "FAIL  $name\n      expected: " . var_export($expected, true) . "\n      actual:   " . var_export($actual, true) . "\n";
}

function jwtConfig(array $overrides = []): array
{
    return $overrides + [
        'header' => ['alg' => 'HS256', 'typ' => 'JWT'],
        'issuer' => 'workforce',
        'audience' => 'workforce-app',
        'ttl' => 3600,
        'secret' => 'test-secret',
    ];
}

// --- Holidays -------------------------------------------------------

$easter = [2000 => '2000-04-23', 2019 => '2019-04-21', 2023 => '2023-04-09', 2024 => '2024-03-31', 2025 => '2025-04-20', 2026 => '2026-04-05'];
foreach ($easter as $year => $date) {
    check("Easter Sunday $year", $date, Holidays::easterSunday($year)->format('Y-m-d'));
}

// The original hand-written list for 2023 was correct; the computed list must match it.
check('holidays 2023 match the original list', [
    '2023-01-01', '2023-01-06', '2023-04-07', '2023-04-10', '2023-05-01', '2023-05-18',
    '2023-05-29', '2023-06-08', '2023-10-03', '2023-11-01', '2023-12-25', '2023-12-26',
], array_keys(Holidays::forYear(2023)));

// The original 2024 entries were wrong (Good Friday was 29 March, not 18 April).
check('Good Friday 2024', true, Holidays::isHoliday(new DateTimeImmutable('2024-03-29')));
check('18 April 2024 is a normal day', false, Holidays::isHoliday(new DateTimeImmutable('2024-04-18')));

// --- WorkTime -------------------------------------------------------

check('8.5 hours minus 30 minutes break', 8.0, WorkTime::actualHours('08:00', '16:30', 30));
check('night shift past midnight', 7.5, WorkTime::actualHours('22:00', '06:00', 30));
check('seconds are ignored', 8.0, WorkTime::actualHours('08:00:59', '16:30:00', 30));
check('missing end time', 0.0, WorkTime::actualHours('08:00', null, 30));
check('break longer than the shift', 0.0, WorkTime::actualHours('08:00', '08:20', 30));
check('invalid time', null, WorkTime::toMinutes('25:00'));

$monday = new DateTimeImmutable('2026-11-02');
$saturday = new DateTimeImmutable('2026-11-07');
$unityDay = new DateTimeImmutable('2025-10-03'); // Friday, public holiday
check('full-time target on a weekday', 8.0, WorkTime::targetHours($monday));
check('part-time target (30 h per week)', 6.0, WorkTime::targetHours($monday, 30.0));
check('no target on Saturday', 0.0, WorkTime::targetHours($saturday));
check('no target on a holiday', 0.0, WorkTime::targetHours($unityDay));
check('overtime above target', 1.5, WorkTime::overtime(9.5, $monday));
check('no negative overtime', 0.0, WorkTime::overtime(6.0, $monday));
check('all weekend hours are overtime', 4.0, WorkTime::overtime(4.0, $saturday));
check('part-time overtime', 1.0, WorkTime::overtime(7.0, $monday, 30.0));

// --- JwtHandler -----------------------------------------------------

$jwt = new JwtHandler(jwtConfig());
$token = $jwt->generate(['id' => 7, 'name' => 'Max', 'admin' => false]);
$claims = $jwt->decode($token);
check('round trip keeps claims', [7, 'Max', false], [$claims['id'] ?? null, $claims['name'] ?? null, $claims['admin'] ?? null]);
check('is_valid on a fresh token', true, $jwt->is_valid($token));

[$h, $p, $s] = explode('.', $token);
$forged = rtrim(strtr(base64_encode(json_encode(['id' => 1, 'admin' => true] + $claims)), '+/', '-_'), '=');
check('changed payload is rejected', null, $jwt->decode("$h.$forged.$s"));
check('wrong secret is rejected', null, (new JwtHandler(jwtConfig(['secret' => 'other'])))->decode($token));
check('wrong audience is rejected', null, (new JwtHandler(jwtConfig(['audience' => 'other'])))->decode($token));
check('expired token is rejected', null, $jwt->decode((new JwtHandler(jwtConfig(['ttl' => -10])))->generate(['id' => 7])));
check('two-part token is rejected', null, $jwt->decode("$h.$p"));
check('garbage is rejected', null, $jwt->decode('not-a-token'));

$noneHeader = rtrim(strtr(base64_encode('{"alg":"none","typ":"JWT"}'), '+/', '-_'), '=');
check('alg "none" is rejected', null, $jwt->decode("$noneHeader.$p."));

// Regression: the old decoder used plain base64 and broke on "-" and "_".
$urlSafeFailures = 0;
for ($i = 0; $i < 200; $i++) {
    $t = $jwt->generate(['id' => $i, 'name' => str_repeat('?>~', $i % 7) . "ü$i"]);
    if ($jwt->decode($t) === null) {
        $urlSafeFailures++;
    }
}
check('tokens with URL-safe base64 characters verify', 0, $urlSafeFailures);

// --- Router ---------------------------------------------------------

$router = new Router();
$hits = [];
$router->get('/api/ping', function () use (&$hits) { $hits[] = 'get'; });
$router->post('/api/ping', function () use (&$hits) { $hits[] = 'post'; });
$router->dispatch('GET', '/api/ping/');
$router->dispatch('post', 'api/ping');
check('router dispatches by method and path', ['get', 'post'], $hits);

$status = static function (Router $router, string $method, string $path): ?int {
    try {
        $router->dispatch($method, $path);
        return null;
    } catch (HttpException $e) {
        return $e->getStatus();
    }
};
check('unknown path is 404', 404, $status($router, 'GET', '/api/missing'));
check('wrong method is 405', 405, @$status($router, 'DELETE', '/api/ping'));

// --------------------------------------------------------------------

echo $failures === 0 ? "OK  $passed checks passed\n" : "\n$failures failed, $passed passed\n";
exit($failures === 0 ? 0 : 1);
