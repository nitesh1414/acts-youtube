import type { Metadata } from "next";
import type { ReactNode } from "react";
import Script from 'next/script';
import "./globals.css";

export const metadata: Metadata = {
  title: "Arena Next.js PostgreSQL Starter",
  description: "Starter template with Next.js, Drizzle, and PostgreSQL.",
};

export default function RootLayout({ children }: { children: ReactNode }) {
  return (
    <html lang="en">
      <head>
        <link rel="manifest" href="/manifest.json" />
        <meta name="theme-color" content="#2563eb" />
        <link rel="apple-touch-icon" href="/icons/icon-192x192.png" />
      </head>
      <body className="bg-slate-100 text-slate-900 antialiased">
        <Script src="https://checkout.razorpay.com/v1/checkout.js" />
        <Script id="sw-register">
          {`
            if ('serviceWorker' in navigator) {
              window.addEventListener('load', () => {
                navigator.serviceWorker.register('/sw.js').then(reg => {
                  console.log('SW registered:', reg);
                }).catch(err => {
                  console.log('SW registration failed:', err);
                });
              });
            }
          `}
        </Script>
        {children}
      </body>
    </html>
  );
}
