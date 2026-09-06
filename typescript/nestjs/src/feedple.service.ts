import { Injectable, OnModuleInit, OnModuleDestroy, Logger } from "@nestjs/common";
import Database from "better-sqlite3";
import { FeedpleSDK, SqliteAdapter } from "feedple-sdk";

@Injectable()
export class FeedpleService implements OnModuleInit, OnModuleDestroy {
  private readonly logger = new Logger(FeedpleService.name);
  private sdk: FeedpleSDK;
  private db: Database.Database;

  constructor() {
    this.db = new Database("app.db");
    this.db.exec(`
      CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY, name TEXT, email TEXT);
      CREATE TABLE IF NOT EXISTS orders (id INTEGER PRIMARY KEY, total REAL, status TEXT);
    `);

    const apiKey = process.env.FEEDPLE_API_KEY || "sk_live_demo_key";

    this.sdk = new FeedpleSDK({
      apiKey,
      db: new SqliteAdapter(this.db),
      identity: {
        allowedTables: ["users", "orders"],
      },
      autoSync: true,
    });
  }

  async onModuleInit() {
    try {
      await this.sdk.connect();
      this.logger.log("✅ Feedple SDK connected successfully in NestJS");
    } catch (err: any) {
      this.logger.error("⚠️ Feedple SDK connection error: " + err.message);
    }
  }

  async onModuleDestroy() {
    await this.sdk.close();
    this.logger.log("Feedple SDK closed");
  }
}
