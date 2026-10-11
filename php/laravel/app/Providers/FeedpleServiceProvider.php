<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Console\Command;
use Feedple\Sdk\FeedpleSDK;
use Feedple\Sdk\DbConfig;
use Feedple\Sdk\Core\Identity;
use Feedple\Sdk\Core\WebhookBridge;

class FeedpleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $configFile = config_path('feedple.php');
        if (file_exists($configFile)) {
            $this->mergeConfigFrom($configFile, 'feedple');
        }
    }

    protected static ?FeedpleSDK $cachedSdk = null;

    /**
     * Factory method to build and initialize the Feedple SDK instance.
     *
     * @param string|null $transport Transport mode ('webhook' or 'websocket'). Defaults to env FEEDPLE_TRANSPORT or 'webhook'.
     */
    public static function makeSdk(?string $transport = null): ?FeedpleSDK
    {
        if (static::$cachedSdk !== null) {
            return static::$cachedSdk;
        }

        $apiKey = config('feedple.api_key')
            ?? env('FEEDPLE_API_KEY')
            ?: ($_ENV['FEEDPLE_API_KEY'] ?? getenv('FEEDPLE_API_KEY') ?: null);

        if (!$apiKey || $apiKey === 'sk_live_your_actual_api_key' || $apiKey === 'sk_live_your_workspace_api_key_here') {
            return null;
        }

        $defaultConn = config('database.default', env('DB_CONNECTION', 'sqlite'));
        $connConfig = config("database.connections.{$defaultConn}", []);
        $dbDriver = $connConfig['driver'] ?? $defaultConn;

        if ($dbDriver === 'mysql') {
            $dbConfig = DbConfig::mysql(
                host: $connConfig['host'] ?? env('DB_HOST', '127.0.0.1'),
                database: $connConfig['database'] ?? env('DB_DATABASE', 'laravel'),
                username: $connConfig['username'] ?? env('DB_USERNAME', 'root'),
                password: $connConfig['password'] ?? env('DB_PASSWORD', ''),
                port: (int) ($connConfig['port'] ?? env('DB_PORT', 3306))
            );
        } elseif ($dbDriver === 'pgsql' || $dbDriver === 'postgres') {
            $sslmode = $connConfig['sslmode'] ?? env('DB_SSLMODE', 'prefer');
            if (empty($sslmode) || $sslmode === 'prefer') {
                if (str_contains($connConfig['host'] ?? '', 'supabase.co') || str_contains(env('DB_HOST', ''), 'supabase.co')) {
                    $sslmode = 'require';
                }
            }
            $options = [\PDO::ATTR_PERSISTENT => true];
            if (!empty($sslmode)) {
                $options['sslmode'] = $sslmode;
            }
            $dbConfig = DbConfig::pgsql(
                host: $connConfig['host'] ?? env('DB_HOST', '127.0.0.1'),
                database: $connConfig['database'] ?? env('DB_DATABASE', 'postgres'),
                username: $connConfig['username'] ?? env('DB_USERNAME', 'postgres'),
                password: $connConfig['password'] ?? env('DB_PASSWORD', ''),
                port: (int) ($connConfig['port'] ?? env('DB_PORT', 5432)),
                options: $options
            );
        } else {
            $dbPath = $connConfig['database'] ?? env('DB_DATABASE', database_path('database.sqlite'));
            if (!str_starts_with((string) $dbPath, '/')) {
                $dbPath = base_path((string) $dbPath);
            }
            $dbConfig = DbConfig::sqlite(path: (string) $dbPath);
        }

        $identity = new Identity(
            name: 'laravel-app',
            allTables: true,
        );

        $selectedTransport = $transport
            ?? config('feedple.transport')
            ?? env('FEEDPLE_TRANSPORT', 'webhook');

        return static::$cachedSdk = new FeedpleSDK(
            apiKey: $apiKey,
            dbConfig: $dbConfig,
            identity: $identity,
            transport: $selectedTransport
        );
    }

    public function boot(): void
    {
        // 1. Auto-register the autonomous HTTP bridge endpoint
        $bridgePath = config('feedple.bridge_path')
            ?? env('FEEDPLE_BRIDGE_PATH', '/api/feedple/bridge');

        Route::post($bridgePath, function (Request $request) {
            $t0 = microtime(true);
            $sdk = static::makeSdk('webhook');
            $t1 = microtime(true);
            if (!$sdk) {
                return response()->json([
                    'status' => 'error',
                    'code'   => 'SDK_NOT_CONFIGURED',
                    'reason' => 'Feedple SDK is not configured (missing FEEDPLE_API_KEY)',
                ], 500);
            }

            $secret = config('feedple.webhook_secret')
                ?? env('FEEDPLE_WEBHOOK_SECRET')
                ?: ($_ENV['FEEDPLE_WEBHOOK_SECRET'] ?? getenv('FEEDPLE_WEBHOOK_SECRET') ?: null);
            $bridge = new WebhookBridge($sdk, $secret);

            $rawBody = (string) $request->getContent();
            $headers = $request->headers->all();

            $result = $bridge->handle($rawBody, $headers);
            $t2 = microtime(true);
            error_log(sprintf("FEEDPLE_BRIDGE_TIMING: makeSdk=%.3fs handle=%.3fs total=%.3fs", $t1 - $t0, $t2 - $t1, $t2 - $t0));

            return response()->json($result['body'], $result['status_code']);
        })->withoutMiddleware([
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
        ]);

        // Health check endpoint for testing bridge reachability
        Route::get($bridgePath, function () {
            $apiKey = config('feedple.api_key') ?? env('FEEDPLE_API_KEY');
            $configured = !empty($apiKey) && $apiKey !== 'sk_live_your_actual_api_key' && $apiKey !== 'sk_live_your_workspace_api_key_here';
            $secret = config('feedple.webhook_secret') ?? env('FEEDPLE_WEBHOOK_SECRET');
            $secretSet = !empty($secret) && $secret !== 'whsec_your_webhook_signing_secret_here';

            return response()->json([
                'status'     => 'active',
                'service'    => 'Feedple Webhook Bridge',
                'transport'  => config('feedple.transport') ?? env('FEEDPLE_TRANSPORT', 'webhook'),
                'configured' => $configured,
                'secret_set' => $secretSet,
            ]);
        });

        // 2. Register CLI artisan commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                FeedpleSyncCommand::class,
                FeedpleStartCommand::class,
                FeedpleStopCommand::class,
                FeedpleStatusCommand::class,
            ]);
        }
    }
}

