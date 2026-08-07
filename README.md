# Feedple Integration Examples

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![Python SDK](https://img.shields.io/pypi/v/feedple-sdk.svg?color=green&label=feedple-sdk)](https://pypi.org/project/feedple-sdk/)
[![PHP SDK](https://img.shields.io/packagist/v/feedple/feedple-sdk.svg?color=purple&label=feedple/feedple-sdk)](https://packagist.org/packages/feedple/feedple-sdk)

Official reference repository containing production-quality, runnable starter applications for integrating the **Feedple SDK** and **Feedple AI Web Widget** into your web applications.

---

## 📚 Overview

Feedple AI allows you to query your database using natural language without exposing database ports or credentials.

- **Feedple SDK**: Synchronizes database schemas over outbound WebSockets and executes AI queries safely on your server.
- **Feedple AI Web Widget**: Embeds a sleek, Shadow-DOM encapsulated AI assistant directly into your frontend with a single script tag.

This repository provides complete, runnable starter projects across popular Python and PHP web frameworks.

---

## 📁 Repository Structure

```text
feedple-examples/
├── python/
│   ├── fastapi/       # FastAPI + SQLAlchemy + Feedple SDK + Web Widget
│   ├── flask/         # Flask + SQLAlchemy + Feedple SDK + Web Widget
│   └── django/        # Django 5 + AppConfig + Feedple SDK + Web Widget
├── php/
│   ├── laravel/       # Laravel + FeedpleServiceProvider + Web Widget
│   └── pure-php/      # Pure PHP + PDO + Feedple SDK + Web Widget
├── README.md
└── LICENSE
```

---

## 🛠️ Prerequisites

Before running any example, ensure you have:

1. **Feedple Account & Keys**:
   - Workspace API Key (`sk_live_...`) from your Feedple workspace dashboard.
   - Widget Public Key (`wpk_...`) from your Web Widget settings card.
2. **Framework Runtime**:
   - Python examples require **Python 3.9+**.
   - PHP examples require **PHP 8.1+** and **Composer**.

---

## ⚡ Quick Start by Framework

### 🐍 Python Frameworks

#### 1. FastAPI Example (`python/fastapi/`)

```bash
cd python/fastapi
python -m venv venv && source venv/bin/activate
pip install -r requirements.txt
cp .env.example .env  # Update FEEDPLE_API_KEY & FEEDPLE_WIDGET_PUBLIC_KEY
uvicorn main:app --reload --port 8000
```
*Access dashboard at `http://localhost:8000`.*

#### 2. Flask Example (`python/flask/`)

```bash
cd python/flask
python -m venv venv && source venv/bin/activate
pip install -r requirements.txt
cp .env.example .env  # Update FEEDPLE_API_KEY & FEEDPLE_WIDGET_PUBLIC_KEY
python app.py
```
*Access dashboard at `http://localhost:5000`.*

#### 3. Django Example (`python/django/`)

```bash
cd python/django
python -m venv venv && source venv/bin/activate
pip install -r requirements.txt
cp .env.example .env  # Update FEEDPLE_API_KEY & FEEDPLE_WIDGET_PUBLIC_KEY
python manage.py migrate
python manage.py runserver 8000
```
*Access dashboard at `http://localhost:8000`.*

---

### 🐘 PHP Frameworks

#### 4. Laravel Example (`php/laravel/`)

```bash
cd php/laravel
composer install
cp .env.example .env  # Update FEEDPLE_API_KEY & FEEDPLE_WIDGET_PUBLIC_KEY
php artisan serve
```
*Access dashboard at `http://localhost:8000`.*

#### 5. Pure PHP Example (`php/pure-php/`)

```bash
cd php/pure-php
composer install
cp .env.example .env  # Update FEEDPLE_API_KEY & FEEDPLE_WIDGET_PUBLIC_KEY
php -S localhost:8000
```
*Access dashboard at `http://localhost:8000`.*

---

## 🔗 Documentation Links

- [Feedple Python SDK Documentation (PyPI)](https://pypi.org/project/feedple-sdk/)
- [Feedple PHP SDK Documentation (Packagist)](https://packagist.org/packages/feedple/feedple-sdk)
- [Feedple Web Widget Integration Guide](https://feedple-ai-psi.vercel.app/docs/widget)

---

## 📄 License

This repository is licensed under the [MIT License](LICENSE).
