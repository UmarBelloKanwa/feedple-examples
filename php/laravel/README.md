# Laravel + Feedple SDK & AI Web Widget Example

This repository demonstrates how to integrate the **Feedple PHP SDK** (`feedple/feedple-sdk`) and **Feedple AI Web Widget** into a **Laravel 11** application using the autonomous **Stateless HTTP Webhook** architecture.

---

## 🚀 Key Architectural Advantages

- **Zero Background Daemons**: 100% stateless HTTP. Works out-of-the-box on shared hosting (Namecheap, cPanel, CloudLinux LVE), serverless (AWS Lambda / Bref), and containerized environments.
- **Cryptographic Security**: Incoming queries from Feedple Cloud are authenticated via `HMAC-SHA256` signatures (`X-Feedple-Signature`) and protected against replay attacks.
- **Data Privacy**: Your database credentials **never leave your server**. Queries are compiled locally into parameterized SQL and executed directly via your PDO connection.
- **Zero-Config URL Auto-Registration**: Running `php artisan feedple:sync` automatically registers your webhook bridge URL and provisions your webhook secret.

---

## ⚙️ Quick Start

### 1. Install Dependencies

```bash
composer install
```

### 2. Configure Environment

Copy `.env.example` to `.env` and generate an app key:

```bash
cp .env.example .env
php artisan key:generate
```

Configure your Feedple credentials and database in `.env`:

```ini
APP_URL=http://localhost:8000

# Feedple Workspace Configuration
FEEDPLE_API_KEY=fdpl_live_your_actual_api_key
FEEDPLE_TRANSPORT=webhook
FEEDPLE_BRIDGE_PATH=/api/feedple/bridge
FEEDPLE_WIDGET_PUBLIC_KEY=wpk_your_widget_public_key

# Database (SQLite, MySQL, or PostgreSQL)
DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite
```

### 3. Synchronize Schema & Register Webhook Bridge

Run the one-shot sync command:

```bash
php artisan feedple:sync
```

This command will:
1. Introspect your database schema.
2. Auto-detect your webhook bridge URL (`APP_URL + /api/feedple/bridge`).
3. Upload schema metadata to Feedple Cloud over HTTPS.
4. Auto-register the bridge URL and save the provisioned `FEEDPLE_WEBHOOK_SECRET` to your `.env` file.

### 4. Start the Application

```bash
php artisan serve
```

Visit `http://localhost:8000` to interact with your data using the Feedple AI Web Widget.

---

## 🛠️ How It Works

```
Feedple Cloud                  Your Laravel App (/api/feedple/bridge)             Your Database
     │                                                │                                  │
     │── HTTPS POST (IR query + HMAC Signature) ─────>│                                  │
     │                                                │── Verify HMAC-SHA256 Signature   │
     │                                                │── Validate IR AST & Permissions  │
     │                                                │── Compile Parameterized SQL ────>│
     │                                                │<─ Local Query Results ───────────│
     │<─ HTTP 200 JSON Response (status, rows) ───────│                                  │
     │   [Completed in 15-30ms, no daemon required]   │                                  │
```

### Automatic Route Registration

The `FeedpleServiceProvider` automatically registers the bridge endpoint at `POST /api/feedple/bridge` and exempts it from CSRF verification.

You can also test the bridge health in your browser or with curl:

```bash
curl http://localhost:8000/api/feedple/bridge
```
```json
{
  "status": "active",
  "service": "Feedple Webhook Bridge",
  "transport": "webhook",
  "configured": true,
  "secret_set": true
}
```

---

## 💻 Artisan Commands Reference

| Command | Description |
| :--- | :--- |
| `php artisan feedple:sync` | Perform a one-shot database schema synchronization to Feedple Cloud and auto-register webhook URL. |
| `php artisan feedple:sync --force` | Force re-synchronization even if the local schema hash matches. |
| `php artisan feedple:sync --bridge-url=https://xyz.ngrok.io/api/feedple/bridge` | Manually override the registered webhook bridge URL (e.g. for tunneling/testing). |
| `php artisan feedple:status` | Check the configuration, active transport mode, database connection, and recent logs. |
| `php artisan feedple:start [--tail]` | Start the optional persistent WebSocket daemon (development mode only). |
| `php artisan feedple:stop` | Stop the background WebSocket daemon process. |

---

## 🗄️ Supported Databases

The SDK detects your active Laravel database connection from `config/database.php`:

- **SQLite**: `DB_CONNECTION=sqlite`
- **MySQL / MariaDB**: `DB_CONNECTION=mysql`
- **PostgreSQL**: `DB_CONNECTION=pgsql`

---

## 💬 Feedple AI Web Widget Integration

The chat bubble is embedded in `resources/views/welcome.blade.php`:

```blade
@php
    $rawApiUrl = env('FEEDPLE_API_BASE_URL', 'https://feedple.com');
    $backendUrl = preg_replace('#/api(/v1)?/?$#', '', $rawApiUrl);
    $widgetSrc = $backendUrl . '/widget.js';
@endphp
<script
    src="{{ $widgetSrc }}"
    data-public-key="{{ env('FEEDPLE_WIDGET_PUBLIC_KEY') }}"
    data-backend-url="{{ $backendUrl }}"
    data-theme-color="#f43f5e"
    data-position="bottom-right"
    data-title="Laravel AI Assistant"
    data-welcome-message="Hello! I am connected to your Laravel database. How can I assist you today?"
    defer
></script>
```

> **Tip for Local Testing**: When testing locally against your local Feedple AI backend (`http://127.0.0.1:8000`), `data-backend-url` ensures the widget points directly to `http://127.0.0.1:8000` rather than the cloud server, allowing seamless communication with your local webhook bridge (`http://127.0.0.1:8001/api/feedple/bridge`).
