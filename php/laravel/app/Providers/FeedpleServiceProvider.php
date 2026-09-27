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
            ]);
            return;
        }

        // Initialize SDK on web application start
        try {
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
