# Django 5 + Feedple SDK & AI Web Widget Example

This directory contains a complete, production-ready **Django 5** application demonstrating how to integrate the **Feedple Python SDK** and **Feedple AI Web Widget**.

---

## ⚙️ Quick Start

### 1. Install Dependencies

```bash
pip install -r requirements.txt
```

### 2. Configure Environment

Copy `.env.example` to `.env`:

```bash
cp .env.example .env
```

Set your API Key and Widget Public Key in `.env`:

```env
FEEDPLE_API_KEY=sk_live_your_actual_api_key
FEEDPLE_WIDGET_PUBLIC_KEY=wpk_your_actual_widget_public_key
```

### 3. Run Migrations & Start Server

```bash
python manage.py migrate
python manage.py runserver 8000
```

Access `http://localhost:8000` in your web browser.
