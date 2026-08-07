<?php

namespace App\Service;

use Feedple\Sdk\FeedpleSDK;
use Feedple\Sdk\DbConfig;
use Feedple\Sdk\Core\Identity;
use Psr\Log\LoggerInterface;

class FeedpleService
{
    private ?FeedpleSDK $sdk = null;
    private string $resolvedApiKey;
    private string $resolvedProjectDir;

    public function __construct(
        ?string $apiKey = null,
        ?string $projectDir = null,
        private ?LoggerInterface $logger = null
    ) {
        $this->resolvedApiKey = $apiKey ?? $_ENV['FEEDPLE_API_KEY'] ?? $_SERVER['FEEDPLE_API_KEY'] ?? '';
        $this->resolvedProjectDir = $projectDir ?? dirname(__DIR__, 2);
        $this->initSdk();
    }

    private function initSdk(): void
    {
        if (!$this->resolvedApiKey || $this->resolvedApiKey === 'sk_live_your_workspace_api_key_here') {
            $this->log('Feedple API Key not configured. Skipping SDK initialization.');
            return;
        }

        try {
            $dbPath = $this->resolvedProjectDir . '/var/data.db';
            
            // Seed sample database if missing
            if (!file_exists($dbPath)) {
                $dir = dirname($dbPath);
                if (!is_dir($dir)) {
                    mkdir($dir, 0777, true);
                }
                $pdo = new \PDO('sqlite:' . $dbPath);
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS clients (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        company_name TEXT NOT NULL,
                        contact_email TEXT UNIQUE NOT NULL
                    );
                    CREATE TABLE IF NOT EXISTS projects (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        client_id INTEGER NOT NULL,
                        title TEXT NOT NULL,
                        budget REAL NOT NULL
                    );
                    INSERT OR IGNORE INTO clients (company_name, contact_email) VALUES ('Apex Digital', 'info@apexdigital.com');
                    INSERT OR IGNORE INTO projects (client_id, title, budget) VALUES (1, 'Cloud Migration AI', 25000.00);
                ");
            }

            $dbConfig = DbConfig::sqlite(path: $dbPath);
            $identity = new Identity(
                name: 'symfony-app',
                allowedTables: ['clients', 'projects']
            );

            $this->sdk = new FeedpleSDK(
                apiKey: $this->resolvedApiKey,
                dbConfig: $dbConfig,
                identity: $identity,
                autoSync: true
            );

            $this->log('Feedple SDK background sync started for Symfony.');
        } catch (\Throwable $e) {
            $this->log('Failed to initialize Feedple SDK in Symfony: ' . $e->getMessage(), 'error');
        }
    }

    private function log(string $message, string $level = 'info'): void
    {
        if ($this->logger) {
            $this->logger->$level($message);
        } else {
            error_log("[$level] $message");
        }
    }

    public function isSdkActive(): bool
    {
        return $this->sdk !== null;
    }
}
