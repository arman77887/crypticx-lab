import type { Metadata } from "next";

export const metadata: Metadata = {
  title: "Documentation",
  alternates: {
    canonical: "/docs",
  },
};

export default function Layout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return children;
}
