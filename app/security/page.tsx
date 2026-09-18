"use client";

import Link from "next/link";

const categories = [
  {
    name: "Web Security",
    score: 91,
    status: "Strong",
    detail: "Headers, cookies, CORS, and HTTP configuration",
  },
  {
    name: "API Security",
    score: 86,
    status: "Good",
    detail: "Endpoints, methods, authentication, and configuration",
  },
  {
    name: "Network Exposure",
    score: 78,
    status: "Review",
    detail: "Services, exposed ports, and external attack surface",
  },
  {
    name: "DNS Security",
    score: 94,
    status: "Strong",
    detail: "Records, configuration, and domain intelligence",
  },
  {
    name: "SSL / TLS",
    score: 88,
    status: "Good",
    detail: "Certificates, protocols, ciphers, and trust chain",
  },
  {
    name: "Security Hygiene",
    score: 83,
    status: "Good",
    detail: "Configuration quality and remediation progress",
  },
];

const risks = [
  ["Critical", 0],
  ["High", 2],
  ["Medium", 7],
  ["Low", 3],
];

export default function SecurityPage() {
  return (
    <main className="min-h-screen bg-[var(--cx-bg)] text-[var(--cx-text)]">

      <section className="mx-auto max-w-7xl px-5 pb-20 pt-28 sm:px-8">
        <div className="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
          <div>
            <div className="mb-3 inline-flex rounded-full px-4 py-2 text-xs font-semibold tracking-wide cx-inset-sm">
              SECURITY POSTURE
            </div>

            <h1 className="text-4xl font-bold tracking-tight sm:text-5xl">
              Security Overview
            </h1>

            <p className="mt-4 max-w-2xl text-base leading-7 text-[var(--cx-muted)]">
              Understand your security posture across web, API, network,
              DNS, TLS, and configuration domains.
            </p>
          </div>

          <Link
            href="/scanner"
            className="cx-button cx-button-primary rounded-2xl px-5 py-3 text-sm font-semibold"
          >
            Run Assessment
          </Link>
        </div>

        <section className="mt-10 grid gap-6 lg:grid-cols-[1fr_1.4fr]">
          <div className="cx-card rounded-[30px] p-7 sm:p-8">
            <div className="flex flex-col items-center text-center">
              <div className="cx-raised flex h-48 w-48 items-center justify-center rounded-full">
                <div className="cx-inset flex h-36 w-36 flex-col items-center justify-center rounded-full">
                  <span className="text-5xl font-bold">86</span>
                  <span className="mt-1 text-xs font-semibold uppercase tracking-wide text-[var(--cx-muted)]">
                    Security Score
                  </span>
                </div>
              </div>

              <h2 className="mt-6 text-2xl font-bold">
                Good security posture
              </h2>

              <p className="mt-2 max-w-md text-sm leading-6 text-[var(--cx-muted)]">
                Your current assessment results indicate a generally strong
                posture, with several areas that should be reviewed.
              </p>
            </div>
          </div>

          <div className="cx-card rounded-[30px] p-7 sm:p-8">
            <div className="flex items-center justify-between">
              <div>
                <h2 className="text-xl font-bold">
                  Risk Distribution
                </h2>

                <p className="mt-1 text-sm text-[var(--cx-muted)]">
                  Open findings across your workspace.
                </p>
              </div>

              <Link
                href="/findings"
                className="text-sm font-semibold underline underline-offset-4"
              >
                View findings
              </Link>
            </div>

            <div className="mt-7 space-y-5">
              {risks.map(([label, count]) => (
                <div key={label}>
                  <div className="flex items-center justify-between text-sm">
                    <span className="font-semibold">{label}</span>
                    <span className="text-[var(--cx-muted)]">
                      {count}
                    </span>
                  </div>

                  <div className="cx-inset-sm mt-2 h-3 overflow-hidden rounded-full">
                    <div
                      className="h-full rounded-full bg-[var(--cx-text)]"
                      style={{
                        width: `${Math.max(
                          Number(count) * 10,
                          Number(count) === 0 ? 2 : 0
                        )}%`,
                      }}
                    />
                  </div>
                </div>
              ))}
            </div>

            <div className="mt-8 grid gap-4 sm:grid-cols-3">
              <div className="cx-inset-sm rounded-2xl p-4">
                <div className="text-2xl font-bold">27</div>
                <div className="mt-1 text-xs text-[var(--cx-muted)]">
                  Assessments
                </div>
              </div>

              <div className="cx-inset-sm rounded-2xl p-4">
                <div className="text-2xl font-bold">12</div>
                <div className="mt-1 text-xs text-[var(--cx-muted)]">
                  Open Findings
                </div>
              </div>

              <div className="cx-inset-sm rounded-2xl p-4">
                <div className="text-2xl font-bold">74%</div>
                <div className="mt-1 text-xs text-[var(--cx-muted)]">
                  Remediated
                </div>
              </div>
            </div>
          </div>
        </section>

        <section className="mt-8 cx-card rounded-[30px] p-6 sm:p-8">
          <div>
            <h2 className="text-xl font-bold">
              Security Domains
            </h2>

            <p className="mt-1 text-sm text-[var(--cx-muted)]">
              Category-level posture based on verified assessment results.
            </p>
          </div>

          <div className="mt-6 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            {categories.map((category) => (
              <article
                key={category.name}
                className="cx-inset-sm rounded-2xl p-5"
              >
                <div className="flex items-start justify-between gap-4">
                  <div>
                    <h3 className="font-semibold">
                      {category.name}
                    </h3>

                    <p className="mt-2 text-xs leading-5 text-[var(--cx-muted)]">
                      {category.detail}
                    </p>
                  </div>

                  <div className="text-right">
                    <div className="text-2xl font-bold">
                      {category.score}
                    </div>

                    <div className="text-[10px] font-semibold uppercase tracking-wide text-[var(--cx-muted)]">
                      {category.status}
                    </div>
                  </div>
                </div>

                <div className="cx-inset-sm mt-5 h-2 overflow-hidden rounded-full">
                  <div
                    className="h-full rounded-full bg-[var(--cx-text)]"
                    style={{ width: `${category.score}%` }}
                  />
                </div>
              </article>
            ))}
          </div>
        </section>

        <section className="mt-8 grid gap-6 lg:grid-cols-2">
          <div className="cx-card rounded-[30px] p-6 sm:p-8">
            <h2 className="text-xl font-bold">
              Priority Improvements
            </h2>

            <div className="mt-6 space-y-3">
              {[
                "Review exposed network services",
                "Remediate high-severity findings",
                "Strengthen API authentication controls",
                "Verify TLS configuration after changes",
              ].map((item, index) => (
                <div
                  key={item}
                  className="cx-inset-sm flex gap-4 rounded-2xl p-4"
                >
                  <span className="cx-raised-sm flex h-8 w-8 shrink-0 items-center justify-center rounded-xl text-xs font-bold">
                    {index + 1}
                  </span>

                  <span className="text-sm font-semibold">
                    {item}
                  </span>
                </div>
              ))}
            </div>
          </div>

          <div className="cx-card rounded-[30px] p-6 sm:p-8">
            <h2 className="text-xl font-bold">
              Security Lifecycle
            </h2>

            <div className="mt-6 grid gap-3 sm:grid-cols-2">
              {[
                ["01", "Discover", "Map authorized assets"],
                ["02", "Analyze", "Identify security risks"],
                ["03", "Fix", "Apply remediation"],
                ["04", "Verify", "Validate improvements"],
                ["05", "Report", "Document evidence"],
                ["06", "Monitor", "Track security posture"],
              ].map(([number, title, detail]) => (
                <div
                  key={number}
                  className="cx-inset-sm rounded-2xl p-4"
                >
                  <div className="text-xs font-bold text-[var(--cx-muted)]">
                    {number}
                  </div>

                  <div className="mt-2 font-semibold">
                    {title}
                  </div>

                  <div className="mt-1 text-xs text-[var(--cx-muted)]">
                    {detail}
                  </div>
                </div>
              ))}
            </div>
          </div>
        </section>

        <div className="mt-8 cx-card rounded-[30px] p-6">
          <div className="flex gap-4">
            <div className="cx-inset-sm flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl">
              ✓
            </div>

            <div>
              <h2 className="font-bold">
                Evidence-based security scoring
              </h2>

              <p className="mt-1 text-sm leading-6 text-[var(--cx-muted)]">
                Production scores will be calculated from verified findings,
                severity, affected assets, remediation state, evidence
                quality, and verification results. Security assessments must
                remain within explicitly authorized scope.
              </p>
            </div>
          </div>
        </div>
      </section>
    </main>
  );
}
