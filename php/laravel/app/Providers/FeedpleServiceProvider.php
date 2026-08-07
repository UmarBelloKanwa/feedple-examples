<?php

namespace App\Providers;

use Feedple\Sdk\FeedpleSDK;
use Feedple\Sdk\DbConfig;
use Feedple\Sdk\Core\Identity;

class FeedpleServiceProvider
{
    protected ?FeedpleSDK $sdk = null;

    public function boot(): void
    {
        $apiKey = env('FEEDPLE_API_KEY');

        if (!$apiKey || $apiKey === 'sk_live_your_workspace_api_key_here') {
            return;
        }

        try {
            $dbDriver = env('DB_CONNECTION', 'sqlite');

            if ($dbDriver === 'mysql') {
                $dbConfig = DbConfig::mysql(
                    host: env('DB_HOST', '127.0.0.1'),
                    database: env('DB_DATABASE', 'laravel'),
                    username: env('DB_USERNAME', 'root'),
                    password: env('DB_PASSWORD', '')
                );
            } else {
                $dbPath = database_path('database.sqlite');
                $dbConfig = DbConfig::sqlite(path: $dbPath);
            }

            $identity = new Identity(
                name: 'laravel-app',
                allowedTables: ['users', 'orders', 'products', 'subscriptions']
            );

            $this->sdk = new FeedpleSDK(
                apiKey: $apiKey,
                dbConfig: $dbConfig,
                identity: $identity,
                autoSync: true
            );
        } catch (\Throwable $e) {
            error_log("Failed to initialize Feedple SDK in Laravel: " . $e->getMessage());
        }
    }
}
