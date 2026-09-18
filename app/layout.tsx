import type { Metadata } from "next";
import localFont from "next/font/local";
import "./globals.css";
import SiteShell from "@/components/SiteShell";

const geistSans = localFont({
  src: "./fonts/GeistVF.woff",
  variable: "--font-geist-sans",
  weight: "100 900",
});

const geistMono = localFont({
  src: "./fonts/GeistMonoVF.woff",
  variable: "--font-geist-mono",
  weight: "100 900",
});

export const metadata: Metadata = {
  metadataBase: new URL("https://crypticxlab.duckdns.org"),

  title: {
    default: "CrypticX Lab | Cybersecurity Intelligence Platform",
    template: "%s | CrypticX Lab",
  },

  description:
    "CrypticX Lab is a cybersecurity intelligence platform for authorized web security, network analysis, DNS intelligence, SSL/TLS analysis, API security, and security assessments.",

  applicationName: "CrypticX Lab",

  verification: {
    google: "saRVQ0sQOUksBmUf-LJFiFPzkeKOKSAZUXFzX-S-zPY",
  },

  alternates: {
    canonical: "/",
  },

  keywords: [
    "CrypticX Lab",
    "cybersecurity",
    "web security",
    "network analysis",
    "DNS intelligence",
    "SSL TLS security",
    "API security",
    "security assessment",
  ],

  authors: [{ name: "CrypticX Lab" }],
  creator: "CrypticX Lab",
  publisher: "CrypticX Lab",

  robots: {
    index: true,
    follow: true,
  },

  openGraph: {
    type: "website",
    url: "https://crypticxlab.duckdns.org",
    siteName: "CrypticX Lab",
    title: "CrypticX Lab | Cybersecurity Intelligence Platform",
    description:
      "Authorized cybersecurity assessments, network analysis, DNS intelligence, SSL/TLS analysis, API security, and more.",
  },

  twitter: {
    card: "summary_large_image",
    title: "CrypticX Lab | Cybersecurity Intelligence Platform",
    description:
      "Authorized cybersecurity assessments, network analysis, DNS intelligence, SSL/TLS analysis, API security, and more.",
  },
};

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html lang="en">
      <body
        className={`${geistSans.variable} ${geistMono.variable} antialiased`}
      >
        <SiteShell>{children}</SiteShell>
      </body>
    </html>
  );
}
