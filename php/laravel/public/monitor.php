<?php

/**
 * Feedple AI — Real-time Background Worker Monitor & Dashboard
 *
 * Designed for shared hosting (Namecheap, cPanel) and local development.
 * Allows you to view live background logs, check worker status, and start/stop the worker.
 */

declare(strict_types=1);

// Prevent caching
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

@ini_set('memory_limit', '256M');

$baseDir = dirname(__DIR__);
$autoloadFile = $baseDir . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
$bootstrapFile = $baseDir . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'app.php';
$envFile = $baseDir . DIRECTORY_SEPARATOR . '.env';

// Start session for auth
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Read .env directly if Laravel is not booted yet
function getFeedpleEnv(string $key, ?string $default = null): ?string {
    if (function_exists('env')) {
        $val = env($key);
        if ($val !== null) {
            return (string) $val;
        }
    }
    $envFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';
    if (is_file($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) continue;
            if (str_contains($line, '=')) {
                [$k, $v] = explode('=', $line, 2);
                $k = trim($k);
                $v = trim($v, " \t\n\r\0\x0B\"'");
                if ($k === $key) return $v;
            }
        }
    }
    return getenv($key) ?: $default;
}

/**
 * Memory-safe log reader using fseek() to read only the last N lines from the end of the file.
 * Prevents Out-Of-Memory crashes even if the log file has grown to tens or hundreds of megabytes.
 */
function tailFileSafely(string $filePath, int $lines = 100): array {
    if (!is_file($filePath) || !is_readable($filePath)) {
        return [];
    }

    $fp = @fopen($filePath, 'rb');
    if (!$fp) {
        return [];
    }

    $buffer = '';
    $chunkSize = 4096;
    fseek($fp, 0, SEEK_END);
    $pos = ftell($fp);
    $lineCount = 0;

    while ($pos > 0 && $lineCount <= $lines) {
        $seek = max(0, $pos - $chunkSize);
        $readLength = $pos - $seek;
        fseek($fp, $seek);
        $chunk = fread($fp, $readLength);
        $buffer = $chunk . $buffer;
        $pos = $seek;
        $lineCount = substr_count($buffer, "\n");
    }

    fclose($fp);

    $allLines = explode("\n", str_replace(["\r\n", "\r"], "\n", $buffer));
    if (end($allLines) === '') {
        array_pop($allLines);
    }

    return array_map('trim', array_slice($allLines, -$lines));
}

$apiKey = getFeedpleEnv('FEEDPLE_API_KEY', '');
$runtimeDir = $baseDir . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'cache';
$tempDir = sys_get_temp_dir();
$pidFile = is_file($tempDir . DIRECTORY_SEPARATOR . 'feedple-sdk.pid')
    ? $tempDir . DIRECTORY_SEPARATOR . 'feedple-sdk.pid'
    : $runtimeDir . DIRECTORY_SEPARATOR . 'feedple-sdk.pid';
$logFile = is_file($tempDir . DIRECTORY_SEPARATOR . 'feedple-sdk.log')
    ? $tempDir . DIRECTORY_SEPARATOR . 'feedple-sdk.log'
    : $runtimeDir . DIRECTORY_SEPARATOR . 'feedple-sdk.log';

// ── Authentication Check ──────────────────────────────────────────────────
$isAuthorized = false;

// Check 1: Session login
if (!empty($_SESSION['feedple_authorized']) && $_SESSION['feedple_authorized'] === true) {
    $isAuthorized = true;
}

// Check 2: Key passed in URL (?key=...)
$providedKey = $_GET['key'] ?? $_POST['key'] ?? null;
if ($providedKey && $apiKey !== '' && hash_equals($apiKey, (string) $providedKey)) {
    $_SESSION['feedple_authorized'] = true;
    $isAuthorized = true;
}

// Check 3: Laravel Admin Session
if (!$isAuthorized && is_file($autoloadFile) && is_file($bootstrapFile)) {
    try {
        require_once $autoloadFile;
        $app = require_once $bootstrapFile;
        $kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);
        $request = \Illuminate\Http\Request::capture();
        $response = $kernel->handle($request);
        if (auth()->check()) {
            $user = auth()->user();
            if (isset($user->is_admin) && $user->is_admin) {
                $isAuthorized = true;
            } elseif (method_exists($user, 'hasRole') && $user->hasRole('admin')) {
                $isAuthorized = true;
            }
        }
    } catch (\Throwable) {
        // Fallback to key-based auth
    }
}

