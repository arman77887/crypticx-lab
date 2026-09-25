import type { Metadata } from "next";

export const metadata: Metadata = {
  title: "Security Overview",
  description:
    "Explore CrypticX Lab security capabilities across web, API, network, DNS, SSL/TLS, and security configuration analysis.",
  alternates: {
    canonical: "/security",
  },
};

export default function Layout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return children;
}
