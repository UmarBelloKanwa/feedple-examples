import express from "express";
import dotenv from "dotenv";
import Database from "better-sqlite3";
import { FeedpleSDK, SqliteAdapter } from "feedple-sdk";

dotenv.config();

const app = express();
const port = process.env.PORT || 3000;

// Initialize SQLite database
const db = new Database("app.db");
db.exec(`
  CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    email TEXT NOT NULL,
    role TEXT NOT NULL
  );

  CREATE TABLE IF NOT EXISTS orders (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    total REAL NOT NULL,
    status TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
  );
`);

// Initialize Feedple SDK with SQLite adapter
const apiKey = process.env.FEEDPLE_API_KEY || "sk_live_demo_key";
const widgetKey = process.env.FEEDPLE_WIDGET_PUBLIC_KEY || "wpk_demo_key";

const sdk = new FeedpleSDK({
  apiKey,
  db: new SqliteAdapter(db),
  identity: {
    allowedTables: ["users", "orders"],
    allTables: false,
  },
  autoSync: true,
  syncInterval: 60,
});

sdk
  .connect()
  .then(() => console.log("✅ Feedple SDK connected successfully"))
  .catch((err) => console.error("⚠️ Feedple SDK connection error:", err.message));

app.get("/", (_req, res) => {
  res.send(`
    <!DOCTYPE html>
    <html>
      <head>
        <title>Feedple SDK + Express.js Example</title>
        <style>
          body { font-family: system-ui, sans-serif; max-width: 800px; margin: 40px auto; padding: 0 20px; }
          .card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 24px; }
          code { background: #e2e8f0; padding: 2px 6px; border-radius: 4px; }
        </style>
      </head>
      <body>
        <div class="card">
          <h1>⚡ Express.js + Feedple SDK Example</h1>
          <p>Feedple SDK is running in the background, syncing your database schema securely with Feedple AI platform.</p>
          <p>Ask questions using the Feedple AI web widget in the bottom-right corner!</p>
        </div>

        <!-- Feedple Web Widget Integration -->
        <script
          src="https://feedple.com/widget.js"
          data-public-key="${widgetKey}"
          data-theme-color="#9333ea"
          data-title="Feedple AI Assistant"
          defer
        ></script>
      </body>
    </html>
  `);
});

const server = app.listen(port, () => {
  console.log(`🚀 Express server running at http://localhost:${port}`);
});

process.on("SIGINT", async () => {
  console.log("Shutting down...");
  await sdk.close();
  server.close();
  process.exit(0);
});
