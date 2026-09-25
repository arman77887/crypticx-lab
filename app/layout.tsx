import type { Metadata } from "next";
import Script from "next/script";
import localFont from "next/font/local";
import "./globals.css";
import SiteShell from "@/components/SiteShell";

const structuredData = {
  "@context": "https://schema.org",
  "@graph": [
    {
      "@type": "WebSite",
      "@id": "https://crypticxlab.duckdns.org/#website",
      url: "https://crypticxlab.duckdns.org/",
      name: "CrypticX Lab",
      description:
        "Cybersecurity intelligence platform for authorized security assessments, network analysis, DNS intelligence, SSL/TLS analysis, and API security.",
      publisher: {
        "@id": "https://crypticxlab.duckdns.org/#organization",
      },
    },
    {
      "@type": "Organization",
      "@id": "https://crypticxlab.duckdns.org/#organization",
      name: "CrypticX Lab",
      url: "https://crypticxlab.duckdns.org/",
      logo: {
        "@type": "ImageObject",
        url: "https://crypticxlab.duckdns.org/brand/crypticx-lab-logo.svg",
      },
    },
  ],
};

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
  const gaMeasurementId =
    process.env.NEXT_PUBLIC_GA_MEASUREMENT_ID;

  return (
    <html lang="en">
      <body
        className={`${geistSans.variable} ${geistMono.variable} antialiased`}
      >
        <script
          type="application/ld+json"
          dangerouslySetInnerHTML={{
            __html: JSON.stringify(structuredData).replace(/</g, "\\u003c"),
          }}
        />
        <SiteShell>{children}</SiteShell>

        {gaMeasurementId ? (
          <>
            <Script
              src={`https://www.googletagmanager.com/gtag/js?id=${gaMeasurementId}`}
              strategy="afterInteractive"
            />
            <Script id="google-analytics" strategy="afterInteractive">
              {`
                window.dataLayer = window.dataLayer || [];
                function gtag(){dataLayer.push(arguments);}
                gtag('js', new Date());
                gtag('config', '${gaMeasurementId}');
              `}
            </Script>
          </>
        ) : null}
      </body>
    </html>
  );
}
