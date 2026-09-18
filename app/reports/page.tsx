"use client";

import Link from "next/link";
import { useEffect, useMemo, useState } from "react";
import Navbar from "@/components/Navbar";
import {
  downloadReportPdf,
  getReports,
  getStoredToken,
  type ReportRecord,
} from "@/lib/api";

function formatDate(value: string | null | undefined): string {
  if (!value) return "—";

  const date = new Date(value);

  if (Number.isNaN(date.getTime())) {
    return "—";
  }

  return new Intl.DateTimeFormat(undefined, {
    dateStyle: "medium",
    timeStyle: "short",
  }).format(date);
}

function riskLabelClass(level: string | null | undefined): string {
  switch ((level ?? "").toLowerCase()) {
    case "critical":
      return "border border-red-500/40 bg-red-500/10 text-red-300";
    case "high":
      return "border border-red-400/30 bg-red-500/[0.08] text-red-300";
    case "medium":
      return "border border-amber-500/30 bg-amber-500/10 text-amber-300";
    case "low":
      return "border border-blue-500/30 bg-blue-500/10 text-blue-300";
    default:
      return "border border-white/10 bg-white/[0.04] text-[var(--cx-muted)]";
  }
}

export default function ReportsPage() {
  const [downloadingReportId, setDownloadingReportId] = useState<string | null>(
    null,
  );
  const [, setDownloadError] = useState<string | null>(null);
  const [reports, setReports] = useState<ReportRecord[]>([]);
  const [total, setTotal] = useState(0);
  const [query, setQuery] = useState("");

  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [authenticated, setAuthenticated] = useState<boolean | null>(null);

  useEffect(() => {
    let cancelled = false;

    async function loadReports() {
      const token = getStoredToken();

      if (!token) {
        if (!cancelled) {
          setAuthenticated(false);
          setLoading(false);
        }

        return;
      }

      if (!cancelled) {
        setAuthenticated(true);
        setLoading(true);
        setError(null);
      }

      try {
        const response = await getReports();

        if (cancelled) return;

        setReports(response.data.data ?? []);
        setTotal(response.data.total ?? response.data.data?.length ?? 0);
      } catch (err) {
        if (cancelled) return;

        setError(
          err instanceof Error
            ? err.message
            : "Unable to load reports.",
        );
      } finally {
        if (!cancelled) {
          setLoading(false);
        }
      }
    }

    void loadReports();

    return () => {
      cancelled = true;
    };
  }, []);

  const filteredReports = useMemo(() => {
    const search = query.trim().toLowerCase();

    if (!search) {
      return reports;
    }

    return reports.filter((report) => {
      const values = [
        report.title,
        report.target_snapshot?.name,
        report.target_snapshot?.hostname,
        report.target_snapshot?.url,
        report.assessment_snapshot?.profile,
        report.assessment_id,
      ];

      return values.some((value) =>
        String(value ?? "")
          .toLowerCase()
          .includes(search),
      );
    });
  }, [query, reports]);

  const readyCount = reports.filter(
    (report) => report.status.toLowerCase() === "ready",
  ).length;

  const immutableCount = reports.filter(
    (report) => report.metadata?.immutable_snapshot === true,
  ).length;

  const findingsCount = reports.reduce(
    (sum, report) =>
      sum + (report.findings_snapshot?.length ?? 0),
    0,
  );

  async function handleDownloadPdf(reportId: string) {
    setDownloadError(null);
    setDownloadingReportId(reportId);

    try {
      await downloadReportPdf(reportId);
    } catch (err) {
      setDownloadError(
        err instanceof Error
          ? err.message
          : "Unable to download report PDF.",
      );
    } finally {
      setDownloadingReportId(null);
    }
  }

  return (
    <main className="min-h-screen bg-[var(--cx-bg)] text-[var(--cx-text)]">
      <Navbar />

      <section className="mx-auto max-w-7xl px-5 pb-20 pt-28 sm:px-8">
        <div className="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
          <div>
            <div className="mb-3 inline-flex rounded-full px-4 py-2 text-xs font-semibold tracking-wide cx-inset-sm">
              SECURITY REPORTING
            </div>

            <h1 className="text-4xl font-bold tracking-tight sm:text-5xl">
              Reports
            </h1>

            <p className="mt-4 max-w-2xl text-base leading-7 text-[var(--cx-muted)]">
              Immutable security assessment snapshots generated from completed
              authorized assessments. Risk values are prioritization scores,
              not probabilities of compromise.
            </p>
          </div>

          <Link
            href="/dashboard"
            className="cx-button cx-button-secondary rounded-2xl px-5 py-3 text-sm font-semibold"
          >
            Assessment Dashboard
          </Link>
        </div>

        {authenticated === false && (
          <div className="mt-10 cx-card rounded-[30px] p-7">
            <div className="cx-inset-sm rounded-2xl p-6">
              <h2 className="text-xl font-bold">
                Sign in to view reports
              </h2>

              <p className="mt-2 max-w-2xl text-sm leading-6 text-[var(--cx-muted)]">
                Reports contain private assessment snapshots and are available
                only to the authenticated owner.
              </p>

              <Link
                href="/login"
                className="mt-5 inline-flex cx-button cx-button-primary rounded-xl px-5 py-3 text-sm font-semibold"
              >
                Sign In
              </Link>
            </div>
          </div>
        )}

        {authenticated !== false && (
          <>
            <div className="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
              {[
                [String(total), "Total Reports"],
                [String(readyCount), "Ready"],
                [String(immutableCount), "Immutable Snapshots"],
                [String(findingsCount), "Findings in Loaded Reports"],
              ].map(([value, label]) => (
                <div
                  key={label}
                  className="cx-card rounded-3xl p-6"
                >
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
                  placeholder="Search title, target, hostname, profile, or assessment ID..."
                  className="cx-input w-full rounded-2xl px-4 py-3 text-sm outline-none lg:max-w-2xl"
                />

                <div className="cx-inset-sm rounded-xl px-4 py-3 text-xs font-semibold text-[var(--cx-muted)]">
                  Persisted snapshots only
                </div>
              </div>
            </div>

            <section className="mt-6 cx-card rounded-[30px] p-5 sm:p-7">
              <div className="mb-5 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                  <h2 className="text-xl font-bold">
                    Assessment Reports
                  </h2>

                  <p className="mt-1 text-sm text-[var(--cx-muted)]">
                    Reports generated from completed authorized assessments.
                  </p>
                </div>

                {!loading && !error && (
                  <div className="text-xs text-[var(--cx-muted)]">
                    Showing {filteredReports.length} loaded report
                    {filteredReports.length === 1 ? "" : "s"}
                  </div>
                )}
              </div>

              {loading && (
                <div className="cx-inset-sm rounded-2xl p-8 text-center">
                  <div className="mx-auto h-8 w-8 animate-spin rounded-full border-2 border-red-500/20 border-t-red-500" />

                  <h3 className="mt-4 font-semibold">
                    Loading reports
                  </h3>

                  <p className="mt-2 text-sm text-[var(--cx-muted)]">
                    Retrieving your persisted report registry.
                  </p>
                </div>
              )}

              {!loading && error && (
                <div className="rounded-2xl border border-red-500/30 bg-red-500/[0.06] p-6">
                  <h3 className="font-semibold text-red-300">
                    Unable to load reports
                  </h3>

                  <p className="mt-2 text-sm text-[var(--cx-muted)]">
                    {error}
                  </p>
                </div>
              )}

              {!loading && !error && filteredReports.length > 0 && (
                <div className="space-y-3">
                  {filteredReports.map((report) => {
                    const riskScore =
                      report.risk_snapshot?.score ?? 0;

                    const riskLevel =
                      report.risk_snapshot?.level ??
                      "informational";

                    const findingCount =
                      report.findings_snapshot?.length ??
                      report.assessment_snapshot?.finding_count ??
                      0;

                    const target =
                      report.target_snapshot?.name ||
                      report.target_snapshot?.hostname ||
                      report.target_snapshot?.url ||
                      "Unknown target";

                    return (
                      <article
                        key={report.id}
                        className="cx-inset-sm rounded-2xl p-4 sm:p-5"
                      >
                        <div className="flex flex-col gap-5 xl:flex-row xl:items-center xl:justify-between">
                          <div className="min-w-0">
                            <div className="flex flex-wrap items-center gap-3">
                              <h3 className="font-semibold">
                                {report.title}
                              </h3>

                              <span className="rounded-full border border-red-500/20 bg-red-500/[0.06] px-3 py-1 text-xs font-semibold text-red-300">
                                {report.status.toUpperCase()}
                              </span>

                              {report.metadata?.immutable_snapshot === true && (
                                <span className="rounded-full border border-white/10 bg-white/[0.04] px-3 py-1 text-xs font-semibold text-[var(--cx-muted)]">
                                  IMMUTABLE
                                </span>
                              )}

                              <span
                                className={`rounded-full px-3 py-1 text-xs font-semibold ${riskLabelClass(
                                  riskLevel,
                                )}`}
                              >
                                {riskScore}/100 · {riskLevel.toUpperCase()}
                              </span>
                            </div>

                            <div className="mt-3 flex flex-wrap gap-x-4 gap-y-2 text-xs text-[var(--cx-muted)]">
                              <span>Target: {target}</span>

                              <span>
                                Profile:{" "}
                                {report.assessment_snapshot?.profile ?? "—"}
                              </span>

                              <span>
                                Findings: {findingCount}
                              </span>

                              <span>
                                Generated: {formatDate(report.generated_at)}
                              </span>
                            </div>

                            <div className="mt-2 break-all text-xs text-[var(--cx-subtle)]">
                              Assessment: {report.assessment_id}
                            </div>
                          </div>

                          <div className="flex shrink-0 flex-wrap gap-2">
                            <Link
                              href={`/reports/${report.id}`}
                              className="cx-button cx-button-primary rounded-xl px-5 py-2.5 text-center text-sm font-semibold"
                            >
                              View Report
                            </Link>
                            <button
                              type="button"
                              onClick={() => void handleDownloadPdf(report.id)}
                              disabled={downloadingReportId === report.id}
                              className="cx-button cx-button-secondary rounded-xl px-5 py-2.5 text-center text-sm font-semibold disabled:cursor-not-allowed disabled:opacity-60"
                            >
                              {downloadingReportId === report.id
                                ? "Downloading..."
                                : "Download PDF"}
                            </button>
                          </div>
                        </div>
                      </article>
                    );
                  })}
                </div>
              )}

              {!loading && !error && filteredReports.length === 0 && (
                <div className="cx-inset-sm rounded-2xl p-8 text-center">
                  <h3 className="font-semibold">
                    {reports.length === 0
                      ? "No reports generated yet"
                      : "No matching reports"}
                  </h3>

                  <p className="mx-auto mt-2 max-w-xl text-sm leading-6 text-[var(--cx-muted)]">
                    {reports.length === 0
                      ? "Generate a report from a completed assessment to create an immutable security assessment snapshot."
                      : "Try another report title, target, hostname, profile, or assessment ID."}
                  </p>

                  {reports.length === 0 && (
                    <Link
                      href="/dashboard"
                      className="mt-5 inline-flex cx-button cx-button-secondary rounded-xl px-5 py-3 text-sm font-semibold"
                    >
                      View Assessments
                    </Link>
                  )}
                </div>
              )}
            </section>

            <section className="mt-8 grid gap-6 lg:grid-cols-2">
              <div className="cx-card rounded-[30px] p-6 sm:p-7">
                <h2 className="text-xl font-bold">
                  Snapshot Integrity
                </h2>

                <p className="mt-3 text-sm leading-6 text-[var(--cx-muted)]">
                  A generated report freezes its target, assessment, findings,
                  risk, intelligence, and provenance data at generation time.
                  Later lifecycle changes do not rewrite the stored report.
                </p>

                <div className="mt-6 grid gap-3 sm:grid-cols-2">
                  {[
                    "Target Snapshot",
                    "Assessment Snapshot",
                    "Finding Snapshots",
                    "Risk Snapshot",
                    "Assessment Intelligence",
                    "Generation Metadata",
                  ].map((item) => (
                    <div
                      key={item}
                      className="cx-inset-sm rounded-2xl p-4 text-sm font-semibold"
                    >
                      {item}
                    </div>
                  ))}
                </div>
              </div>

              <div className="cx-card rounded-[30px] p-6 sm:p-7">
                <h2 className="text-xl font-bold">
                  Risk Semantics
                </h2>

                <p className="mt-3 text-sm leading-6 text-[var(--cx-muted)]">
                  Report risk is stored as a 0–100 prioritization score derived
                  from lifecycle scoring at generation time. It must not be
                  interpreted as a percentage probability that a target is
                  vulnerable or compromised.
                </p>

                <div className="mt-6 cx-inset-sm rounded-2xl p-5">
                  <div className="text-xs font-semibold uppercase tracking-[0.18em] text-red-300">
                    Immutable Report V1
                  </div>

                  <div className="mt-2 text-sm text-[var(--cx-muted)]">
                    Canonical chain: Target → Assessment → Report
                  </div>
                </div>
              </div>
            </section>
          </>
        )}
      </section>
    </main>
  );
}
