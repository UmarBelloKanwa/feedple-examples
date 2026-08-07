# Flask + Feedple SDK & AI Web Widget Example

This directory contains a complete, production-ready **Flask** application demonstrating how to integrate the **Feedple Python SDK** and **Feedple AI Web Widget**.

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

### 3. Run the Application

```bash
python app.py
```

Visit `http://localhost:5000` to access the application.
