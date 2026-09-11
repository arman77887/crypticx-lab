import Link from "next/link";
import Navbar from "@/components/Navbar";

const sections = [
  {
    number: "01",
    title: "Getting Started",
    description:
      "Understand CrypticX Lab, create an assessment, define scope, and review your first security result.",
    items: ["Platform Overview", "Quick Start", "Assessment Basics", "Project Setup"],
  },
  {
    number: "02",
    title: "Security Assessment",
    description:
      "Learn how the Discover → Analyze → Fix → Verify workflow connects security testing with remediation.",
    items: ["Targets", "Scan Types", "Scope Control", "Findings"],
  },
  {
    number: "03",
    title: "Security Tools",
    description:
      "Explore the tools available across web, network, DNS, TLS, API, SQL, data, and reconnaissance workflows.",
    items: ["Web Security", "Network", "DNS", "SSL / TLS"],
  },
  {
    number: "04",
    title: "Reports & Findings",
    description:
      "Turn technical assessment results into clear findings, remediation guidance, and verification reports.",
    items: ["Severity", "Evidence", "Remediation", "Reports"],
  },
];

const quickLinks = [
  {
    title: "Security Tools",
    description: "Browse the CrypticX security toolkit.",
    href: "/tools",
  },
  {
    title: "Security Labs",
    description: "Learn through controlled security exercises.",
    href: "/labs",
  },
  {
    title: "Start Assessment",
    description: "Open the assessment interface.",
    href: "/scanner",
  },
];

