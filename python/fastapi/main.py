import os
import logging
from contextlib import asynccontextmanager
from dotenv import load_dotenv
from fastapi import FastAPI, Request
from fastapi.templating import Jinja2Templates
from fastapi.responses import HTMLResponse
from feedple_sdk import FeedpleSDK, Identity

from database import engine, init_db

# Load environment variables from .env
load_dotenv()

# Configure Logging
logging.basicConfig(level=os.getenv("LOG_LEVEL", "INFO"))
logger = logging.getLogger("feedple.fastapi")

FEEDPLE_API_KEY = os.getenv("FEEDPLE_API_KEY", "sk_live_demo_key")
WIDGET_PUBLIC_KEY = os.getenv("FEEDPLE_WIDGET_PUBLIC_KEY", "wpk_demo_public_key")

sdk = None

@asynccontextmanager
async def lifespan(app: FastAPI):
    global sdk
    logger.info("Initializing database...")
    init_db()

    logger.info("Initializing Feedple SDK background worker...")
    identity = Identity(
        name="fastapi-app",
        allowed_tables=["users", "products", "orders"]
    )

    try:
        sdk = FeedpleSDK(
            api_key=FEEDPLE_API_KEY,
            db=engine,
            identity=identity,
            auto_sync=True,
            sync_interval=60
        )
        logger.info("Feedple SDK started successfully in non-blocking background thread.")
    except Exception as e:
        logger.error(f"Failed to start Feedple SDK: {e}")

    yield

    if sdk:
        logger.info("Stopping Feedple SDK background worker...")
        sdk.stop()
        logger.info("Feedple SDK stopped cleanly.")

app = FastAPI(title="FastAPI Feedple AI Integration Example", lifespan=lifespan)
templates = Jinja2Templates(directory="templates")

@app.get("/", response_class=HTMLResponse)
async def read_root(request: Request):
    return templates.TemplateResponse(
        "index.html",
        {
            "request": request,
            "widget_public_key": WIDGET_PUBLIC_KEY
        }
    )

@app.get("/api/health")
async def health_check():
    return {
        "status": "healthy",
        "feedple_sdk_active": sdk is not None,
        "database": "connected"
    }

if __name__ == "__main__":
    import uvicorn
    port = int(os.getenv("PORT", 8000))
    uvicorn.run("main:app", host="0.0.0.0", port=port, reload=True)
