import Link from "next/link";

const categories = [
  {
    icon: "◎",
    title: "Web Security",
    description: "Assess web applications for common security weaknesses and configuration issues.",
    tools: [
      "Security Headers",
      "HTTP Analysis",
      "Cookie Security",
      "CORS Review",
      "Phishing Link Analyzer",
    ],
  },
  {
    icon: "⌁",
    title: "Network Analysis",
    description: "Understand authorized network assets, services and externally observable exposure.",
    tools: ["Port Analysis", "Service Discovery", "Network Inspector", "Exposure Review"],
  },
  {
    icon: "◌",
    title: "DNS Intelligence",
    description: "Inspect DNS configuration and collect infrastructure intelligence.",
    tools: ["DNS Lookup", "Record Inspector", "Subdomain Discovery", "DNS Health"],
  },
  {
    icon: "◇",
    title: "SSL / TLS",
    description: "Review certificates, protocols, cipher configuration and transport security.",
    tools: ["Certificate Check", "TLS Analysis", "Cipher Review", "Certificate Chain"],
  },
  {
    icon: "⌘",
    title: "API Security",
    description: "Analyze authorized API endpoints and security controls.",
    tools: ["Endpoint Inspector", "HTTP Method Review", "Security Headers", "API Configuration"],
  },
  {
    icon: "▣",
    title: "SQL Lab",
    description: "Work with SQL safely through controlled analysis and defensive database tooling.",
    tools: [
      "Query Analyzer",
      "Schema Inspector",
      "SQL Formatter",
      "Data Profiler",
      "SQL Security Analyzer",
      "Parameterization Coach",
      "Query Risk Report",
    ],
  },
  {
    icon: "△",
    title: "Data Lab",
    description: "Transform, inspect and analyze structured security datasets.",
    tools: [
      "CSV Analyzer",
      "JSON Inspector",
      "Data Cleaner",
      "Pattern & IOC Analysis",
      "Security Log Analyzer",
      "HTTP Inspector",
      "Encoding Studio",
      "Hash Inspector",
      "JWT Inspector",
      "Regex Lab",
      "Structured Data Diff",
      "Sensitive Data Redactor",
    ],
  },
  {
    icon: "⌖",
    title: "Reconnaissance",
    description: "Organize authorized reconnaissance and publicly observable intelligence.",
    tools: ["Asset Discovery", "Technology Detection", "Metadata Inspector", "WHOIS"],
  },
];

