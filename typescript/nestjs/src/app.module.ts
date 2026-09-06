import { Module } from "@nestjs/common";
import { AppController } from "./app.controller";
import { FeedpleService } from "./feedple.service";

@Module({
  imports: [],
  controllers: [AppController],
  providers: [FeedpleService],
})
export class AppModule {}
