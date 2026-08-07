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
                $dbDir = database_path();
                if (!is_dir($dbDir)) {
                    mkdir($dbDir, 0777, true);
                }
                $dbPath = database_path('database.sqlite');
                if (!file_exists($dbPath)) {
                    touch($dbPath);
                    $pdo = new \PDO('sqlite:' . $dbPath);
                    $pdo->exec("
                        CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, email TEXT);
                        CREATE TABLE IF NOT EXISTS orders (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, total REAL);
                        INSERT OR IGNORE INTO users (name, email) VALUES ('Taylor Otwell', 'taylor@laravel.com');
                        INSERT OR IGNORE INTO orders (user_id, total) VALUES (1, 299.00);
                    ");
                }
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
