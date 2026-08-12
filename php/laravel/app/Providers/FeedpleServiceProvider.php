<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Feedple\Sdk\FeedpleSDK;
use Feedple\Sdk\DbConfig;
use Feedple\Sdk\Core\Identity;

class FeedpleServiceProvider extends ServiceProvider
{
    protected ?FeedpleSDK $sdk = null;

    public function boot(): void
    {
        $apiKey = env('FEEDPLE_API_KEY');

        if (!$apiKey || $apiKey === 'sk_live_your_workspace_api_key_here') {
            return;
        }

        try {
            // 1. Database Connection Configuration (matches .env)
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
                allowedTables: ['users', 'orders', 'products']
            );

            $this->sdk = new FeedpleSDK(
                apiKey: $apiKey,
                dbConfig: $dbConfig,
                identity: $identity
            );
        } catch (\Throwable $e) {
            error_log("Failed to initialize Feedple SDK in Laravel: " . $e->getMessage());
        }
    }
}

