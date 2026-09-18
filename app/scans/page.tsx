"use client";

import Link from "next/link";
import { useState } from "react";

type Scan = {
  target: string;
  type: string;
  score: number;
  status: "Completed" | "Running" | "Failed";
  findings: number;
  date: string;
};

const scans: Scan[] = [
  {
    target: "example.com",
    type: "Web Security",
    score: 86,
    status: "Completed",
    findings: 6,
    date: "Today, 10:42 AM",
  },
  {
    target: "api.example.dev",
    type: "API Security",
    score: 92,
    status: "Completed",
    findings: 3,
    date: "Yesterday, 4:18 PM",
  },
  {
    target: "staging.example.net",
    type: "SSL / TLS",
    score: 78,
    status: "Completed",
    findings: 3,
    date: "3 days ago",
  },
  {
    target: "example.com",
    type: "DNS Intelligence",
    score: 94,
    status: "Completed",
    findings: 1,
    date: "5 days ago",
  },
  {
    target: "api.example.dev",
    type: "Web Security",
    score: 89,
    status: "Running",
    findings: 2,
    date: "Just now",
  },
];

export default function ScansPage() {
  const [query, setQuery] = useState("");
  const [status, setStatus] = useState<
    "All" | "Completed" | "Running" | "Failed"
  >("All");

  const filteredScans = scans.filter((scan) => {
    const search = query.toLowerCase();

    const matchesQuery =
      scan.target.toLowerCase().includes(search) ||
      scan.type.toLowerCase().includes(search);

    const matchesStatus =
      status === "All" || scan.status === status;

    return matchesQuery && matchesStatus;
  });

  return (
    <main className="min-h-screen bg-[var(--cx-bg)] text-[var(--cx-text)]">

      <section className="mx-auto max-w-7xl px-5 pb-20 pt-28 sm:px-8">
        <div className="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
          <div>
            <div className="mb-3 inline-flex rounded-full px-4 py-2 text-xs font-semibold tracking-wide cx-inset-sm">
              ASSESSMENT HISTORY
            </div>

            <h1 className="text-4xl font-bold tracking-tight sm:text-5xl">
              Scan History
            </h1>

            <p className="mt-4 max-w-2xl text-base leading-7 text-[var(--cx-muted)]">
              Review previous authorized security assessments, results,
              findings, scores, and execution status.
            </p>
          </div>

          <Link
            href="/scanner"
            className="cx-button cx-button-primary rounded-2xl px-5 py-3 text-sm font-semibold"
          >
            + New Assessment
          </Link>
        </div>

        <div className="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
          {[
            ["27", "Total Assessments"],
            ["24", "Completed"],
            ["01", "Running"],
            ["02", "Failed"],
          ].map(([value, label]) => (
            <div key={label} className="cx-card rounded-3xl p-6">
              <div className="text-3xl font-bold">{value}</div>
              <div className="mt-2 text-sm text-[var(--cx-muted)]">
                {label}
              </div>
            </div>
          ))}
        </div>

        <div className="mt-8 cx-card rounded-[30px] p-5 sm:p-7">
          <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <input
              type="search"
              value={query}
              onChange={(event) => setQuery(event.target.value)}
              placeholder="Search target or assessment type..."
              className="cx-input w-full rounded-2xl px-4 py-3 text-sm outline-none lg:max-w-md"
            />

            <div className="flex flex-wrap gap-2">
              {(["All", "Completed", "Running", "Failed"] as const).map(
                (item) => (
                  <button
                    key={item}
                    type="button"
                    onClick={() => setStatus(item)}
                    className={
                      status === item
                        ? "cx-button cx-button-primary rounded-xl px-4 py-2 text-sm font-semibold"
                        : "cx-button cx-button-secondary rounded-xl px-4 py-2 text-sm font-semibold"
                    }
                  >
                    {item}
                  </button>
                )
              )}
            </div>
          </div>
        </div>

        <section className="mt-6 cx-card rounded-[30px] p-5 sm:p-7">
          <div className="mb-5">
            <h2 className="text-xl font-bold">Assessment History</h2>

            <p className="mt-1 text-sm text-[var(--cx-muted)]">
              Detailed history of security assessment jobs.
            </p>
          </div>

          <div className="overflow-x-auto">
            <table className="w-full min-w-[820px] text-left text-sm">
              <thead>
                <tr className="border-b border-[var(--cx-border)] text-xs uppercase tracking-wide text-[var(--cx-muted)]">
                  <th className="px-4 py-3 font-semibold">Target</th>
                  <th className="px-4 py-3 font-semibold">Assessment</th>
                  <th className="px-4 py-3 font-semibold">Score</th>
                  <th className="px-4 py-3 font-semibold">Findings</th>
                  <th className="px-4 py-3 font-semibold">Status</th>
                  <th className="px-4 py-3 font-semibold">Date</th>
                </tr>
              </thead>

              <tbody>
                {filteredScans.map((scan) => (
                  <tr
                    key={`${scan.target}-${scan.type}-${scan.date}`}
                    className="border-b border-[var(--cx-border)] last:border-0"
                  >
                    <td className="px-4 py-4 font-semibold">
                      {scan.target}
                    </td>

                    <td className="px-4 py-4 text-[var(--cx-muted)]">
                      {scan.type}
                    </td>

                    <td className="px-4 py-4">
                      <span className="font-bold">{scan.score}</span>
                    </td>

                    <td className="px-4 py-4 font-semibold">
                      {scan.findings}
                    </td>

                    <td className="px-4 py-4">
                      <span className="rounded-full px-3 py-1 text-xs font-semibold cx-inset-sm">
                        {scan.status}
                      </span>
                    </td>

                    <td className="px-4 py-4 text-[var(--cx-muted)]">
                      {scan.date}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          {filteredScans.length === 0 && (
            <div className="cx-inset-sm mt-5 rounded-2xl p-8 text-center">
              <h3 className="font-semibold">No assessments found</h3>

              <p className="mt-2 text-sm text-[var(--cx-muted)]">
                Try another search term or status filter.
              </p>
            </div>
          )}
        </section>

        <section className="mt-8 grid gap-6 lg:grid-cols-2">
          <div className="cx-card rounded-[30px] p-6 sm:p-7">
            <h2 className="text-xl font-bold">Assessment Workflow</h2>

            <div className="mt-6 space-y-3">
              {[
                ["01", "Discover", "Identify scoped assets and services."],
                ["02", "Analyze", "Evaluate security configuration and exposure."],
                ["03", "Fix", "Prioritize remediation based on findings."],
                ["04", "Verify", "Re-test fixes and validate improvements."],
              ].map(([number, title, description]) => (
                <div
                  key={number}
                  className="cx-inset-sm rounded-2xl p-4"
                >
                  <div className="flex gap-4">
                    <span className="text-xs font-bold text-[var(--cx-muted)]">
                      {number}
                    </span>

                    <div>
                      <div className="font-semibold">{title}</div>
                      <div className="mt-1 text-xs leading-5 text-[var(--cx-muted)]">
                        {description}
                      </div>
                    </div>
                  </div>
                </div>
              ))}
            </div>
          </div>

          <div className="cx-card rounded-[30px] p-6 sm:p-7">
            <h2 className="text-xl font-bold">Responsible Testing</h2>

            <p className="mt-3 text-sm leading-6 text-[var(--cx-muted)]">
              Scan history will become the audit trail for assessment jobs.
              Production execution will enforce target scope, authorization,
              rate limits, worker isolation, timeouts, and logging.
            </p>

            <div className="mt-6 cx-inset-sm rounded-2xl p-5">
              <div className="text-sm font-semibold">
                Current status
              </div>

              <div className="mt-2 text-sm text-[var(--cx-muted)]">
                Frontend interface only — backend scanner integration comes
                later.
              </div>
            </div>

            <Link
              href="/docs"
              className="cx-button cx-button-secondary mt-5 inline-flex rounded-xl px-4 py-2 text-sm font-semibold"
            >
              Read Documentation →
            </Link>
          </div>
        </section>
      </section>
    </main>
  );
}
