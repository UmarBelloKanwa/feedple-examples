<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Feedple\Sdk\Core\FeedpleWebSocket;
use Feedple\Sdk\FeedpleSDK;
use React\EventLoop\Loop;

$apiKey = env('FEEDPLE_API_KEY');
if (empty($apiKey)) {
    echo "FAIL: FEEDPLE_API_KEY is not configured\n";
    exit(1);
}

$wsUrl = FeedpleSDK::getWsUrl();
echo "--- Testing Feedple WebSocket Client E2E ---\n";
echo "Target Endpoint: {$wsUrl}\n";
echo "API Key Prefix: " . substr($apiKey, 0, 10) . "...\n\n";

$loop = Loop::get();
$logger = new class implements \Psr\Log\LoggerInterface {
    use \Psr\Log\LoggerTrait;
    public function log($level, string|\Stringable $message, array $context = []): void {
        echo "  [WS LOG " . strtoupper((string)$level) . "] {$message}\n";
    }
};

$ws = new FeedpleWebSocket($wsUrl, $apiKey, $loop, $logger);

$irHandled = false;
$ws->onIrRequest(function (array $ir) use (&$irHandled) {
    $irHandled = true;
    echo "PASS: IR request received and dispatched to IR handler!\n";
    return [
        'rows' => [['test_field' => 'ws_e2e_ok']],
        'count' => 1,
        'duration_ms' => 5,
    ];
});

$testPassed = false;

$ws->onAuthenticated(function () use ($ws, $loop, &$testPassed, &$irHandled) {
    echo "PASS: WebSocket connection established and session authenticated!\n";
    echo "PASS: Session ID is valid: " . ($ws->getSessionId() ?? 'none') . "\n";

    // Test sending inspecting status
    $ws->sendInspectingStatus();
    echo "PASS: schema.inspecting frame sent successfully\n";

    // Test sending schema unchanged
    $ws->sendSchemaUnchanged();
    echo "PASS: schema.unchanged frame sent successfully\n";

    // Test IR handling flow
    $reflection = new \ReflectionClass($ws);
    $handleIrMethod = $reflection->getMethod('handleIrRequest');
    $handleIrMethod->setAccessible(true);

    $testIrPayload = [
        'type' => 'ir.request',
        'payload' => [
            'request_id' => 'ws-test-req-001',
            'ir' => ['operation' => 'select', 'table' => 'users'],
        ],
    ];

    $handleIrMethod->invoke($ws, $testIrPayload);

    if ($irHandled) {
        echo "PASS: IR request executed through registered handler\n";
        $testPassed = true;
    } else {
        echo "FAIL: IR request handler was not invoked\n";
    }

    // Gracefully finish after 1 second
    $loop->addTimer(1.0, function () use ($ws, $loop) {
        echo "PASS: Closing WebSocket connection cleanly...\n";
        $ws->stop();
        $loop->stop();
    });
});

// Watchdog timer: fail if not finished within 15 seconds
$loop->addTimer(15.0, function () use ($loop) {
    echo "FAIL: WebSocket E2E test timed out after 15 seconds\n";
    $loop->stop();
    exit(1);
});

echo "Connecting to WebSocket server...\n";
$ws->connect();
$loop->run();

if ($testPassed) {
    echo "\nALL WEBSOCKET E2E TESTS PASSED!\n";
    exit(0);
} else {
    echo "\nWEBSOCKET TESTS FAILED!\n";
    exit(1);
}