export default function ToolsPage() {
  return (
    <main className="min-h-screen bg-[#09090b] text-white">
      <div className="mx-auto max-w-7xl px-5 sm:px-8">
        <header className="flex h-20 items-center justify-between">
          <Link href="/" className="flex items-center gap-3">
            <div className="flex h-11 w-11 items-center justify-center">
              <img
                src="/brand/crypticx2.png"
                alt="CrypticX Lab"
                width={48}
                height={48}
                className="h-11 w-11 object-contain"
              />
            </div>
            <div>
              <div className="text-lg font-bold tracking-tight">CrypticX Lab</div>
              <div className="text-[10px] font-semibold uppercase tracking-[0.24em] text-white/40">
                Security Intelligence
              </div>
            </div>
          </Link>

          <div className="flex items-center gap-3">
            <Link
              href="/scanner"
              className="hidden rounded-xl px-4 py-2.5 text-sm font-semibold text-white/65 sm:block"
            >
              Scanner
            </Link>
            <Link
              href="/"
              className="rounded-xl bg-[#09090b] px-4 py-2.5 text-sm font-semibold text-white/65 shadow-[5px_5px_10px_#000000,-5px_-5px_10px_#171719]"
            >
              ← Home
            </Link>
          </div>
        </header>

        <section className="py-16 sm:py-24">
          <div className="max-w-3xl">
            <p className="text-xs font-bold uppercase tracking-[0.22em] text-red-400">
              Security toolkit
            </p>

            <h1 className="mt-3 text-4xl font-black tracking-tight sm:text-6xl">
              Tools for every stage of analysis.
            </h1>

            <p className="mt-5 max-w-2xl text-base leading-7 text-white/45">
              CrypticX Lab is designed as a modular security workspace. Use
              individual tools for focused analysis or combine them into a
              complete authorized assessment.
            </p>
          </div>

          <div className="mt-14 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            {categories.map((category) => (
              <section
                key={category.title}
                className="group rounded-[2rem] bg-[#09090b] p-6 shadow-[8px_8px_18px_#000000,-8px_-8px_18px_#171719] transition duration-300 hover:-translate-y-1"
              >
                <div className="flex items-start justify-between">
                  <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-[#09090b] text-xl font-bold text-red-400 shadow-[inset_4px_4px_8px_#020203,inset_-4px_-4px_8px_#171719]">
                    {category.icon}
                  </div>

                  <span className="rounded-xl bg-[#09090b] px-2.5 py-1.5 text-[10px] font-bold text-white/35 shadow-[inset_2px_2px_5px_#020203,inset_-2px_-2px_5px_#171719]">
                    {category.tools.length} TOOLS
                  </span>
                </div>

                <h2 className="mt-6 text-xl font-black">{category.title}</h2>

                <p className="mt-2 min-h-[72px] text-sm leading-6 text-white/45">
                  {category.description}
                </p>

                <div className="mt-5 space-y-2">
                  {category.tools.map((tool) => (
                    <Link
                      key={tool}
                      href={
                        category.title === "DNS Intelligence" &&
                        tool === "DNS Lookup"
                          ? "/tools/dns"
                          : category.title === "Web Security" &&
                            tool === "Security Headers"
                          ? "/tools/web/security-headers"
                          : category.title === "Web Security" &&
                            tool === "HTTP Analysis"
                          ? "/tools/web/http-analysis"
                          : category.title === "Web Security" &&
                            tool === "Cookie Security"
                          ? "/tools/web/cookie-security"
                          : category.title === "Web Security" &&
                            tool === "CORS Review"
                          ? "/tools/web/cors-review"
                          : category.title === "Web Security" &&
                            tool === "Phishing Link Analyzer"
                          ? "/tools/web/phishing-link-analyzer"
                          : category.title === "API Security" &&
                            tool === "Endpoint Inspector"
                          ? "/tools/api/endpoint-inspector"
                          : category.title === "API Security" &&
                            tool === "HTTP Method Review"
                          ? "/tools/api/http-method-review"
                          : category.title === "API Security" &&
                            tool === "Security Headers"
                          ? "/tools/api/security-headers"
                          : category.title === "API Security" &&
                            tool === "API Configuration"
                          ? "/tools/api/api-configuration"
                                                    : category.title === "DNS Intelligence" &&
                            tool === "Record Inspector"
                          ? "/tools/dns/record-inspector"
                          : category.title === "DNS Intelligence" &&
                            tool === "Subdomain Discovery"
                          ? "/tools/dns/subdomain-discovery"
                          : category.title === "DNS Intelligence" &&
                            tool === "DNS Health"
                          ? "/tools/dns/dns-health"
                          : category.title === "SSL / TLS" &&
                            tool === "Certificate Check"
                          ? "/tools/ssl/certificate-check"
                          : category.title === "SSL / TLS" &&
                            tool === "TLS Analysis"
                          ? "/tools/ssl/tls-analysis"
                          : category.title === "SSL / TLS" &&
                            tool === "Cipher Review"
                          ? "/tools/ssl/cipher-review"
                          : category.title === "SSL / TLS" &&
                            tool === "Certificate Chain"
                          ? "/tools/ssl/certificate-chain"
                                                    : category.title === "DNS Intelligence" &&
                            tool === "Record Inspector"
                          ? "/tools/dns/record-inspector"
                          : category.title === "DNS Intelligence" &&
                            tool === "Subdomain Discovery"
                          ? "/tools/dns/subdomain-discovery"
                          : category.title === "DNS Intelligence" &&
                            tool === "DNS Health"
                          ? "/tools/dns/dns-health"
                          : category.title === "SSL / TLS" &&
                            tool === "Certificate Check"
                          ? "/tools/ssl/certificate-check"
                          : category.title === "SSL / TLS" &&
                            tool === "TLS Analysis"
                          ? "/tools/ssl/tls-analysis"
                          : category.title === "SSL / TLS" &&
                            tool === "Cipher Review"
                          ? "/tools/ssl/cipher-review"
                          : category.title === "SSL / TLS" &&
                            tool === "Certificate Chain"
                          ? "/tools/ssl/certificate-chain"
                                                    : category.title === "SQL Lab" &&
                            tool === "Query Analyzer"
                          ? "/tools/sql/query-analyzer"
                          : category.title === "SQL Lab" &&
                            tool === "Schema Inspector"
                          ? "/tools/sql/schema-inspector"
                          : category.title === "SQL Lab" &&
                            tool === "SQL Formatter"
                          ? "/tools/sql/sql-formatter"
                          : category.title === "SQL Lab" &&
                            tool === "Data Profiler"
                          ? "/tools/sql/data-profiler"
                          : category.title === "SQL Lab" &&
                            tool === "SQL Security Analyzer"
                          ? "/tools/sql/security-analyzer"
                          : category.title === "SQL Lab" &&
                            tool === "Parameterization Coach"
                          ? "/tools/sql/parameterization-coach"
                          : category.title === "SQL Lab" &&
                            tool === "Query Risk Report"
                          ? "/tools/sql/query-risk-report"
                          : category.title === "Data Lab" &&
                            tool === "CSV Analyzer"
                          ? "/tools/data/csv-analyzer"
                          : category.title === "Data Lab" &&
                            tool === "JSON Inspector"
                          ? "/tools/data/json-inspector"
                          : category.title === "Data Lab" &&
                            tool === "Data Cleaner"
                          ? "/tools/data/data-cleaner"
                          : category.title === "Data Lab" &&
                            tool === "Pattern & IOC Analysis"
                          ? "/tools/data/pattern-analysis"
                          : category.title === "Data Lab" &&
                            tool === "Security Log Analyzer"
                          ? "/tools/data/log-analyzer"
                          : category.title === "Data Lab" &&
                            tool === "HTTP Inspector"
                          ? "/tools/data/http-inspector"
                          : category.title === "Data Lab" &&
                            tool === "Encoding Studio"
                          ? "/tools/data/encoding-studio"
                          : category.title === "Data Lab" &&
                            tool === "Hash Inspector"
                          ? "/tools/data/hash-inspector"
                          : category.title === "Data Lab" &&
                            tool === "JWT Inspector"
                          ? "/tools/data/jwt-inspector"
                          : category.title === "Data Lab" &&
                            tool === "Regex Lab"
                          ? "/tools/data/regex-lab"
                          : category.title === "Data Lab" &&
                            tool === "Structured Data Diff"
                          ? "/tools/data/data-diff"
                          : category.title === "Data Lab" &&
                            tool === "Sensitive Data Redactor"
                          ? "/tools/data/sensitive-data-redactor"
                                                    : category.title === "Reconnaissance" &&
                            tool === "Asset Discovery"
                          ? "/tools/recon/asset-discovery"
                          : category.title === "Reconnaissance" &&
                            tool === "Technology Detection"
                          ? "/tools/recon/technology-detection"
                          : category.title === "Reconnaissance" &&
                            tool === "Metadata Inspector"
                          ? "/tools/recon/metadata-inspector"
                          : category.title === "Reconnaissance" &&
                            tool === "WHOIS"
                          ? "/tools/recon/whois"
                                                    : category.title === "Network Analysis" &&
                            tool === "Port Analysis"
                          ? "/tools/network/port-analysis"
                          : category.title === "Network Analysis" &&
                            tool === "Service Discovery"
                          ? "/tools/network/service-discovery"
                          : category.title === "Network Analysis" &&
                            tool === "Network Inspector"
                          ? "/tools/network/network-inspector"
                          : category.title === "Network Analysis" &&
                            tool === "Exposure Review"
                          ? "/tools/network/exposure-review"
                          : "/scanner"
                      }
                      className="flex items-center justify-between rounded-xl bg-[#09090b] px-3 py-2.5 text-xs font-semibold text-white/65 shadow-[inset_2px_2px_5px_#020203,inset_-2px_-2px_5px_#171719] transition hover:text-white"
                    >
                      <span>{tool}</span>
                      <span>→</span>
                    </Link>
                  ))}
                </div>

                <Link
                  href="/scanner"
                  className="mt-6 block text-xs font-bold uppercase tracking-widest text-red-300/80"
                >
                  Open category →
                </Link>
              </section>
            ))}
          </div>

          <section className="mt-10 rounded-[2rem] bg-[#17212b] p-7 text-white shadow-[10px_10px_22px_#000000,-8px_-8px_18px_#171719] sm:p-9">
            <div className="flex flex-col justify-between gap-7 lg:flex-row lg:items-center">
              <div>
                <p className="text-xs font-bold uppercase tracking-[0.22em] text-[#aeb9c2]">
                  Authorized testing only
                </p>
                <h2 className="mt-2 text-2xl font-black">
                  Build assessments around your scope.
                </h2>
                <p className="mt-3 max-w-2xl text-sm leading-6 text-[#b8c1c9]">
                  Future scanner workers will enforce target scope, rate
                  limits, job isolation, audit logging and abuse controls
                  before active assessment tasks execute.
                </p>
              </div>

              <Link
                href="/scanner"
                className="shrink-0 rounded-2xl bg-[#09090b] px-6 py-4 text-center text-sm font-bold text-white transition hover:-translate-y-0.5"
              >
                Open Security Scanner
              </Link>
            </div>
          </section>
        </section>

        <footer className="border-t border-[#d2d8dd] py-10">
          <div className="flex flex-col justify-between gap-4 text-sm text-white/35 sm:flex-row">
            <span className="font-bold text-[#273541]">CrypticX Lab</span>
            <div className="flex gap-5">
              <Link href="/docs" className="hover:text-white">Docs</Link>
              <Link href="/labs" className="hover:text-white">Labs</Link>
              <Link href="/pricing" className="hover:text-white">Pricing</Link>
            </div>
          </div>
        </footer>
      </div>
    </main>
  );
}