// Handle Login Form Submission
if (!$isAuthorized && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['login_key'])) {
    $submitted = trim($_POST['login_key']);
    if ($apiKey !== '' && hash_equals($apiKey, $submitted)) {
        $_SESSION['feedple_authorized'] = true;
        header('Location: ' . strtok($_SERVER['REQUEST_URI'] ?? '/', '?'));
        exit;
    }
    $loginError = 'Invalid Feedple API Key. Please provide the key configured in your .env file.';
}

// Handle Logout
if (isset($_GET['logout'])) {
    unset($_SESSION['feedple_authorized']);
    header('Location: ' . strtok($_SERVER['REQUEST_URI'] ?? '/', '?'));
    exit;
}

// Helper: Check if worker is running
function checkWorkerRunning(string $pidFile): array {
    if (!is_file($pidFile)) {
        return ['running' => false, 'pid' => null];
    }
    $pid = (int) trim((string) file_get_contents($pidFile));
    if ($pid <= 0) {
        return ['running' => false, 'pid' => null];
    }

    if (PHP_OS_FAMILY === 'Windows') {
        $output = shell_exec(sprintf('tasklist /FI "PID eq %d" 2>NUL', $pid));
        $running = $output !== null && str_contains($output, (string) $pid);
        return ['running' => $running, 'pid' => $pid];
    }

    exec(sprintf('kill -0 %d 2>/dev/null', $pid), result_code: $exitCode);
    return ['running' => $exitCode === 0, 'pid' => $pid];
}

function getOptimalPhpBinary(): string {
    $envOverride = getFeedpleEnv('FEEDPLE_PHP_BINARY');
    if (!empty($envOverride)) {
        return (string) $envOverride;
    }
    if (PHP_OS_FAMILY !== 'Windows' && PHP_VERSION_ID < 80300) {
        $candidates = [
            '/usr/local/bin/ea-php83',
            '/opt/cpanel/ea-php83/root/usr/bin/php',
            '/opt/alt/php83/usr/bin/php',
            '/usr/local/bin/ea-php84',
            '/opt/cpanel/ea-php84/root/usr/bin/php',
        ];
        foreach ($candidates as $candidate) {
            if (@is_executable($candidate)) {
                return $candidate;
            }
        }
    }
    return PHP_OS_FAMILY === 'Windows' ? 'php' : PHP_BINARY;
}

// ── AJAX API Endpoints ─────────────────────────────────────────────────────
if (isset($_GET['api'])) {
    header('Content-Type: application/json');
    if (!$isAuthorized) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }
    $action = $_GET['api'];

    if ($action === 'status') {
        $status = checkWorkerRunning($pidFile);
        $fileSize = is_file($logFile) ? filesize($logFile) : 0;
        $lastModified = is_file($logFile) ? filemtime($logFile) : null;

        echo json_encode([
            'success'       => true,
            'running'       => $status['running'],
            'pid'           => $status['pid'],
            'log_size'      => $fileSize,
            'log_mtime'     => $lastModified ? date('Y-m-d H:i:s', $lastModified) : null,
            'log_path'      => $logFile,
            'api_key_set'   => !empty($apiKey),
            'masked_key'    => $apiKey ? substr($apiKey, 0, 8) . '...' . substr($apiKey, -4) : 'Not configured',
            'server_os'     => PHP_OS_FAMILY,
            'php_version'   => PHP_VERSION,
            'php_binary'    => getOptimalPhpBinary(),
        ]);
        exit;
    }

    if ($action === 'logs') {
        $lines = max(10, min(500, (int) ($_GET['lines'] ?? 100)));
        $content = [];
        if (is_file($logFile)) {
            $content = tailFileSafely($logFile, $lines);
        }
        echo json_encode([
            'success' => true,
            'count'   => count($content),
            'logs'    => $content,
        ]);
        exit;
    }

    if ($action === 'start') {
        $status = checkWorkerRunning($pidFile);
        if ($status['running']) {
            echo json_encode(['success' => true, 'message' => "Worker is already running (PID: {$status['pid']})"]);
            exit;
        }

        // Trigger artisan feedple:start
        $phpBinary = getOptimalPhpBinary();
        $artisan = $baseDir . DIRECTORY_SEPARATOR . 'artisan';

        if (PHP_OS_FAMILY === 'Windows') {
            $cmd = sprintf(
                'powershell.exe -NoProfile -Command "Start-Process -FilePath \'%s\' -ArgumentList \'\"%s\" feedple:start\' -WindowStyle Hidden"',
                $phpBinary,
                $artisan
            );
            pclose(popen("start /B " . $cmd, "r"));
        } else {
            exec(sprintf('%s %s feedple:start >> %s 2>&1 &', escapeshellarg($phpBinary), escapeshellarg($artisan), escapeshellarg($logFile)));
        }

        sleep(1);
        $newStatus = checkWorkerRunning($pidFile);
        echo json_encode([
            'success' => true,
            'running' => $newStatus['running'],
            'pid'     => $newStatus['pid'],
            'message' => $newStatus['running'] ? "Worker started successfully (PID: {$newStatus['pid']})" : "Worker start initiated.",
        ]);
        exit;
    }

    if ($action === 'stop') {
        $status = checkWorkerRunning($pidFile);
        if ($status['pid']) {
            $pid = $status['pid'];
            if (PHP_OS_FAMILY === 'Windows') {
                exec(sprintf('taskkill /F /T /PID %d 2>NUL', $pid));
            } else {
                exec(sprintf('kill -TERM %d 2>/dev/null', $pid));
            }
        }
        @unlink($pidFile);
        echo json_encode(['success' => true, 'message' => 'Worker stopped successfully.']);
        exit;
    }

    if ($action === 'clear_log') {
        if (is_file($logFile)) {
            file_put_contents($logFile, '');
        }
        echo json_encode(['success' => true, 'message' => 'Log file cleared.']);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Unknown API action']);
    exit;
}