/**
 * Artisan Command: php artisan feedple:sync
 */
class FeedpleSyncCommand extends Command
{
    protected $signature = 'feedple:sync {--force : Force schema re-sync even if hash matches} {--bridge-url= : Override the webhook bridge URL to register with Feedple Cloud}';
    protected $description = 'Perform a one-shot database schema synchronization to Feedple Cloud over HTTPS';

    public function handle(): int
    {
        $this->info('Starting Feedple schema synchronization...');

        try {
            $sdk = FeedpleServiceProvider::makeSdk('webhook');

            if (!$sdk) {
                $this->error('FEEDPLE_API_KEY is not configured in .env file.');
                return Command::FAILURE;
            }

            $apiBaseUrl = config('feedple.api_base_url') ?? FeedpleSDK::getApiBaseUrl();
            $this->line('<comment>Transport:</comment> Stateless HTTPS Webhook');
            $this->line('<comment>API Endpoint:</comment> ' . rtrim($apiBaseUrl, '/') . '/tenants/schema');

            $force = (bool) $this->option('force');
            $customBridgeUrl = $this->option('bridge-url');

            // Auto-resolve Webhook Bridge URL
            $bridgeUrl = null;
            if (!empty($customBridgeUrl)) {
                $bridgeUrl = (string) $customBridgeUrl;
            } else {
                $appUrl = config('app.url') ?? env('APP_URL', '');
                $bridgePath = config('feedple.bridge_path') ?? env('FEEDPLE_BRIDGE_PATH', '/api/feedple/bridge');
                if (!empty($appUrl) && filter_var($appUrl, FILTER_VALIDATE_URL)) {
                    $bridgeUrl = rtrim((string) $appUrl, '/') . '/' . ltrim((string) $bridgePath, '/');
                }
            }

            if (!empty($bridgeUrl)) {
                $this->line('<comment>Bridge URL:</comment> ' . $bridgeUrl);
            }

            $startTime = microtime(true);
            $result = $sdk->syncSchemaHttp(apiBaseUrl: $apiBaseUrl, force: $force, webhookUrl: $bridgeUrl);
            $elapsed = round(microtime(true) - $startTime, 2);

            if ($result['skipped']) {
                $this->info("Schema is up-to-date (unchanged). Verified {$result['tables_count']} tables in {$elapsed}s.");
            } else {
                $this->info("Schema synchronized successfully! Version: {$result['schema_version']} ({$result['tables_count']} tables) in {$elapsed}s.");
            }

            if (!empty($result['registered_webhook_url'])) {
                $this->line("<info>✓ Registered Webhook Bridge:</info> {$result['registered_webhook_url']}");
            }

            if (!empty($result['webhook_secret'])) {
                $existingSecret = config('feedple.webhook_secret') ?? env('FEEDPLE_WEBHOOK_SECRET');
                if (empty($existingSecret) || $existingSecret === 'whsec_your_webhook_signing_secret_here') {
                    $envPath = base_path('.env');
                    $autoUpdated = false;
                    if (file_exists($envPath) && is_writable($envPath)) {
                        $envContent = (string) file_get_contents($envPath);
                        if (str_contains($envContent, 'FEEDPLE_WEBHOOK_SECRET=')) {
                            $envContent = (string) preg_replace(
                                '/^FEEDPLE_WEBHOOK_SECRET=.*$/m',
                                'FEEDPLE_WEBHOOK_SECRET=' . $result['webhook_secret'],
                                $envContent
                            );
                        } else {
                            $envContent .= "\nFEEDPLE_WEBHOOK_SECRET=" . $result['webhook_secret'] . "\n";
                        }
                        file_put_contents($envPath, $envContent);
                        $autoUpdated = true;
                    }

                    if ($autoUpdated) {
                        $this->line("<info>✓ Saved Webhook Secret to .env:</info> {$result['webhook_secret']}");
                    } else {
                        $this->newLine();
                        $this->warn('! Action Required: Webhook Secret auto-generated by Feedple Cloud.');
                        $this->line("  Please add the following to your .env file:");
                        $this->line("  <comment>FEEDPLE_WEBHOOK_SECRET={$result['webhook_secret']}</comment>");
                        $this->newLine();
                    }
                }
            }

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Failed to synchronize schema: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}

/**
 * Artisan Command: php artisan feedple:start
 */
class FeedpleStartCommand extends Command
{
    protected $signature = 'feedple:start {--tail : Stream worker logs in real time}';
    protected $description = 'Start the Feedple background worker daemon (WebSocket dev mode)';

    public function handle(): int
    {
        $this->info('Initializing Feedple SDK in WebSocket mode...');

        try {
            $sdk = FeedpleServiceProvider::makeSdk('websocket');

            if (!$sdk) {
                $this->error('FEEDPLE_API_KEY is not configured in .env file.');
                return Command::FAILURE;
            }

            $this->info('Feedple worker started (or already active).');

            $logFile = sys_get_temp_dir() . '/feedple-sdk.log';
            $this->line("<comment>Log File:</comment> {$logFile}");

            if ($this->option('tail')) {
                if (!file_exists($logFile)) {
                    touch($logFile);
                }
                $this->info("Tailing {$logFile} (Press Ctrl+C to stop)...");
                passthru("tail -f " . escapeshellarg($logFile));
            }

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Failed to start Feedple worker: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}

/**
 * Artisan Command: php artisan feedple:stop
 */
class FeedpleStopCommand extends Command
{
    protected $signature = 'feedple:stop';
    protected $description = 'Stop the Feedple background worker process';

    public function handle(): int
    {
        $this->info('Stopping Feedple background worker...');

        try {
            $sdk = FeedpleServiceProvider::makeSdk('websocket');
            if ($sdk) {
                $sdk->stop();
            }

            $pidFile = sys_get_temp_dir() . '/feedple-sdk.pid';
            if (file_exists($pidFile)) {
                $pid = (int) trim((string) file_get_contents($pidFile));
                if ($pid > 0) {
                    exec("kill -9 {$pid} 2>/dev/null");
                }
                @unlink($pidFile);
            }

            $this->info('Feedple background worker stopped.');
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Failed to stop Feedple worker: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}

/**
 * Artisan Command: php artisan feedple:status
 */
class FeedpleStatusCommand extends Command
{
    protected $signature = 'feedple:status {--lines=15 : Number of log lines to show}';
    protected $description = 'Check the status of the Feedple background worker and view recent logs';

    public function handle(): int
    {
        $this->info('--- Feedple Worker Status ---');

        $apiKey = config('feedple.api_key') ?? env('FEEDPLE_API_KEY');
        if (!$apiKey || $apiKey === 'sk_live_your_actual_api_key' || $apiKey === 'sk_live_your_workspace_api_key_here') {
            $this->warn('API Key: Not configured or using placeholder in .env');
        } else {
            $maskedKey = substr((string) $apiKey, 0, 7) . '...' . substr((string) $apiKey, -4);
            $this->line("<comment>API Key:</comment> {$maskedKey}");
        }

        $transport = config('feedple.transport') ?? env('FEEDPLE_TRANSPORT', 'webhook');
        $this->line("<comment>Configured Transport:</comment> {$transport}");

        $defaultConn = config('database.default', env('DB_CONNECTION', 'sqlite'));
        $this->line("<comment>Database Connection:</comment> {$defaultConn}");

        $tempDir = sys_get_temp_dir();
        $pidFile = $tempDir . '/feedple-sdk.pid';
        $logFile = $tempDir . '/feedple-sdk.log';

        $isRunning = false;
        $pid = null;

        if (file_exists($pidFile)) {
            $pid = (int) trim((string) file_get_contents($pidFile));
            if ($pid > 0) {
                if (PHP_OS_FAMILY === 'Windows') {
                    $out = shell_exec(sprintf('tasklist /FI "PID eq %d" 2>NUL', $pid));
                    $isRunning = $out !== null && str_contains($out, (string) $pid);
                } else {
                    exec(sprintf('kill -0 %d 2>/dev/null', $pid), result_code: $exitCode);
                    $isRunning = ($exitCode === 0);
                }
            }
        }

        if ($isRunning && $pid) {
            $this->info("Worker Status: RUNNING (PID: {$pid})");
        } else {
            $this->line("Worker Status: STOPPED (not required in webhook mode)");
        }

        $this->line("<comment>Log File:</comment> {$logFile}");

        if (file_exists($logFile)) {
            $lines = max(1, (int) $this->option('lines'));
            $this->line("<comment>Recent Logs (last {$lines} lines):</comment>");
            $content = file($logFile);
            if ($content !== false && count($content) > 0) {
                $slice = array_slice($content, -$lines);
                foreach ($slice as $logLine) {
                    $this->output->write($logLine);
                }
            } else {
                $this->line('  (log file is empty)');
            }
        } else {
            $this->line('  (no log file found yet)');
        }

        return Command::SUCCESS;
    }
}
