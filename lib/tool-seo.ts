export type ToolSeo = {
  title: string;
  description: string;
};

export const toolSeo: Record<string, Record<string, ToolSeo>> = {
  api: {
    "endpoint-inspector": {
      title: "API Endpoint Inspector",
      description: "Inspect API response status, media type, timing, redirects, and endpoint metadata.",
    },
    "http-method-review": {
      title: "API HTTP Method Review",
      description: "Review advertised API HTTP methods and CORS method metadata without destructive requests.",
    },
    "security-headers": {
      title: "API Security Headers Analyzer",
      description: "Inspect important HTTP security headers returned by an API endpoint.",
    },
    "api-configuration": {
      title: "API Configuration Analyzer",
      description: "Review API transport, caching, authentication hints, CORS, and information disclosure.",
    },
  },

  dns: {
    "record-inspector": {
      title: "DNS Record Inspector",
      description: "Inspect public DNS records and domain configuration with CrypticX Lab.",
    },
    "subdomain-discovery": {
      title: "Subdomain Discovery",
      description: "Analyze bounded public DNS data to discover common subdomains for an authorized domain.",
    },
    "dns-health": {
      title: "DNS Health Analyzer",
      description: "Review DNS configuration and domain health signals.",
    },
  },

  ssl: {
    "certificate-check": {
      title: "SSL Certificate Checker",
      description: "Inspect an SSL/TLS certificate, validity information, and certificate metadata.",
    },
    "tls-analysis": {
      title: "TLS Security Analyzer",
      description: "Analyze TLS configuration, protocols, and security characteristics.",
    },
    "cipher-review": {
      title: "TLS Cipher Review",
      description: "Review TLS cipher configuration and related security information.",
    },
    "certificate-chain": {
      title: "Certificate Chain Inspector",
      description: "Inspect the SSL/TLS certificate chain and trust-chain information.",
    },
  },

  web: {
    "security-headers": {
      title: "Website Security Headers Checker",
      description: "Inspect important HTTP response security headers for a website.",
    },
    "http-analysis": {
      title: "HTTP Website Analyzer",
      description: "Inspect HTTP status, response metadata, server information, and timing.",
    },
    "cookie-security": {
      title: "Cookie Security Checker",
      description: "Review Secure, HttpOnly, and SameSite attributes in website cookies.",
    },
    "cors-review": {
      title: "CORS Configuration Checker",
      description: "Inspect cross-origin resource sharing response configuration.",
    },
    "phishing-link-analyzer": {
      title: "Phishing Link Analyzer",
      description: "Inspect a URL for static phishing and deception indicators without visiting the destination.",
    },
  },

  network: {
    "port-analysis": {
      title: "Network Port Analysis",
      description: "Analyze a bounded set of common TCP ports on an authorized saved target.",
    },
    "service-discovery": {
      title: "Network Service Discovery",
      description: "Identify likely services from reachable standard ports on an authorized target.",
    },
    "network-inspector": {
      title: "Network Inspector",
      description: "Inspect validated public network addresses and reachable services.",
    },
    "exposure-review": {
      title: "Network Exposure Review",
      description: "Review externally reachable services and evidence-backed network exposure signals.",
    },
  },

  recon: {
    "asset-discovery": {
      title: "Asset Discovery",
      description: "Perform bounded discovery of common public-facing assets for an authorized target.",
    },
    "technology-detection": {
      title: "Website Technology Detection",
      description: "Inspect HTTP headers and public HTML signals to identify website technologies.",
    },
    "metadata-inspector": {
      title: "Website Metadata Inspector",
      description: "Review public page metadata and selected HTTP information for an authorized target.",
    },
    whois: {
      title: "WHOIS & RDAP Lookup",
      description: "Retrieve public domain registration information through RDAP.",
    },
  },

  sql: {
    "query-analyzer": {
      title: "SQL Query Analyzer",
      description: "Analyze SQL query structure and characteristics in a controlled local lab.",
    },
    "schema-inspector": {
      title: "SQL Schema Inspector",
      description: "Inspect SQL schema definitions and database structure.",
    },
    "sql-formatter": {
      title: "SQL Formatter",
      description: "Format SQL into a cleaner and more readable structure.",
    },
    "data-profiler": {
      title: "SQL Data Profiler",
      description: "Profile structured sample data in the CrypticX Lab SQL environment.",
    },
    "security-analyzer": {
      title: "SQL Security Analyzer",
      description: "Review SQL text for security-relevant patterns in a controlled lab.",
    },
    "parameterization-coach": {
      title: "SQL Parameterization Coach",
      description: "Learn safer SQL parameterization patterns using controlled examples.",
    },
    "query-risk-report": {
      title: "SQL Query Risk Report",
      description: "Review SQL statements and generate a structured query-risk assessment.",
    },
  },

  data: {
    "csv-analyzer": {
      title: "CSV Analyzer",
      description: "Inspect CSV structure and data characteristics.",
    },
    "json-inspector": {
      title: "JSON Inspector",
      description: "Inspect and analyze JSON structure in a controlled local tool.",
    },
    "data-cleaner": {
      title: "Data Cleaner",
      description: "Clean duplicate, blank, and inconsistent text data.",
    },
    "pattern-analysis": {
      title: "Pattern & IOC Analyzer",
      description: "Inspect text for useful patterns and common indicators.",
    },
    "log-analyzer": {
      title: "Security Log Analyzer",
      description: "Analyze security log text and identify notable events and patterns.",
    },
    "http-inspector": {
      title: "HTTP Request & Response Inspector",
      description: "Inspect raw HTTP request or response text and its structure.",
    },
    "encoding-studio": {
      title: "Encoding Studio",
      description: "Work with common text encoding representations in a local lab.",
    },
    "hash-inspector": {
      title: "Hash Inspector",
      description: "Inspect hash strings and identify common hash characteristics.",
    },
    "jwt-inspector": {
      title: "JWT Inspector",
      description: "Inspect JWT structure and claims locally without sending the token to a target.",
    },
    "regex-lab": {
      title: "Regex Lab",
      description: "Test regular-expression patterns against controlled text input.",
    },
    "data-diff": {
      title: "Structured Data Diff",
      description: "Compare structured data and identify differences.",
    },
    "sensitive-data-redactor": {
      title: "Sensitive Data Redactor",
      description: "Detect and redact common sensitive-data patterns from supplied text.",
    },
  },
};

export function getToolSeo(
  category: string,
  slug: string,
): ToolSeo | null {
  return toolSeo[category]?.[slug] ?? null;
}