export default function DocsPage() {
  return (
    <main className="min-h-screen bg-[var(--cx-bg)] text-[var(--cx-text)]">
      <Navbar />

      <section className="mx-auto max-w-7xl px-5 pb-16 pt-16 sm:px-8 lg:px-10 lg:pt-24">
        <div className="max-w-3xl">
          <div className="cx-raised-sm inline-flex items-center gap-2 rounded-full px-4 py-2 text-xs font-semibold uppercase tracking-[0.18em] text-[var(--cx-subtle)]">
            <span className="h-2 w-2 rounded-full bg-[var(--cx-text)]" />
            Documentation
          </div>

          <h1 className="mt-7 text-4xl font-semibold tracking-tight sm:text-5xl lg:text-6xl">
            Everything you need to
            <span className="block text-[var(--cx-subtle)]">
              work with CrypticX Lab.
            </span>
          </h1>

          <p className="mt-6 max-w-2xl text-base leading-8 text-[var(--cx-muted)] sm:text-lg">
            Technical documentation for security researchers, developers,
            students, and teams using CrypticX for authorized security
            assessment and data intelligence workflows.
          </p>
        </div>

        <div className="mt-12 max-w-3xl">
          <div className="cx-inset rounded-2xl p-2">
            <div className="flex items-center gap-3 px-4 py-3">
              <svg
                viewBox="0 0 24 24"
                className="h-5 w-5 shrink-0 text-[var(--cx-subtle)]"
                fill="none"
                stroke="currentColor"
                strokeWidth="2"
              >
                <circle cx="11" cy="11" r="7" />
                <path d="m20 20-4-4" />
              </svg>

              <span className="text-sm text-[var(--cx-subtle)]">
                Search documentation...
              </span>

              <span className="ml-auto hidden rounded-lg bg-[var(--cx-surface)] px-2 py-1 text-[11px] text-[var(--cx-subtle)] shadow-sm sm:inline">
                /
              </span>
            </div>
          </div>
        </div>
      </section>

      <section className="mx-auto max-w-7xl px-5 pb-20 sm:px-8 lg:px-10">
        <div className="grid gap-6 lg:grid-cols-2">
          {sections.map((section) => (
            <article
              key={section.number}
              className="cx-card rounded-[28px] p-7 sm:p-8"
            >
              <div className="flex items-start justify-between gap-6">
                <span className="text-sm font-bold tracking-[0.2em] text-[var(--cx-subtle)]">
                  {section.number}
                </span>

                <span className="cx-inset-sm rounded-full px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.12em] text-[var(--cx-subtle)]">
                  Guide
                </span>
              </div>

              <h2 className="mt-6 text-2xl font-semibold">{section.title}</h2>

              <p className="mt-3 text-sm leading-7 text-[var(--cx-muted)]">
                {section.description}
              </p>

              <div className="mt-7 grid gap-2 sm:grid-cols-2">
                {section.items.map((item) => (
                  <div
                    key={item}
                    className="cx-inset-sm rounded-xl px-4 py-3 text-sm font-medium text-[var(--cx-subtle)]"
                  >
                    {item}
                  </div>
                ))}
              </div>
            </article>
          ))}
        </div>
      </section>

      <section className="border-y border-[var(--cx-border)] bg-[var(--cx-surface)]">
        <div className="mx-auto max-w-7xl px-5 py-20 sm:px-8 lg:px-10">
          <div className="grid gap-12 lg:grid-cols-[0.8fr_1.2fr]">
            <div>
              <p className="text-xs font-semibold uppercase tracking-[0.2em] text-[var(--cx-subtle)]">
                Core workflow
              </p>

              <h2 className="mt-4 text-3xl font-semibold tracking-tight sm:text-4xl">
                A security workflow built around evidence.
              </h2>

              <p className="mt-5 text-sm leading-7 text-[var(--cx-muted)]">
                CrypticX is designed to connect discovery, analysis,
                remediation, verification, and reporting into one consistent
                workflow.
              </p>
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
              {[
                ["Discover", "Identify authorized assets and understand the attack surface."],
                ["Analyze", "Inspect configuration, behavior, exposure, and security controls."],
                ["Fix", "Apply defensive changes based on actionable findings."],
                ["Verify", "Re-test and confirm that the identified risk has been addressed."],
              ].map(([title, description], index) => (
                <div
                  key={title}
                  className="cx-raised rounded-2xl p-6"
                >
                  <div className="flex items-center gap-3">
                    <span className="cx-inset-sm rounded-lg px-2.5 py-1 text-xs font-bold text-[var(--cx-subtle)]">
                      0{index + 1}
                    </span>
                    <h3 className="font-semibold">{title}</h3>
                  </div>

                  <p className="mt-4 text-sm leading-6 text-[var(--cx-muted)]">
                    {description}
                  </p>
                </div>
              ))}
            </div>
          </div>
        </div>
      </section>

      <section className="mx-auto max-w-7xl px-5 py-20 sm:px-8 lg:px-10">
        <div className="mb-8">
          <p className="text-xs font-semibold uppercase tracking-[0.2em] text-[var(--cx-subtle)]">
            Quick access
          </p>

          <h2 className="mt-3 text-2xl font-semibold sm:text-3xl">
            Continue exploring CrypticX.
          </h2>
        </div>

        <div className="grid gap-5 md:grid-cols-3">
          {quickLinks.map((link) => (
            <Link
              key={link.title}
              href={link.href}
              className="cx-raised group rounded-2xl p-6 transition-transform duration-200 hover:-translate-y-1"
            >
              <h3 className="font-semibold">{link.title}</h3>

              <p className="mt-2 text-sm leading-6 text-[var(--cx-muted)]">
                {link.description}
              </p>

              <span className="mt-6 inline-flex text-sm font-semibold text-[var(--cx-text)]">
                Explore
                <span className="ml-2 transition-transform group-hover:translate-x-1">
                  →
                </span>
              </span>
            </Link>
          ))}
        </div>
      </section>

      <section className="mx-auto max-w-7xl px-5 pb-20 sm:px-8 lg:px-10">
        <div className="cx-raised rounded-[30px] p-8 sm:p-10">
          <div className="grid gap-8 lg:grid-cols-[1fr_auto] lg:items-center">
            <div>
              <p className="text-xs font-semibold uppercase tracking-[0.2em] text-[var(--cx-subtle)]">
                Responsible research
              </p>

              <h2 className="mt-4 text-2xl font-semibold sm:text-3xl">
                Only test systems you own or are authorized to assess.
              </h2>

              <p className="mt-4 max-w-2xl text-sm leading-7 text-[var(--cx-muted)]">
                CrypticX is intended for legitimate security research,
                defensive testing, education, and authorized assessments.
                Future automated workers will enforce scope, rate limits,
                isolation, resource limits, and audit logging.
              </p>
            </div>

            <Link
              href="/scanner"
              className="cx-button cx-button-primary inline-flex"
            >
              Start Assessment
            </Link>
          </div>
        </div>
      </section>

      <footer className="border-t border-[var(--cx-border)]">
        <div className="mx-auto flex max-w-7xl flex-col gap-3 px-5 py-8 text-sm text-[var(--cx-muted)] sm:px-8 lg:flex-row lg:items-center lg:justify-between lg:px-10">
          <p>© 2026 CrypticX Lab. Open security infrastructure.</p>
          <p>Documentation for responsible security research.</p>
        </div>
      </footer>
    </main>
  );
}
