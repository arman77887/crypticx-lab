import Link from "next/link";
import Navbar from "@/components/Navbar";

const labs = [
  {
    level: "Beginner",
    title: "Web Security Fundamentals",
    description:
      "Learn the foundations of modern web security through practical, controlled security exercises.",
    topics: ["HTTP", "Headers", "Cookies", "CORS"],
  },
  {
    level: "Intermediate",
    title: "API Security Lab",
    description:
      "Understand API attack surfaces, authentication controls, request validation, and secure API design.",
    topics: ["REST API", "Auth", "Methods", "Validation"],
  },
  {
    level: "Intermediate",
    title: "Network Exposure Lab",
    description:
      "Explore network services, exposed ports, service identification, and defensive exposure analysis.",
    topics: ["Ports", "Services", "Exposure", "Hardening"],
  },
  {
    level: "Advanced",
    title: "DNS Intelligence Lab",
    description:
      "Study DNS records, domain infrastructure, subdomains, and defensive asset discovery.",
    topics: ["DNS", "Subdomains", "Records", "Assets"],
  },
  {
    level: "Advanced",
    title: "SSL / TLS Analysis",
    description:
      "Analyze certificates, TLS configuration, cipher suites, and common transport-security weaknesses.",
    topics: ["TLS", "Certificates", "Ciphers", "HTTPS"],
  },
  {
    level: "Advanced",
    title: "Security Assessment Lab",
    description:
      "Practice a complete authorized assessment workflow from reconnaissance to verification and reporting.",
    topics: ["Recon", "Assessment", "Findings", "Reports"],
  },
];

const learningPath = [
  {
    number: "01",
    title: "Understand",
    description: "Learn the security concept and understand the underlying technology.",
  },
  {
    number: "02",
    title: "Analyze",
    description: "Work with controlled lab environments and inspect security behavior.",
  },
  {
    number: "03",
    title: "Fix",
    description: "Apply defensive improvements and understand why they reduce risk.",
  },
  {
    number: "04",
    title: "Verify",
    description: "Re-test the environment and confirm that the security improvement works.",
  },
];

export default function LabsPage() {
  return (
    <main className="min-h-screen bg-[var(--cx-bg)] text-[var(--cx-text)]">
      <Navbar />

      <section className="mx-auto max-w-7xl px-5 pb-20 pt-16 sm:px-8 lg:px-10 lg:pt-24">
        <div className="max-w-3xl">
          <div className="cx-raised-sm inline-flex items-center gap-2 rounded-full px-4 py-2 text-xs font-semibold uppercase tracking-[0.18em] text-[var(--cx-subtle)]">
            <span className="h-2 w-2 rounded-full bg-[var(--cx-text)]" />
            Security Labs
          </div>

          <h1 className="mt-7 text-4xl font-semibold tracking-tight sm:text-5xl lg:text-6xl">
            Learn security by
            <span className="block text-[var(--cx-subtle)]">
              understanding how systems work.
            </span>
          </h1>

          <p className="mt-6 max-w-2xl text-base leading-8 text-[var(--cx-muted)] sm:text-lg">
            CrypticX Labs provides structured, controlled environments for
            learning security concepts, analyzing vulnerabilities, and
            practicing defensive techniques without targeting systems you do
            not own or have permission to test.
          </p>
        </div>

        <div className="mt-14 grid gap-6 lg:grid-cols-3">
          {labs.map((lab) => (
            <article
              key={lab.title}
              className="cx-card flex h-full flex-col rounded-[28px] p-6 sm:p-7"
            >
              <div className="flex items-center justify-between gap-4">
                <span className="cx-inset-sm rounded-full px-3 py-1.5 text-xs font-semibold text-[var(--cx-subtle)]">
                  {lab.level}
                </span>

                <span className="text-xs font-medium text-[var(--cx-subtle)]">
                  LAB
                </span>
              </div>

              <h2 className="mt-7 text-xl font-semibold">{lab.title}</h2>

              <p className="mt-3 flex-1 text-sm leading-7 text-[var(--cx-muted)]">
                {lab.description}
              </p>

              <div className="mt-6 flex flex-wrap gap-2">
                {lab.topics.map((topic) => (
                  <span
                    key={topic}
                    className="cx-inset-sm rounded-lg px-3 py-2 text-xs font-medium text-[var(--cx-subtle)]"
                  >
                    {topic}
                  </span>
                ))}
              </div>

              <Link
                href="/scanner"
                className="cx-button cx-button-secondary mt-7 w-full"
              >
                Open Lab
              </Link>
            </article>
          ))}
        </div>
      </section>

      <section className="border-y border-[var(--cx-border)] bg-[var(--cx-surface)]">
        <div className="mx-auto max-w-7xl px-5 py-20 sm:px-8 lg:px-10">
          <div className="grid gap-12 lg:grid-cols-[0.8fr_1.2fr] lg:items-center">
            <div>
              <p className="text-xs font-semibold uppercase tracking-[0.2em] text-[var(--cx-subtle)]">
                Learning Path
              </p>

              <h2 className="mt-4 text-3xl font-semibold tracking-tight sm:text-4xl">
                From first principles to verified security.
              </h2>

              <p className="mt-5 max-w-xl text-sm leading-7 text-[var(--cx-muted)]">
                Each lab will eventually connect to CrypticX assessment,
                findings, remediation, and reporting workflows.
              </p>
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
              {learningPath.map((step) => (
                <div
                  key={step.number}
                  className="cx-raised rounded-2xl p-6"
                >
                  <span className="text-xs font-bold tracking-[0.2em] text-[var(--cx-subtle)]">
                    {step.number}
                  </span>

                  <h3 className="mt-5 text-lg font-semibold">{step.title}</h3>

                  <p className="mt-3 text-sm leading-6 text-[var(--cx-muted)]">
                    {step.description}
                  </p>
                </div>
              ))}
            </div>
          </div>
        </div>
      </section>

      <section className="mx-auto max-w-7xl px-5 py-20 sm:px-8 lg:px-10">
        <div className="cx-raised rounded-[30px] p-8 sm:p-10 lg:flex lg:items-center lg:justify-between lg:gap-10">
          <div className="max-w-2xl">
            <p className="text-xs font-semibold uppercase tracking-[0.2em] text-[var(--cx-subtle)]">
              Practice responsibly
            </p>

            <h2 className="mt-4 text-2xl font-semibold sm:text-3xl">
              Security research starts with the right scope.
            </h2>

            <p className="mt-4 text-sm leading-7 text-[var(--cx-muted)]">
              CrypticX is designed around authorized testing, controlled
              environments, rate limits, isolation, audit trails, and
              responsible security research.
            </p>
          </div>

          <Link
            href="/tools"
            className="cx-button cx-button-primary mt-7 inline-flex lg:mt-0"
          >
            Explore Security Tools
          </Link>
        </div>
      </section>

      <footer className="border-t border-[var(--cx-border)]">
        <div className="mx-auto flex max-w-7xl flex-col gap-3 px-5 py-8 text-sm text-[var(--cx-muted)] sm:px-8 lg:flex-row lg:items-center lg:justify-between lg:px-10">
          <p>© 2026 CrypticX Lab. Open security infrastructure.</p>
          <p>Built for learning, analysis, and responsible research.</p>
        </div>
      </footer>
    </main>
  );
}
