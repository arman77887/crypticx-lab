import type { MetadataRoute } from "next";

export default function sitemap(): MetadataRoute.Sitemap {
  const baseUrl = "https://crypticxlab.duckdns.org";

  const routes = [
    "",
    "/about",
    "/tools",
    "/scanner",
    "/labs",
    "/docs",
    "/security",
    "/pricing",
    "/contact",
    "/terms",
    "/privacy",
    "/refund",
  ];

  return routes.map((route) => ({
    url: `${baseUrl}${route}`,
    lastModified: new Date(),
    changeFrequency: route === "" ? "weekly" : "monthly",
    priority:
      route === ""
        ? 1
        : route === "/tools" ||
            route === "/scanner" ||
            route === "/labs" ||
            route === "/docs" ||
            route === "/security"
          ? 0.8
          : 0.6,
  }));
}
