import os
import atexit
import logging
from dotenv import load_dotenv
from flask import Flask, render_template, jsonify
from feedple_sdk import FeedpleSDK, Identity

from database import engine, init_db

load_dotenv()

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger("feedple.flask")

app = Flask(__name__)

FEEDPLE_API_KEY = os.getenv("FEEDPLE_API_KEY", "sk_live_demo_key")
WIDGET_PUBLIC_KEY = os.getenv("FEEDPLE_WIDGET_PUBLIC_KEY", "wpk_demo_public_key")

# 1. Initialize Database
init_db()

# 2. Initialize Feedple SDK
logger.info("Initializing Feedple SDK for Flask...")
identity = Identity(
    name="flask-app",
    allowed_tables=["customers", "invoices"]
)

try:
    sdk = FeedpleSDK(
        api_key=FEEDPLE_API_KEY,
        db=engine,
        identity=identity,
        auto_sync=True
    )
    logger.info("Feedple SDK started in background thread.")
except Exception as e:
    logger.error(f"Failed to start Feedple SDK: {e}")
    sdk = None

# Ensure clean shutdown of background thread when Flask exits
def shutdown_sdk():
    if sdk:
        logger.info("Stopping Feedple SDK...")
        sdk.stop()

atexit.register(shutdown_sdk)

@app.route("/")
def index():
    return render_template("index.html", widget_public_key=WIDGET_PUBLIC_KEY)

@app.route("/health")
def health():
    return jsonify({
        "status": "healthy",
        "feedple_sdk_active": sdk is not None
    })

if __name__ == "__main__":
    port = int(os.getenv("FLASK_PORT", 5000))
    debug = os.getenv("FLASK_DEBUG", "True").lower() == "true"
    app.run(host="0.0.0.0", port=port, debug=debug)
