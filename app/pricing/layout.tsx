import type { Metadata } from "next";

export const metadata: Metadata = {
  title: "Pricing",
  alternates: {
    canonical: "/pricing",
  },
};

export default function Layout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return children;
}
