import type { Metadata } from "next";

export const metadata: Metadata = {
  title: "Security Labs",
  alternates: {
    canonical: "/labs",
  },
};

export default function Layout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return children;
}
