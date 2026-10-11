<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Providers\FeedpleServiceProvider;
use Feedple\Sdk\Core\WebhookBridge;

echo "--- Benchmarking Laravel -> Supabase PostgreSQL Bridge ---\n";

$t0 = microtime(true);
$sdk = FeedpleServiceProvider::makeSdk('webhook');
$t1 = microtime(true);
echo "1. makeSdk (inc. PDO connection to Supabase): " . round($t1 - $t0, 3) . "s\n";

$secret = env('FEEDPLE_WEBHOOK_SECRET');
$bridge = new WebhookBridge($sdk, $secret);

$payload = json_encode([
    'request_id' => 'bench-real-1',
    'tenant_id' => '47221f46-cfd3-4ab2-8730-3a128dacbe73',
    'ir' => [
        'operation' => 'query',
        'table' => 'user',
        'fields' => [
            ['column' => 'user.id', 'expression' => 'COUNT', 'alias' => 'total_users']
        ]
    ]
]);

$ts = time();
$toSign = $ts . '.' . $payload;
$sig = hash_hmac('sha256', $toSign, $secret);
$headers = [
    'X-Feedple-Signature' => "t={$ts},v1={$sig}",
    'X-Feedple-Request-ID' => 'bench-real-1',
];

$t2 = microtime(true);
$res = $bridge->handle($payload, $headers);
$t3 = microtime(true);

echo "2. bridge->handle (SQL execution): " . round($t3 - $t2, 3) . "s\n";
echo "Response: " . json_encode($res['body']) . "\n";
echo "Total CLI time: " . round($t3 - $t0, 3) . "s\n";
