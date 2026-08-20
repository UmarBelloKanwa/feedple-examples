<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laravel + Feedple AI Integration</title>
    <style>
        :root {
            --bg: #0f172a;
            --card-bg: #1e293b;
            --accent: #f43f5e;
            --text: #f8fafc;
            --text-muted: #94a3b8;
            --border: #334155;
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
            background: rgba(30, 41, 59, 0.8);
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
        <h1>Laravel Integration Example</h1>
        <span style="color: var(--accent); font-weight: 600;">FeedpleServiceProvider Active</span>
    </header>

    <main>
        <div class="card">
            <h2>Laravel + Feedple AI Starter</h2>
            <p>The <code>FeedpleServiceProvider</code> initializes the <code>feedple/feedple-sdk</code> package seamlessly using Laravel's environment and database connection settings.</p>
            <p>Click the chat bubble on the bottom right to start asking questions about your Laravel application data.</p>
        </div>
    </main>

    <!-- Feedple AI Web Widget Integration -->
    <script
        src="https://feedple.com/widget.js"
        data-public-key="{{ env('FEEDPLE_WIDGET_PUBLIC_KEY', 'wpk_demo_key') }}"
        data-theme-color="#f43f5e"
        data-position="bottom-right"
        data-title="Laravel AI Assistant"
        data-welcome-message="Hello! I am connected to your Laravel database. How can I assist you today?"
        defer
    ></script>
</body>
</html>
