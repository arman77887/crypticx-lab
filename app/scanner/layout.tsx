import type { Metadata } from "next";

export const metadata: Metadata = {
  title: "Security Scanner",
  alternates: {
    canonical: "/scanner",
  },
};

export default function Layout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return children;
}
