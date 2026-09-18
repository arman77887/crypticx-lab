import Link from "next/link";

const principles = [
  {
    number: "01",
    title: "Security first",
    description:
      "Every product decision starts with security, privacy, isolation, and responsible use.",
  },
  {
    number: "02",
    title: "Open by design",
    description:
      "CrypticX Lab is built around an open-source foundation that researchers and developers can inspect and extend.",
  },
  {
    number: "03",
    title: "Evidence driven",
    description:
      "Security findings should be understandable, reproducible, actionable, and supported by evidence.",
  },
  {
    number: "04",
    title: "Built to learn",
    description:
      "The platform connects practical security research with education, experimentation, and defensive engineering.",
  },
];

const lifecycle = [
  ["Discover", "Map authorized assets and understand the environment."],
  ["Analyze", "Identify configuration issues, exposures, and security weaknesses."],
  ["Fix", "Turn findings into practical defensive improvements."],
  ["Verify", "Re-test changes and confirm the security posture improved."],
  ["Report", "Create clear technical and executive-level security reports."],
  ["Monitor", "Track security posture and changes over time."],
];

export default function AboutPage() {
  return (
    <main className="min-h-screen bg-[var(--cx-bg)] text-[var(--cx-text)]">

      <section className="mx-auto max-w-7xl px-5 pb-20 pt-16 sm:px-8 lg:px-10 lg:pt-24">
        <div className="grid gap-12 lg:grid-cols-[1.05fr_0.95fr] lg:items-center">
          <div>
            <div className="cx-raised-sm inline-flex items-center gap-2 rounded-full px-4 py-2 text-xs font-semibold uppercase tracking-[0.18em] text-[var(--cx-subtle)]">
              <span className="h-2 w-2 rounded-full bg-[var(--cx-text)]" />
              About CrypticX Lab
            </div>

            <h1 className="mt-7 text-4xl font-semibold tracking-tight sm:text-5xl lg:text-6xl">
              Security research,
              <span className="block text-[var(--cx-subtle)]">
                made more accessible.
              </span>
            </h1>

            <p className="mt-6 max-w-2xl text-base leading-8 text-[var(--cx-muted)] sm:text-lg">
              CrypticX Lab is an open-source security and data intelligence
              platform designed to bring assessment, analysis, learning,
              remediation, and reporting into one coherent environment.
            </p>

            <div className="mt-8 flex flex-col gap-3 sm:flex-row">
              <Link href="/tools" className="cx-button cx-button-primary">
                Explore Tools
              </Link>

              <Link href="/labs" className="cx-button cx-button-secondary">
                Learn in Security Labs
              </Link>
            </div>
          </div>

          <div className="cx-inset rounded-[32px] p-7 sm:p-9">
            <div className="cx-raised rounded-[26px] p-6 sm:p-7">
              <div className="flex items-center justify-between">
                <span className="text-xs font-semibold uppercase tracking-[0.18em] text-[var(--cx-subtle)]">
                  CrypticX Model
                </span>

                <span className="rounded-full bg-[var(--cx-surface)] px-3 py-1 text-[10px] font-bold tracking-[0.14em] text-[var(--cx-subtle)] shadow-sm">
                  OPEN
                </span>
              </div>

              <div className="mt-8">
                <div className="flex items-end justify-between">
                  <div>
                    <p className="text-3xl font-semibold">6 stages</p>
                    <p className="mt-1 text-sm text-[var(--cx-muted)]">
                      Complete security lifecycle
                    </p>
                  </div>

                  <span className="text-sm font-semibold text-[var(--cx-subtle)]">
                    CX
                  </span>
                </div>

                <div className="cx-divider my-7" />

                <div className="grid grid-cols-2 gap-3">
                  {lifecycle.map(([title], index) => (
                    <div
                      key={title}
                      className="cx-inset-sm rounded-xl px-4 py-4"
                    >
                      <span className="text-[10px] font-bold tracking-[0.16em] text-[var(--cx-subtle)]">
                        0{index + 1}
                      </span>
                      <p className="mt-2 text-sm font-semibold">{title}</p>
                    </div>
                  ))}
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <section className="border-y border-[var(--cx-border)] bg-[var(--cx-surface)]">
        <div className="mx-auto max-w-7xl px-5 py-20 sm:px-8 lg:px-10">
          <div className="max-w-3xl">
            <p className="text-xs font-semibold uppercase tracking-[0.2em] text-[var(--cx-subtle)]">
              Why CrypticX
            </p>

            <h2 className="mt-4 text-3xl font-semibold tracking-tight sm:text-4xl">
              More than a collection of security tools.
            </h2>

            <p className="mt-5 text-sm leading-7 text-[var(--cx-muted)]">
              Modern security work often requires many disconnected tools.
              CrypticX Lab aims to provide a consistent workflow where
              discovery, analysis, findings, remediation, verification, and
              reporting are connected instead of treated as separate tasks.
            </p>
          </div>

          <div className="mt-12 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
            {principles.map((principle) => (
              <article
                key={principle.number}
                className="cx-raised rounded-[24px] p-6"
              >
                <span className="text-xs font-bold tracking-[0.2em] text-[var(--cx-subtle)]">
                  {principle.number}
                </span>

                <h3 className="mt-5 text-lg font-semibold">
                  {principle.title}
                </h3>

                <p className="mt-3 text-sm leading-6 text-[var(--cx-muted)]">
                  {principle.description}
                </p>
              </article>
            ))}
          </div>
        </div>
      </section>

      <section className="mx-auto max-w-7xl px-5 py-20 sm:px-8 lg:px-10">
        <div className="grid gap-12 lg:grid-cols-[0.75fr_1.25fr] lg:items-start">
          <div>
            <p className="text-xs font-semibold uppercase tracking-[0.2em] text-[var(--cx-subtle)]">
              The lifecycle
            </p>

            <h2 className="mt-4 text-3xl font-semibold tracking-tight sm:text-4xl">
              One workflow from discovery to monitoring.
            </h2>

            <p className="mt-5 text-sm leading-7 text-[var(--cx-muted)]">
              The long-term goal is to make security assessment a continuous,
              understandable process rather than a one-time scan.
            </p>
          </div>

          <div className="grid gap-3">
            {lifecycle.map(([title, description], index) => (
              <div
                key={title}
                className="cx-raised flex gap-5 rounded-2xl p-5 sm:p-6"
              >
                <span className="cx-inset-sm flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-xs font-bold text-[var(--cx-subtle)]">
                  0{index + 1}
                </span>

                <div>
                  <h3 className="font-semibold">{title}</h3>
                  <p className="mt-1 text-sm leading-6 text-[var(--cx-muted)]">
                    {description}
                  </p>
                </div>
              </div>
            ))}
          </div>
        </div>
      </section>

      <section className="border-y border-[var(--cx-border)] bg-[var(--cx-surface)]">
        <div className="mx-auto max-w-5xl px-5 py-20 text-center sm:px-8 lg:px-10">
          <div className="cx-raised rounded-[30px] p-8 sm:p-10">
            <p className="text-xs font-semibold uppercase tracking-[0.2em] text-[var(--cx-subtle)]">
              Open-source security
            </p>

            <h2 className="mt-4 text-2xl font-semibold sm:text-3xl">
              Built for researchers, developers, students, and security teams.
            </h2>

            <p className="mx-auto mt-5 max-w-2xl text-sm leading-7 text-[var(--cx-muted)]">
              CrypticX Lab is intended to grow through transparent engineering,
              community feedback, responsible research, and practical security
              knowledge.
            </p>

            <div className="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
              <Link href="/docs" className="cx-button cx-button-primary">
                Read Documentation
              </Link>

              <Link href="/scanner" className="cx-button cx-button-secondary">
                Start Assessment
              </Link>
            </div>
          </div>
        </div>
      </section>

      <footer className="border-t border-[var(--cx-border)]">
        <div className="mx-auto flex max-w-7xl flex-col gap-3 px-5 py-8 text-sm text-[var(--cx-muted)] sm:px-8 lg:flex-row lg:items-center lg:justify-between lg:px-10">
          <p>© 2026 CrypticX Lab. Open security infrastructure.</p>
          <p>Security research with purpose.</p>
        </div>
      </footer>
    </main>
  );
}