// ── Render Unauthorized Login Screen ───────────────────────────────────────
if (!$isAuthorized): ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedple AI — Background Monitor Authentication</title>
    <style>
        :root {
            --bg: #090d16;
            --card: #131b2e;
            --border: #23314d;
            --primary: #4f46e5;
            --primary-hover: #4338ca;
            --text: #f1f5f9;
            --muted: #94a3b8;
            --danger: #ef4444;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background-color: var(--bg);
            color: var(--text);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
        }
        .login-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 40px;
            width: 100%;
            max-width: 440px;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5);
        }
        .logo-badge {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-weight: 700;
            font-size: 1.25rem;
            color: #fff;
            margin-bottom: 24px;
        }
        .logo-dot {
            width: 12px;
            height: 12px;
            background: #6366f1;
            border-radius: 50%;
            box-shadow: 0 0 12px #6366f1;
        }
        h1 { font-size: 1.5rem; font-weight: 600; margin-bottom: 8px; }
        p { color: var(--muted); font-size: 0.9rem; margin-bottom: 24px; line-height: 1.5; }
        .form-group { margin-bottom: 20px; }
        label { display: block; font-size: 0.85rem; font-weight: 500; margin-bottom: 8px; color: var(--text); }
        input[type="password"], input[type="text"] {
            width: 100%;
            padding: 12px 14px;
            background: #0b1120;
            border: 1px solid var(--border);
            border-radius: 8px;
            color: #fff;
            font-size: 0.95rem;
            outline: none;
            transition: border-color 0.2s;
        }
        input:focus { border-color: var(--primary); }
        button {
            width: 100%;
            padding: 12px;
            background: var(--primary);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }
        button:hover { background: var(--primary-hover); }
        .error-msg {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid var(--danger);
            color: #fca5a5;
            padding: 12px;
            border-radius: 8px;
            font-size: 0.85rem;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="logo-badge">
            <span class="logo-dot"></span> Feedple AI
        </div>
        <h1>Worker Monitor</h1>
        <p>Enter your Feedple API Key (from your <code>.env</code> file) to view live background sync activity.</p>

        <?php if (!empty($loginError)): ?>
            <div class="error-msg"><?= htmlspecialchars($loginError) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="login_key">Feedple API Key</label>
                <input type="password" id="login_key" name="login_key" placeholder="fdpl_..." required autofocus>
            </div>
            <button type="submit">Unlock Dashboard</button>
        </form>
    </div>
</body>
</html>
<?php exit; endif; ?>

<?php
// ── Render Full Dashboard ─────────────────────────────────────────────────
$initialStatus = checkWorkerRunning($pidFile);
$serverHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
$defaultPhpBin = getOptimalPhpBinary();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedple AI — Live Background Worker Monitor</title>
    <style>
        :root {
            --bg: #090d16;
            --card: #111827;
            --card-sub: #1f2937;
            --border: #374151;
            --primary: #4f46e5;
            --primary-hover: #4338ca;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --text: #f9fafb;
            --muted: #9ca3af;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background-color: var(--bg);
            color: var(--text);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            padding: 24px;
            min-height: 100vh;
        }
        .container { max-width: 1200px; margin: 0 auto; }
        
        /* Header */
        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 24px;
            border-bottom: 1px solid var(--border);
            margin-bottom: 24px;
        }
        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 1.35rem;
            font-weight: 700;
        }
        .brand .dot {
            width: 14px;
            height: 14px;
            border-radius: 50%;
            background: var(--primary);
            box-shadow: 0 0 10px var(--primary);
        }
        .top-actions { display: flex; align-items: center; gap: 12px; }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 9px 16px;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
            border: 1px solid transparent;
            transition: all 0.2s;
            text-decoration: none;
        }
        .btn-primary { background: var(--primary); color: #fff; }
        .btn-primary:hover { background: var(--primary-hover); }
        .btn-success { background: #059669; color: #fff; }
        .btn-success:hover { background: #047857; }
        .btn-danger { background: #dc2626; color: #fff; }
        .btn-danger:hover { background: #b91c1c; }
        .btn-outline { background: transparent; border-color: var(--border); color: var(--muted); }
        .btn-outline:hover { background: var(--card-sub); color: #fff; }

        /* Grid stats */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .stat-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 20px;
            position: relative;
        }
        .stat-label { font-size: 0.8rem; color: var(--muted); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; }
        .stat-val { font-size: 1.4rem; font-weight: 700; display: flex; align-items: center; gap: 10px; }
        .stat-sub { font-size: 0.8rem; color: var(--muted); margin-top: 6px; }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 9999px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .status-badge.running { background: rgba(16, 185, 129, 0.2); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.4); }
        .status-badge.stopped { background: rgba(239, 68, 68, 0.2); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.4); }
        .pulse { width: 8px; height: 8px; border-radius: 50%; }
        .status-badge.running .pulse { background: #10b981; box-shadow: 0 0 8px #10b981; animation: pulse-anim 2s infinite; }
        .status-badge.stopped .pulse { background: #ef4444; }

        @keyframes pulse-anim {
            0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
            70% { transform: scale(1); box-shadow: 0 0 0 6px rgba(16, 185, 129, 0); }
            100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
        }

        /* Terminal card */
        .terminal-card {
            background: #0a0e17;
            border: 1px solid var(--border);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.5);
            margin-bottom: 24px;
        }
        .terminal-header {
            background: #111827;
            padding: 12px 18px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border);
        }
        .terminal-title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.9rem;
            font-weight: 600;
            color: #cbd5e1;
        }
        .terminal-controls { display: flex; align-items: center; gap: 12px; font-size: 0.85rem; }
        .terminal-controls label { display: flex; align-items: center; gap: 6px; color: var(--muted); cursor: pointer; }
        .terminal-body {
            padding: 16px;
            height: 480px;
            overflow-y: auto;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 0.85rem;
            line-height: 1.6;
            background: #06090e;
        }
        .log-entry { margin-bottom: 3px; word-break: break-all; }
        .log-time { color: #64748b; }
        .log-info { color: #38bdf8; font-weight: 600; }
        .log-warning { color: #facc15; font-weight: 600; }
        .log-error { color: #f87171; font-weight: 600; }
        .log-text { color: #e2e8f0; }

        /* Shared hosting info card */
        .info-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 20px;
        }
        .info-card h3 { font-size: 1.1rem; margin-bottom: 12px; display: flex; align-items: center; gap: 8px; }
        .cron-box {
            background: #06090e;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 12px 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 10px;
            font-family: monospace;
            font-size: 0.875rem;
            color: #38bdf8;
        }
        .copy-btn {
            background: var(--card-sub);
            border: 1px solid var(--border);
            color: #fff;
            padding: 5px 10px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.75rem;
        }
        .copy-btn:hover { background: var(--primary); }
    </style>
</head>
<body>
    <div class="container">
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="brand">
                <span class="dot"></span>
                Feedple AI Worker Monitor
            </div>
            <div class="top-actions">
                <button id="btnStart" class="btn btn-success" onclick="startWorker()">Start Worker</button>
                <button id="btnStop" class="btn btn-danger" onclick="stopWorker()">Stop Worker</button>
                <button class="btn btn-outline" onclick="fetchStatusAndLogs()">Refresh Now</button>
                <a href="?logout=1" class="btn btn-outline">Logout</a>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Worker Status</div>
                <div class="stat-val">
                    <span id="statusBadge" class="status-badge <?= $initialStatus['running'] ? 'running' : 'stopped' ?>">
                        <span class="pulse"></span>
                        <span id="statusText"><?= $initialStatus['running'] ? 'RUNNING (PID: ' . $initialStatus['pid'] . ')' : 'STOPPED' ?></span>
                    </span>
                </div>
                <div class="stat-sub" id="lastCheckTime">Checked just now</div>
            </div>

            <div class="stat-card">
                <div class="stat-label">Workspace API Key</div>
                <div class="stat-val" style="font-size: 1.1rem; color: #a5b4fc;">
                    <?= $apiKey ? substr($apiKey, 0, 8) . '...' . substr($apiKey, -4) : '<span style="color:#ef4444">Not configured in .env</span>' ?>
                </div>
                <div class="stat-sub">From <code>FEEDPLE_API_KEY</code></div>
            </div>

            <div class="stat-card">
                <div class="stat-label">Database Connection</div>
                <div class="stat-val" style="font-size: 1.1rem; color: #34d399;">
                    <?= htmlspecialchars(getFeedpleEnv('DB_DATABASE', 'feedple_test')) ?>
                </div>
                <div class="stat-sub"><?= htmlspecialchars(getFeedpleEnv('DB_CONNECTION', 'mysql')) ?>:<?= htmlspecialchars(getFeedpleEnv('DB_PORT', '3306')) ?></div>
            </div>

            <div class="stat-card">
                <div class="stat-label">Sync Interval</div>
                <div class="stat-val" style="font-size: 1.1rem;">
                    <?= htmlspecialchars(getFeedpleEnv('FEEDPLE_SYNC_INTERVAL', '60')) ?> seconds
                </div>
                <div class="stat-sub">Auto-sync: <?= getFeedpleEnv('FEEDPLE_AUTO_SYNC', 'true') === 'true' ? 'Enabled' : 'Disabled' ?></div>
            </div>
        </div>

        <!-- Terminal Log Viewer -->
        <div class="terminal-card">
            <div class="terminal-header">
                <div class="terminal-title">
                    <span>⚡ Live Background Worker Logs</span>
                    <span id="logCounter" style="color: var(--muted); font-size: 0.8rem;">(0 lines)</span>
                </div>
                <div class="terminal-controls">
                    <label>
                        <input type="checkbox" id="autoScroll" checked> Auto-Scroll
                    </label>
                    <label>
                        <input type="checkbox" id="autoRefresh" checked> Live Stream (3s)
                    </label>
                    <button class="btn btn-outline" style="padding: 4px 10px; font-size: 0.75rem;" onclick="clearLogs()">Clear Log</button>
                </div>
            </div>
            <div class="terminal-body" id="terminalBody">
                <div class="log-entry" style="color: var(--muted);">Loading logs...</div>
            </div>
        </div>

        <!-- Shared Hosting (Namecheap) Cron Setup -->
        <div class="info-card">
            <h3>🌐 Namecheap / cPanel Background Setup Guide</h3>
            <p style="color: var(--muted); font-size: 0.9rem; line-height: 1.5;">
                On shared hosting (cPanel), web servers may periodically restart background processes when idle. To guarantee your Feedple AI worker runs 24/7 automatically, set up a <strong>Cron Job</strong> in cPanel:
            </p>
            <div class="cron-box">
                <span id="cronCommand">* * * * * <?= htmlspecialchars($defaultPhpBin) ?> <?= htmlspecialchars($baseDir) ?>/artisan feedple:start >/dev/null 2>&1</span>
                <button class="copy-btn" onclick="copyCron()">Copy Cron Command</button>
            </div>
            <p style="color: var(--muted); font-size: 0.8rem; margin-top: 8px;">
                💡 <em>Note: The SDK is strictly idempotent — running <code>feedple:start</code> every minute via cron will simply verify the worker is alive, without starting duplicate processes.</em>
            </p>
        </div>
    </div>

    <script>
        let pollTimer = null;

        function formatLogLine(line) {
            // Match pattern [YYYY-MM-DD HH:MM:SS] LEVEL: Message
            const match = line.match(/^\[(.*?)\]\s+(INFO|WARNING|ERROR|NOTICE|DEBUG):\s*(.*)$/);
            if (!match) {
                return '<div class="log-entry"><span class="log-text">' + escapeHtml(line) + '</span></div>';
            }
            const time = match[1];
            const level = match[2];
            const msg = match[3];

            let levelClass = 'log-info';
            if (level === 'WARNING') levelClass = 'log-warning';
            if (level === 'ERROR') levelClass = 'log-error';

            return `<div class="log-entry">
                <span class="log-time">[${escapeHtml(time)}]</span>
                <span class="${levelClass}">${level}:</span>
                <span class="log-text">${escapeHtml(msg)}</span>
            </div>`;
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        async function fetchStatusAndLogs() {
            try {
                const [statusRes, logsRes] = await Promise.all([
                    fetch('?api=status'),
                    fetch('?api=logs&lines=120')
                ]);

                const statusData = await statusRes.json();
                const logsData = await logsRes.json();

                // Update Status UI
                const badge = document.getElementById('statusBadge');
                const text = document.getElementById('statusText');
                if (statusData.running) {
                    badge.className = 'status-badge running';
                    text.textContent = `RUNNING (PID: ${statusData.pid})`;
                    document.getElementById('btnStart').style.display = 'none';
                    document.getElementById('btnStop').style.display = 'inline-flex';
                } else {
                    badge.className = 'status-badge stopped';
                    text.textContent = 'STOPPED';
                    document.getElementById('btnStart').style.display = 'inline-flex';
                    document.getElementById('btnStop').style.display = 'none';
                }
                document.getElementById('lastCheckTime').textContent = 'Updated at ' + new Date().toLocaleTimeString();

                // Update Logs
                const term = document.getElementById('terminalBody');
                const autoScroll = document.getElementById('autoScroll').checked;

                if (logsData.logs && logsData.logs.length > 0) {
                    document.getElementById('logCounter').textContent = `(${logsData.logs.length} lines)`;
                    term.innerHTML = logsData.logs.map(formatLogLine).join('');
                    if (autoScroll) {
                        term.scrollTop = term.scrollHeight;
                    }
                } else {
                    term.innerHTML = '<div class="log-entry" style="color: #64748b;">No log output recorded yet.</div>';
                }
            } catch (err) {
                console.error('Failed to fetch status/logs:', err);
            }
        }

        async function startWorker() {
            const btn = document.getElementById('btnStart');
            btn.textContent = 'Starting...';
            btn.disabled = true;
            try {
                const res = await fetch('?api=start');
                const data = await res.json();
                setTimeout(fetchStatusAndLogs, 1000);
            } catch (err) {
                alert('Failed to start worker');
            } finally {
                btn.textContent = 'Start Worker';
                btn.disabled = false;
            }
        }

        async function stopWorker() {
            const btn = document.getElementById('btnStop');
            btn.textContent = 'Stopping...';
            btn.disabled = true;
            try {
                const res = await fetch('?api=stop');
                const data = await res.json();
                setTimeout(fetchStatusAndLogs, 1000);
            } catch (err) {
                alert('Failed to stop worker');
            } finally {
                btn.textContent = 'Stop Worker';
                btn.disabled = false;
            }
        }

        async function clearLogs() {
            if (!confirm('Clear all background logs?')) return;
            await fetch('?api=clear_log');
            fetchStatusAndLogs();
        }

        function copyCron() {
            const cmd = document.getElementById('cronCommand').textContent;
            navigator.clipboard.writeText(cmd).then(() => {
                alert('cPanel Cron command copied to clipboard!');
            });
        }

        // Auto polling
        function initPolling() {
            fetchStatusAndLogs();
            pollTimer = setInterval(() => {
                if (document.getElementById('autoRefresh').checked) {
                    fetchStatusAndLogs();
                }
            }, 3000);
        }

        window.addEventListener('DOMContentLoaded', initPolling);
    </script>
</body>
</html>