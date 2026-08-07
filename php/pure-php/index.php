<?php

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

use Dotenv\Dotenv;
use Feedple\Sdk\FeedpleSDK;
use Feedple\Sdk\DbConfig;
use Feedple\Sdk\Core\Identity;

// Load environment variables if .env exists
if (file_exists(__DIR__ . '/.env') && class_exists(Dotenv::class)) {
    $dotenv = Dotenv::createImmutable(__DIR__);
    $dotenv->load();
}

$apiKey = $_ENV['FEEDPLE_API_KEY'] ?? $_SERVER['FEEDPLE_API_KEY'] ?? 'sk_live_demo_key';
$widgetPublicKey = $_ENV['FEEDPLE_WIDGET_PUBLIC_KEY'] ?? $_SERVER['FEEDPLE_WIDGET_PUBLIC_KEY'] ?? 'wpk_demo_public_key';
$dbPath = __DIR__ . '/database.sqlite';

// 1. Initialize SQLite Database if missing
if (!file_exists($dbPath)) {
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS members (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            full_name TEXT NOT NULL,
            email TEXT UNIQUE NOT NULL,
            status TEXT DEFAULT 'active'
        );
        CREATE TABLE IF NOT EXISTS transactions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            member_id INTEGER NOT NULL,
            amount REAL NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
        INSERT OR IGNORE INTO members (id, full_name, email) VALUES (1, 'John Doe', 'john@example.com');
        INSERT OR IGNORE INTO transactions (member_id, amount) VALUES (1, 99.99);
    ");
}

// 2. Initialize Feedple SDK
$sdkActive = false;
if (class_exists(FeedpleSDK::class) && $apiKey && $apiKey !== 'sk_live_demo_key') {
    try {
        $dbConfig = DbConfig::sqlite(path: $dbPath);
        $identity = new Identity(
            name: 'pure-php-app',
            allowedTables: ['members', 'transactions']
        );

        $sdk = new FeedpleSDK(
            apiKey: $apiKey,
            dbConfig: $dbConfig,
            identity: $identity,
            autoSync: true
        );
        $sdkActive = true;
    } catch (\Throwable $e) {
        error_log("Feedple SDK Error: " . $e->getMessage());
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pure PHP + Feedple AI Integration</title>
    <style>
        :root {
            --bg: #0c1017;
            --card-bg: #161b22;
            --accent: #0284c7;
            --text: #f0f6fc;
            --text-muted: #8b949e;
            --border: #30363d;
        }
        body {
            margin: 0;
            font-family: system-ui, -apple-system, sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
        }
        header {
            border-bottom: 1px solid var(--border);
            padding: 1.25rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: rgba(22, 27, 34, 0.8);
        }
        header h1 {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 700;
        }
        main {
            max-width: 900px;
            margin: 0 auto;
            padding: 3rem 1.5rem;
        }
        .card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 2rem;
        }
        .card h2 {
            margin-top: 0;
            color: var(--accent);
        }
    </style>
</head>
<body>
    <header>
        <h1>Pure PHP Integration Example</h1>
        <span style="color: var(--accent); font-weight: 600;">
            Status: <?= $sdkActive ? 'SDK Active' : 'Demo Mode' ?>
        </span>
    </header>

    <main>
        <div class="card">
            <h2>Pure PHP + Feedple AI Starter</h2>
            <p>This application demonstrates direct PHP integration using Composer autoloading, <code>DbConfig::sqlite(...)</code>, and the Feedple AI Web Widget.</p>
            <p>Use the chat widget on the bottom right to query the <code>members</code> and <code>transactions</code> database tables.</p>
        </div>
    </main>

    <!-- Feedple AI Web Widget Integration -->
    <script
        src="https://feedple-ai-psi.vercel.app/widget.js"
        data-public-key="<?= htmlspecialchars($widgetPublicKey) ?>"
        data-theme-color="#0284c7"
        data-position="bottom-right"
        data-title="PHP AI Assistant"
        data-welcome-message="Hello! How can I assist you with your PHP database today?"
        defer
    ></script>
</body>
</html>
