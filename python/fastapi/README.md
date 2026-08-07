# FastAPI + Feedple SDK & AI Web Widget Example

This directory contains a complete, production-ready **FastAPI** application demonstrating how to integrate the **Feedple Python SDK** and **Feedple AI Web Widget**.

---

## 🚀 Features

- **FastAPI Lifespan Management**: Automatically starts `FeedpleSDK` in a non-blocking background thread on startup and stops it cleanly on shutdown.
- **SQLAlchemy Integration**: Syncs database tables (`users`, `products`, `orders`) securely with Feedple AI.
- **Embedded Web Widget**: Renders a floating AI chat widget in HTML using `https://feedple-ai-psi.vercel.app/widget.js`.
- **Health Check Endpoint**: Includes `/api/health` monitoring.

---

## 🛠️ Prerequisites

- Python 3.9+
- A Feedple Workspace API Key (`sk_live_...`)
- A Feedple Widget Public Key (`wpk_...`)

---

## ⚙️ Quick Start

### 1. Create & Activate Virtual Environment

```bash
python -m venv venv
source venv/bin/activate  # On Windows: venv\Scripts\activate
```

### 2. Install Dependencies

```bash
pip install -r requirements.txt
```

### 3. Configure Environment Variables

Copy `.env.example` to `.env`:

```bash
cp .env.example .env
```

Update `.env` with your Feedple credentials:

```env
FEEDPLE_API_KEY=sk_live_your_actual_api_key
FEEDPLE_WIDGET_PUBLIC_KEY=wpk_your_actual_widget_public_key
```

### 4. Run the Application

```bash
uvicorn main:app --reload --port 8000
```

Open your browser at `http://localhost:8000`. You will see the application dashboard with the floating Feedple AI Web Widget on the bottom right!

---

## 📁 Code Overview

- `main.py`: Main FastAPI application entry point with lifespan hook and routes.
- `database.py`: SQLAlchemy models (`User`, `Product`, `Order`) and database seed logic.
- `templates/index.html`: Web interface containing the Feedple AI Web Widget tag.
