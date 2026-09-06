import Database from "better-sqlite3";
import { FeedpleSDK, SqliteAdapter } from "feedple-sdk";

let sdkInstance: FeedpleSDK | null = null;

export async function getFeedpleSDK(): Promise<FeedpleSDK> {
  if (sdkInstance) return sdkInstance;

  const db = new Database("app.db");
  db.exec(`
    CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY, name TEXT, email TEXT);
    CREATE TABLE IF NOT EXISTS orders (id INTEGER PRIMARY KEY, total REAL, status TEXT);
  `);

  const apiKey = process.env.FEEDPLE_API_KEY || "sk_live_demo_key";

  sdkInstance = new FeedpleSDK({
    apiKey,
    db: new SqliteAdapter(db),
    identity: {
      allowedTables: ["users", "orders"],
    },
    autoSync: true,
  });

  await sdkInstance.connect();
  return sdkInstance;
}
