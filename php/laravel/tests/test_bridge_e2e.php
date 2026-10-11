<?php

declare(strict_types=1);

@unlink(__DIR__ . '/../bootstrap/cache/config.php');

// Set local sqlite for test environment before Laravel boot
$sqliteDb = __DIR__ . '/test.sqlite';
putenv("DB_CONNECTION=sqlite");
$_ENV['DB_CONNECTION'] = 'sqlite';
putenv("DB_DATABASE={$sqliteDb}");
$_ENV['DB_DATABASE'] = $sqliteDb;

// Initialize sqlite test database
$pdo = new PDO("sqlite:{$sqliteDb}");
$pdo->exec("CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY, name TEXT);");
$pdo->exec("DELETE FROM users;");
$pdo->exec("INSERT INTO users (id, name) VALUES (1, 'Laravel Alice');");

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$secret = 'whsec_test_secret_for_laravel_123';
putenv("FEEDPLE_WEBHOOK_SECRET={$secret}");
$_ENV['FEEDPLE_WEBHOOK_SECRET'] = $secret;

function assertCondition(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
    echo "PASS: {$message}\n";
}

// 0. GET health check -> 200 active
$request0 = Illuminate\Http\Request::create('/api/feedple/bridge', 'GET');
$response0 = $kernel->handle($request0);
assertCondition($response0->getStatusCode() === 200, 'GET health check returns 200');
$healthBody = json_decode($response0->getContent(), true);
assertCondition($healthBody['status'] === 'active', 'Health check status is active');
assertCondition($healthBody['service'] === 'Feedple Webhook Bridge', 'Health check service identified');

// 1. Missing signature -> 401
$request1 = Illuminate\Http\Request::create(
    '/api/feedple/bridge',
    'POST',
    [],
    [],
    [],
    ['CONTENT_TYPE' => 'application/json'],
    json_encode(['request_id' => 'r1', 'ir' => ['operation' => 'query', 'table' => 'users']])
);
$response1 = $kernel->handle($request1);
assertCondition($response1->getStatusCode() === 401, 'Missing signature returns 401');

// 2. Invalid signature -> 401
$request2 = Illuminate\Http\Request::create(
    '/api/feedple/bridge',
    'POST',
    [],
    [],
    [],
    [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_FEEDPLE_SIGNATURE' => 't=' . time() . ',v1=bad_sig',
    ],
    json_encode(['request_id' => 'r2', 'ir' => ['operation' => 'query', 'table' => 'users']])
);
$response2 = $kernel->handle($request2);
assertCondition($response2->getStatusCode() === 401, 'Invalid signature returns 401');

// 3. Stale timestamp -> 401
$oldTs = time() - 400;
$payload3 = json_encode(['request_id' => 'r3', 'ir' => ['operation' => 'query', 'table' => 'users']]);
$sig3 = hash_hmac('sha256', $oldTs . '.' . $payload3, $secret);
$request3 = Illuminate\Http\Request::create(
    '/api/feedple/bridge',
    'POST',
    [],
    [],
    [],
    [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_FEEDPLE_SIGNATURE' => "t={$oldTs},v1={$sig3}",
    ],
    $payload3
);
$response3 = $kernel->handle($request3);
assertCondition($response3->getStatusCode() === 401, 'Stale timestamp returns 401');

// 4. Valid signature with valid IR -> 200 success
$ts4 = time();
$payload4 = json_encode([
    'request_id' => 'r4-valid',
    'tenant_id' => 'tenant-test',
    'ir' => [
        'operation' => 'query',
        'table' => 'users',
        'fields' => [],
    ],
]);
$sig4 = hash_hmac('sha256', $ts4 . '.' . $payload4, $secret);
$request4 = Illuminate\Http\Request::create(
    '/api/feedple/bridge',
    'POST',
    [],
    [],
    [],
    [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_FEEDPLE_SIGNATURE' => "t={$ts4},v1={$sig4}",
        'HTTP_X_FEEDPLE_REQUEST_ID' => 'r4-valid',
    ],
    $payload4
);
$response4 = $kernel->handle($request4);
assertCondition($response4->getStatusCode() === 200, 'Valid signed IR request returns 200');

$body4 = json_decode($response4->getContent(), true);
assertCondition($body4['status'] === 'success', 'Response status is success');
assertCondition($body4['request_id'] === 'r4-valid', 'Response includes correlation request_id');
assertCondition(count($body4['rows']) === 1, 'Query executed and returned 1 row');
assertCondition($body4['rows'][0]['name'] === 'Laravel Alice', 'Row data contains Laravel Alice');

// 5. Missing IR -> 422
$ts5 = time();
$payload5 = json_encode(['request_id' => 'r5-no-ir']);
$sig5 = hash_hmac('sha256', $ts5 . '.' . $payload5, $secret);
$request5 = Illuminate\Http\Request::create(
    '/api/feedple/bridge',
    'POST',
    [],
    [],
    [],
    [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_FEEDPLE_SIGNATURE' => "t={$ts5},v1={$sig5}",
    ],
    $payload5
);
$response5 = $kernel->handle($request5);
assertCondition($response5->getStatusCode() === 422, 'Missing IR payload returns 422');

// Cleanup
@unlink($sqliteDb);

echo "ALL LARAVEL BRIDGE TESTS PASSED!\n";
