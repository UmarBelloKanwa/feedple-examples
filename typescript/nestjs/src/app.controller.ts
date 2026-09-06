import { Controller, Get, Header } from "@nestjs/common";

@Controller()
export class AppController {
  @Get()
  @Header("Content-Type", "text/html")
  getHome(): string {
    const widgetKey = process.env.FEEDPLE_WIDGET_PUBLIC_KEY || "wpk_demo_key";
    return `
      <!DOCTYPE html>
      <html>
        <head>
          <title>Feedple SDK + NestJS Example</title>
          <style>
            body { font-family: system-ui, sans-serif; max-width: 800px; margin: 40px auto; padding: 0 20px; }
            .card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 24px; }
          </style>
        </head>
        <body>
          <div class="card">
            <h1>🦅 NestJS + Feedple SDK Example</h1>
            <p>Feedple SDK is running via NestJS Lifecycle Hooks (<code>OnModuleInit</code>) and syncing database schemas securely.</p>
          </div>

          <script
            src="https://feedple.com/widget.js"
            data-public-key="${widgetKey}"
            data-theme-color="#9333ea"
            data-title="Feedple AI Assistant"
            defer
          ></script>
        </body>
      </html>
    `;
  }
}
