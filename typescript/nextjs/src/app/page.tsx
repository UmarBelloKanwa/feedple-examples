import Script from "next/script";
import { getFeedpleSDK } from "@/lib/feedple";

export default async function HomePage() {
  // Ensure Feedple SDK is connected on server side
  try {
    await getFeedpleSDK();
  } catch (err: any) {
    console.error("Feedple SDK init error:", err.message);
  }

  const widgetKey = process.env.NEXT_PUBLIC_FEEDPLE_WIDGET_KEY || "wpk_demo_key";

  return (
    <main style={{ maxWidth: "800px", margin: "40px auto", padding: "0 20px", fontFamily: "system-ui, sans-serif" }}>
      <div style={{ background: "#f8fafc", border: "1px solid #e2e8f0", borderRadius: "8px", padding: "24px" }}>
        <h1>▲ Next.js + Feedple SDK Example</h1>
        <p>Feedple SDK is running on Next.js server-side, syncing your database schema securely with Feedple AI platform.</p>
        <p>Ask questions using the Feedple AI web widget in the bottom-right corner!</p>
      </div>

      <Script
        src="https://feedple.com/widget.js"
        data-public-key={widgetKey}
        data-theme-color="#9333ea"
        data-title="Feedple AI Assistant"
        strategy="lazyOnload"
      />
    </main>
  );
}
