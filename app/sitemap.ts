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

  const toolRoutes = [
    "/tools/api/endpoint-inspector",
    "/tools/api/http-method-review",
    "/tools/api/security-headers",
    "/tools/api/api-configuration",

    "/tools/dns/record-inspector",
    "/tools/dns/subdomain-discovery",
    "/tools/dns/dns-health",

    "/tools/ssl/certificate-check",
    "/tools/ssl/tls-analysis",
    "/tools/ssl/cipher-review",
    "/tools/ssl/certificate-chain",

    "/tools/web/security-headers",
    "/tools/web/http-analysis",
    "/tools/web/cookie-security",
    "/tools/web/cors-review",
    "/tools/web/phishing-link-analyzer",

    "/tools/network/port-analysis",
    "/tools/network/service-discovery",
    "/tools/network/network-inspector",
    "/tools/network/exposure-review",

    "/tools/recon/asset-discovery",
    "/tools/recon/technology-detection",
    "/tools/recon/metadata-inspector",
    "/tools/recon/whois",

    "/tools/sql/query-analyzer",
    "/tools/sql/schema-inspector",
    "/tools/sql/sql-formatter",
    "/tools/sql/data-profiler",
    "/tools/sql/security-analyzer",
    "/tools/sql/parameterization-coach",
    "/tools/sql/query-risk-report",

    "/tools/data/csv-analyzer",
    "/tools/data/json-inspector",
    "/tools/data/data-cleaner",
    "/tools/data/pattern-analysis",
    "/tools/data/log-analyzer",
    "/tools/data/http-inspector",
    "/tools/data/encoding-studio",
    "/tools/data/hash-inspector",
    "/tools/data/jwt-inspector",
    "/tools/data/regex-lab",
    "/tools/data/data-diff",
    "/tools/data/sensitive-data-redactor",
  ];

  const allRoutes = [...routes, ...toolRoutes];

  return allRoutes.map((route) => ({
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
