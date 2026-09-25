import type { Metadata } from "next";
import { getToolSeo } from "@/lib/tool-seo";

export async function generateMetadata({
  params,
}: {
  params: Promise<{ tool: string }>;
}): Promise<Metadata> {
  const { tool } = await params;
  const seo = getToolSeo("network", tool);

  if (!seo) {
    return {
      title: "Tool Not Found",
      robots: {
        index: false,
        follow: false,
        googleBot: {
          index: false,
          follow: false,
        },
      },
    };
  }

  return {
    title: seo.title,
    description: seo.description,
    alternates: {
      canonical: "/tools/network/" + encodeURIComponent(tool),
    },
  };
}

export default function Layout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return children;
}
