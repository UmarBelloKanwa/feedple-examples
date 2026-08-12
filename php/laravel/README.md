# Laravel + Feedple SDK & AI Web Widget Example

This directory contains a clean, beginner-friendly **Laravel** application demonstrating how to integrate the **Feedple PHP SDK** (`feedple/feedple-sdk`) and **Feedple AI Web Widget**.

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

Set your Feedple API Key and Widget Public Key in `.env`:

```env
FEEDPLE_API_KEY=sk_live_your_actual_api_key
FEEDPLE_WIDGET_PUBLIC_KEY=wpk_your_actual_widget_public_key
```

### 3. Run Application Server

```bash
php artisan serve
```

Access `http://localhost:8000` in your web browser.
