<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Console\Command;
use Feedple\Sdk\FeedpleSDK;
use Feedple\Sdk\DbConfig;
use Feedple\Sdk\Core\Identity;

class FeedpleServiceProvider extends ServiceProvider
{
    /**
     * Factory method to build and initialize the Feedple SDK instance.
     */
    public static function makeSdk(): ?FeedpleSDK
    {
        $apiKey = env('FEEDPLE_API_KEY');

        if (!$apiKey || $apiKey === 'sk_live_your_actual_api_key' || $apiKey === 'sk_live_your_workspace_api_key_here') {
            return null;
        }

        $dbDriver = env('DB_CONNECTION', 'sqlite');

        if ($dbDriver === 'mysql') {
            $dbConfig = DbConfig::mysql(
                host: env('DB_HOST', '127.0.0.1'),
                database: env('DB_DATABASE', 'laravel'),
                username: env('DB_USERNAME', 'root'),
                password: env('DB_PASSWORD', ''),
                port: (int) env('DB_PORT', 3306)
            );
        } elseif ($dbDriver === 'pgsql' || $dbDriver === 'postgres') {
            $dbConfig = DbConfig::pgsql(
                host: env('DB_HOST', '127.0.0.1'),
                database: env('DB_DATABASE', 'postgres'),
                username: env('DB_USERNAME', 'postgres'),
                password: env('DB_PASSWORD', ''),
                port: (int) env('DB_PORT', 5432)
            );
        } else {
            $dbPath = env('DB_DATABASE', database_path('database.sqlite'));
            if (!str_starts_with($dbPath, '/')) {
                $dbPath = base_path($dbPath);
            }
            $dbConfig = DbConfig::sqlite(path: $dbPath);
        }

        $identity = new Identity(
            name: 'laravel-app',
            allTables: true,
        );

        return new FeedpleSDK(
            apiKey: $apiKey,
            dbConfig: $dbConfig,
            identity: $identity
        );
    }

    public function boot(): void
    {
        // Register CLI artisan command
        if ($this->app->runningInConsole()) {
            $this->commands([
                FeedpleStartCommand::class,
                FeedpleStopCommand::class,
                FeedpleStatusCommand::class,
            ]);
            return;
        }

        // Initialize SDK on web application start
        try {
            $tempDir = sys_get_temp_dir();
            $pidFile = $tempDir . '/feedple-sdk.pid';
            $lockFile = $tempDir . '/feedple-sdk.lock';
            if (is_file($lockFile) && is_file($pidFile)) {
                $pid = (int) trim((string) @file_get_contents($pidFile));
                if ($pid > 0) {
                    $isRunning = false;
                    if (PHP_OS_FAMILY === 'Windows') {
                        $out = shell_exec(sprintf('tasklist /FI "PID eq %d" 2>NUL', $pid));
                        $isRunning = $out !== null && str_contains($out, (string) $pid);
                    } else {
                        exec(sprintf('kill -0 %d 2>/dev/null', $pid), result_code: $exitCode);
                        $isRunning = ($exitCode === 0);
                    }
                    if ($isRunning) {
                        return;
                    }
                }
            }

            static::makeSdk();
        } catch (\Throwable $e) {
            error_log("Failed to initialize Feedple SDK in Laravel: " . $e->getMessage());
        }
    }
}

/**
 * Artisan Command: php artisan feedple:start
 */
class FeedpleStartCommand extends Command
{
    protected $signature = 'feedple:start {--tail : Stream worker logs in real time}';
    protected $description = 'Start the Feedple background worker and optionally stream logs';

    public function handle(): int
    {
        $this->info('Initializing Feedple SDK...');

        try {
            $sdk = FeedpleServiceProvider::makeSdk();

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
            $sdk = FeedpleServiceProvider::makeSdk();
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

        $apiKey = env('FEEDPLE_API_KEY');
        if (!$apiKey || $apiKey === 'sk_live_your_actual_api_key' || $apiKey === 'sk_live_your_workspace_api_key_here') {
            $this->warn('API Key: Not configured or using placeholder in .env');
        } else {
            $maskedKey = substr($apiKey, 0, 7) . '...' . substr($apiKey, -4);
            $this->line("<comment>API Key:</comment> {$maskedKey}");
        }

        $dbDriver = env('DB_CONNECTION', 'sqlite');
        $this->line("<comment>Database Driver:</comment> {$dbDriver}");

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
            $this->warn("Worker Status: STOPPED");
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

        return $isRunning ? Command::SUCCESS : Command::FAILURE;
    }
}

