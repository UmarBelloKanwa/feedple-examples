import os
import sys
import logging
from django.apps import AppConfig
from django.conf import settings

logger = logging.getLogger("feedple.django")

class CoreConfig(AppConfig):
    default_auto_field = 'django.db.models.BigAutoField'
    name = 'core'

    def ready(self):
        # Prevent running SDK twice during Django auto-reloader process initialization
        if 'runserver' in sys.argv and os.environ.get('RUN_MAIN') != 'true':
            return

        api_key = getattr(settings, 'FEEDPLE_API_KEY', None)
        if not api_key or api_key == "sk_live_demo_key":
            logger.warning("Feedple API Key not configured. Skipping SDK initialization.")
            return

        try:
            from sqlalchemy import create_engine
            from feedple_sdk import FeedpleSDK, Identity

            db_path = settings.DATABASES['default']['NAME']
            engine = create_engine(f"sqlite:///{db_path}")

            identity = Identity(
                name="django-app",
                allowed_tables=["organizations", "tickets"]
            )

            logger.info("Initializing Feedple SDK for Django...")
            self.sdk = FeedpleSDK(
                api_key=api_key,
                db=engine,
                identity=identity,
                auto_sync=True
            )
            logger.info("Feedple SDK background sync started for Django.")
        except Exception as e:
            logger.error(f"Failed to initialize Feedple SDK in Django: {e}")
