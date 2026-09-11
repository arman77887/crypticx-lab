"use client";

import Link from "next/link";
import { useParams } from "next/navigation";
import { useEffect, useMemo, useState } from "react";
import {
  downloadAdminReportPdf,
  getAdminReport,
  getStoredToken,
  type AdminReportRecord,
  type ReportFindingSnapshot,
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

function displayValue(value: unknown): string {
  if (value === null || value === undefined || value === "") {
    return "—";
  }

  if (typeof value === "boolean") {
    return value ? "Yes" : "No";
  }

  if (typeof value === "object") {
    return JSON.stringify(value, null, 2);
  }

  return String(value);
}

function severityClass(severity: string): string {
  switch (severity.toLowerCase()) {
    case "critical":
      return "border-red-500/50 bg-red-500/15 text-red-200";
    case "high":
      return "border-red-400/40 bg-red-500/10 text-red-300";
    case "medium":
      return "border-amber-500/40 bg-amber-500/10 text-amber-300";
    case "low":
      return "border-blue-500/30 bg-blue-500/10 text-blue-300";
    default:
      return "border-white/10 bg-white/[0.04] text-[var(--cx-muted)]";
  }
}

function SnapshotJson({
  title,
  value,
}: {
  title: string;
  value: unknown;
}) {
  return (
    <div className="cx-inset-sm rounded-2xl p-5">
      <h3 className="text-sm font-semibold">{title}</h3>

      <pre className="mt-3 max-h-80 overflow-auto whitespace-pre-wrap break-words text-xs leading-6 text-[var(--cx-muted)]">
        {displayValue(value)}
      </pre>
    </div>
  );
}

function FindingCard({
  finding,
  index,
}: {
  finding: ReportFindingSnapshot;
  index: number;
}) {
  return (
    <article className="cx-inset-sm rounded-2xl p-5">
      <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div className="min-w-0">
          <div className="text-xs font-semibold uppercase tracking-[0.16em] text-[var(--cx-subtle)]">
            Finding {index + 1}
          </div>

          <h3 className="mt-2 text-lg font-bold">
            {finding.title}
          </h3>

          <div className="mt-3 flex flex-wrap gap-2">
            <span
              className={`rounded-full border px-3 py-1 text-xs font-semibold ${severityClass(
                finding.severity,
              )}`}
            >
              {finding.severity.toUpperCase()}
            </span>

            <span className="rounded-full border border-white/10 bg-white/[0.04] px-3 py-1 text-xs font-semibold text-[var(--cx-muted)]">
              Confidence: {finding.confidence}
            </span>

            <span className="rounded-full border border-white/10 bg-white/[0.04] px-3 py-1 text-xs font-semibold text-[var(--cx-muted)]">
              {finding.type}
            </span>
          </div>
        </div>

        <div className="break-all text-xs text-[var(--cx-subtle)]">
          {finding.fingerprint || "No fingerprint"}
        </div>
      </div>

      {finding.description && (
        <div className="mt-5">
          <h4 className="text-sm font-semibold">Description</h4>
          <p className="mt-2 whitespace-pre-wrap text-sm leading-6 text-[var(--cx-muted)]">
            {finding.description}
          </p>
        </div>
      )}

      {finding.evidence && (
        <div className="mt-5">
          <h4 className="text-sm font-semibold">Evidence</h4>
          <div className="mt-2 rounded-xl border border-red-500/10 bg-black/20 p-4">
            <pre className="overflow-auto whitespace-pre-wrap break-words text-xs leading-6 text-[var(--cx-muted)]">
              {finding.evidence}
            </pre>
          </div>
        </div>
      )}

      {finding.evidence_data &&
        Object.keys(finding.evidence_data).length > 0 && (
          <div className="mt-5">
            <SnapshotJson
              title="Structured Evidence"
              value={finding.evidence_data}
            />
          </div>
        )}

      {finding.remediation && (
        <div className="mt-5">
          <h4 className="text-sm font-semibold text-red-300">
            Remediation
          </h4>

          <p className="mt-2 whitespace-pre-wrap text-sm leading-6 text-[var(--cx-muted)]">
            {finding.remediation}
          </p>
        </div>
      )}
    </article>
  );
}

export default function AdminReportDetailPage() {
  const params = useParams<{ id: string }>();
  const reportId = params?.id;

  const [report, setReport] = useState<AdminReportRecord | null>(null);
  const [downloadingPdf, setDownloadingPdf] = useState(false);
  const [pdfError, setPdfError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const [authenticated, setAuthenticated] = useState<boolean | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let cancelled = false;

    async function load() {
      if (!getStoredToken()) {
        if (!cancelled) {
          setAuthenticated(false);
          setLoading(false);
        }

        return;
      }

      if (!reportId) {
        if (!cancelled) {
          setAuthenticated(true);
          setError("Invalid report identifier.");
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
        const response = await getAdminReport(reportId);

        if (!cancelled) {
          setReport(response.data);
        }
      } catch (err) {
        if (!cancelled) {
          setError(
            err instanceof Error
              ? err.message
              : "Unable to load report.",
          );
        }
      } finally {
        if (!cancelled) {
          setLoading(false);
        }
      }
    }

    void load();

    return () => {
      cancelled = true;
    };
  }, [reportId]);

  const severityCounts = useMemo(() => {
    const counts: Record<string, number> = {};

    for (const finding of report?.findings_snapshot ?? []) {
      const severity = finding.severity.toLowerCase();
      counts[severity] = (counts[severity] ?? 0) + 1;
    }

    return counts;
  }, [report]);

  const intelligence = report?.intelligence_snapshot ?? null;

  async function handleDownloadPdf() {
    if (!report || downloadingPdf) {
      return;
    }

    setDownloadingPdf(true);
    setPdfError(null);

    try {
      await downloadAdminReportPdf(report.id);
    } catch (downloadError) {
      setPdfError(
        downloadError instanceof Error
          ? downloadError.message
          : "Unable to download PDF.",
      );
    } finally {
      setDownloadingPdf(false);
    }
  }

  return (
    <main className="min-h-screen bg-[var(--cx-bg)] text-[var(--cx-text)]">

      <section className="mx-auto max-w-7xl px-5 pb-24 pt-28 sm:px-8">
        <Link
          href="/admin/reports"
          className="text-sm font-semibold text-red-300 transition hover:text-red-200"
        >
          ← Back to Admin Reports
        </Link>

        {authenticated === false && (
          <div className="mt-8 cx-card rounded-[30px] p-7">
            <h1 className="text-2xl font-bold">
              Authentication required
            </h1>

            <p className="mt-2 text-sm text-[var(--cx-muted)]">
              Sign in with an authorized administrator account to access this report.
            </p>

            <Link
              href="/login"
              className="mt-5 inline-flex cx-button cx-button-primary rounded-xl px-5 py-3 text-sm font-semibold"
            >
              Sign In
            </Link>
          </div>
        )}

        {authenticated !== false && loading && (
          <div className="mt-8 cx-card rounded-[30px] p-10 text-center">
            <div className="mx-auto h-9 w-9 animate-spin rounded-full border-2 border-red-500/20 border-t-red-500" />
            <div className="mt-4 font-semibold">Loading report</div>
          </div>
        )}

        {authenticated !== false && !loading && error && (
          <div className="mt-8 rounded-[30px] border border-red-500/30 bg-red-500/[0.06] p-7">
            <h1 className="text-xl font-bold text-red-300">
              Unable to load report
            </h1>

            <p className="mt-2 text-sm text-[var(--cx-muted)]">
              {error}
            </p>
          </div>
        )}

        {authenticated !== false && !loading && !error && report && (
          <>
            <section className="mt-8 cx-card rounded-[30px] p-6 sm:p-7">
              <div className="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
                <div>
                  <div className="text-xs font-semibold uppercase tracking-[0.16em] text-red-300">
                    Platform Report Owner
                  </div>

                  <h2 className="mt-2 text-xl font-bold text-white">
                    {report.owner?.name || "Unknown owner"}
                  </h2>

                  <p className="mt-1 break-all text-sm text-[var(--cx-muted)]">
                    {report.owner?.email || "No owner email available"}
                  </p>
                </div>

                <div className="cx-inset-sm rounded-2xl px-5 py-4">
                  <div className="text-xs uppercase tracking-[0.14em] text-[var(--cx-subtle)]">
                    Access Context
                  </div>

                  <div className="mt-2 text-sm font-semibold text-white">
                    Platform-wide administrator view
                  </div>

                  <div className="mt-1 break-all font-mono text-[11px] text-[var(--cx-subtle)]">
                    User {report.user_id}
                  </div>
                </div>
              </div>
            </section>

            <header className="mt-8 cx-card rounded-[32px] p-6 sm:p-8">
              <div className="flex flex-col gap-7 xl:flex-row xl:items-start xl:justify-between">
                <div className="min-w-0">
                  <div className="inline-flex rounded-full border border-red-500/20 bg-red-500/[0.06] px-4 py-2 text-xs font-semibold tracking-[0.15em] text-red-300">
                    IMMUTABLE SECURITY REPORT
                  </div>

                  <h1 className="mt-5 text-3xl font-bold tracking-tight sm:text-5xl">
                    {report.title}
                  </h1>

                  <div className="mt-5 flex flex-wrap gap-x-5 gap-y-2 text-sm text-[var(--cx-muted)]">
                    <span>
                      Target:{" "}
                      {report.target_snapshot.name ||
                        report.target_snapshot.hostname ||
                        report.target_snapshot.url ||
                        "—"}
                    </span>

                    <span>
                      Generated: {formatDate(report.generated_at)}
                    </span>

                    <span>
                      Schema: {report.metadata.schema_version}
                    </span>
                  </div>

                  <div className="mt-3 break-all text-xs text-[var(--cx-subtle)]">
                    Report ID: {report.id}
                  </div>

                  <div className="mt-5">
                    <button
                      type="button"
                      onClick={handleDownloadPdf}
                      disabled={downloadingPdf}
                      className="cx-button cx-button-primary inline-flex min-w-36 items-center justify-center rounded-xl px-5 py-3 text-sm font-semibold disabled:cursor-not-allowed disabled:opacity-60"
                    >
                      {downloadingPdf ? "Preparing PDF..." : "Export PDF"}
                    </button>

                    {pdfError && (
                      <p className="mt-3 text-sm text-red-300">
                        {pdfError}
                      </p>
                    )}
                  </div>
                </div>

                <div className="cx-inset-sm min-w-52 rounded-3xl p-6 text-center">
                  <div className="text-xs font-semibold uppercase tracking-[0.18em] text-[var(--cx-muted)]">
                    Snapshot Risk
                  </div>

                  <div className="mt-3 text-5xl font-bold">
                    {report.risk_snapshot.score}
                    <span className="text-xl text-[var(--cx-muted)]">
                      /100
                    </span>
                  </div>

                  <div className="mt-3 text-sm font-semibold uppercase text-red-300">
                    {report.risk_snapshot.level}
                  </div>

                  <div className="mt-3 text-xs leading-5 text-[var(--cx-subtle)]">
                    Prioritization score, not probability
                  </div>
                </div>
              </div>
            </header>

            <section className="mt-6 grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
              {[
                [
                  String(report.findings_snapshot.length),
                  "Findings",
                ],
                [
                  String(severityCounts.critical ?? 0),
                  "Critical",
                ],
                [
                  String(severityCounts.high ?? 0),
                  "High",
                ],
                [
                  report.metadata.immutable_snapshot
                    ? "Verified"
                    : "Unknown",
                  "Snapshot State",
                ],
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
            </section>

            <section className="mt-8 grid gap-6 lg:grid-cols-2">
              <div className="cx-card rounded-[30px] p-6 sm:p-7">
                <h2 className="text-xl font-bold">
                  Target Snapshot
                </h2>

                <div className="mt-5 space-y-3 text-sm">
                  {[
                    ["Name", report.target_snapshot.name],
                    ["Hostname", report.target_snapshot.hostname],
                    ["URL", report.target_snapshot.url],
                    ["Scheme", report.target_snapshot.scheme],
                    ["Port", report.target_snapshot.port],
                    ["Status", report.target_snapshot.status],
                    [
                      "Authorization",
                      report.target_snapshot.authorization.confirmed
                        ? "Confirmed"
                        : "Not confirmed",
                    ],
                    [
                      "Authorization Method",
                      report.target_snapshot.authorization.method,
                    ],
                  ].map(([label, value]) => (
                    <div
                      key={String(label)}
                      className="cx-inset-sm flex flex-col gap-1 rounded-xl p-4 sm:flex-row sm:justify-between"
                    >
                      <span className="text-[var(--cx-muted)]">
                        {label}
                      </span>
                      <span className="break-all font-semibold">
                        {displayValue(value)}
                      </span>
                    </div>
                  ))}
                </div>
              </div>

              <div className="cx-card rounded-[30px] p-6 sm:p-7">
                <h2 className="text-xl font-bold">
                  Assessment Snapshot
                </h2>

                <div className="mt-5 space-y-3 text-sm">
                  {[
                    ["Assessment ID", report.assessment_snapshot.id],
                    ["Profile", report.assessment_snapshot.profile],
                    ["Status", report.assessment_snapshot.status],
                    ["Progress", `${report.assessment_snapshot.progress}%`],
                    [
                      "Started",
                      formatDate(report.assessment_snapshot.started_at),
                    ],
                    [
                      "Completed",
                      formatDate(report.assessment_snapshot.completed_at),
                    ],
                    [
                      "Finding Count",
                      report.assessment_snapshot.finding_count,
                    ],
                  ].map(([label, value]) => (
                    <div
                      key={String(label)}
                      className="cx-inset-sm flex flex-col gap-1 rounded-xl p-4 sm:flex-row sm:justify-between"
                    >
                      <span className="text-[var(--cx-muted)]">
                        {label}
                      </span>
                      <span className="break-all font-semibold">
                        {displayValue(value)}
                      </span>
                    </div>
                  ))}
                </div>
              </div>
            </section>

            <section className="mt-8 cx-card rounded-[30px] p-6 sm:p-7">
              <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                  <h2 className="text-2xl font-bold">
                    Security Findings
                  </h2>

                  <p className="mt-2 text-sm text-[var(--cx-muted)]">
                    Evidence and remediation below are frozen copies from
                    report generation time.
                  </p>
                </div>

                <div className="text-sm font-semibold text-red-300">
                  {report.findings_snapshot.length} finding
                  {report.findings_snapshot.length === 1 ? "" : "s"}
                </div>
              </div>

              <div className="mt-6 space-y-4">
                {report.findings_snapshot.length > 0 ? (
                  report.findings_snapshot.map((finding, index) => (
                    <FindingCard
                      key={`${finding.id}-${finding.fingerprint ?? index}`}
                      finding={finding}
                      index={index}
                    />
                  ))
                ) : (
                  <div className="cx-inset-sm rounded-2xl p-8 text-center text-sm text-[var(--cx-muted)]">
                    No findings were captured in this report snapshot.
                  </div>
                )}
              </div>
            </section>

            <section className="mt-8 cx-card rounded-[30px] p-6 sm:p-7">
              <h2 className="text-2xl font-bold">
                Assessment Intelligence
              </h2>

              {!intelligence ? (
                <p className="mt-4 text-sm text-[var(--cx-muted)]">
                  No intelligence snapshot was stored.
                </p>
              ) : intelligence.available === false ? (
                <div className="mt-5 cx-inset-sm rounded-2xl p-5">
                  <div className="font-semibold">
                    Baseline assessment
                  </div>

                  <p className="mt-2 text-sm leading-6 text-[var(--cx-muted)]">
                    No previous completed assessment was available for a
                    comparison when this report was generated.
                  </p>

                  <div className="mt-3 text-xs text-[var(--cx-subtle)]">
                    Reason: {intelligence.reason}
                  </div>
                </div>
              ) : (
                <div className="mt-6">
                  <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {[
                      [
                        intelligence.comparison.summary.new,
                        "New",
                      ],
                      [
                        intelligence.comparison.summary.persistent,
                        "Persistent",
                      ],
                      [
                        intelligence.comparison.summary.no_longer_detected,
                        "No Longer Detected",
                      ],
                      [
                        `${
                          intelligence.comparison.risk.delta_points > 0
                            ? "+"
                            : ""
                        }${intelligence.comparison.risk.delta_points}`,
                        "Risk Point Delta",
                      ],
                    ].map(([value, label]) => (
                      <div
                        key={String(label)}
                        className="cx-inset-sm rounded-2xl p-5"
                      >
                        <div className="text-2xl font-bold">
                          {value}
                        </div>

                        <div className="mt-2 text-xs text-[var(--cx-muted)]">
                          {label}
                        </div>
                      </div>
                    ))}
                  </div>

                  <div className="mt-5 text-sm text-[var(--cx-muted)]">
                    Trend:{" "}
                    <span className="font-semibold text-[var(--cx-text)]">
                      {intelligence.comparison.risk.trend}
                    </span>
                    {" · "}
                    Absence means no longer detected; it does not prove
                    remediation or resolution.
                  </div>
                </div>
              )}
            </section>

            <section className="mt-8 grid gap-6 lg:grid-cols-2">
              <div className="cx-card rounded-[30px] p-6 sm:p-7">
                <h2 className="text-xl font-bold">
                  Risk Provenance
                </h2>

                <div className="mt-5 space-y-3 text-sm">
                  {[
                    ["Score", `${report.risk_snapshot.score}/100`],
                    ["Level", report.risk_snapshot.level],
                    ["Highest", report.risk_snapshot.highest],
                    ["Average", report.risk_snapshot.average],
                    ["Scored Items", report.risk_snapshot.item_count],
                    ["Source", report.risk_snapshot.scoring.source],
                    [
                      "Scored Lifecycles",
                      report.risk_snapshot.scoring.scored_lifecycle_count,
                    ],
                  ].map(([label, value]) => (
                    <div
                      key={String(label)}
                      className="cx-inset-sm flex flex-col gap-1 rounded-xl p-4 sm:flex-row sm:justify-between"
                    >
                      <span className="text-[var(--cx-muted)]">
                        {label}
                      </span>
                      <span className="break-all font-semibold">
                        {displayValue(value)}
                      </span>
                    </div>
                  ))}
                </div>
              </div>

              <div className="cx-card rounded-[30px] p-6 sm:p-7">
                <h2 className="text-xl font-bold">
                  Report Integrity
                </h2>

                <div className="mt-5 space-y-3 text-sm">
                  {[
                    ["Schema", report.metadata.schema_version],
                    ["Source", report.metadata.source],
                    ["Risk Source", report.metadata.risk_source],
                    [
                      "Immutable",
                      report.metadata.immutable_snapshot,
                    ],
                    [
                      "Risk Probability",
                      report.metadata.risk_probability,
                    ],
                    [
                      "Generated",
                      formatDate(report.metadata.generated_at),
                    ],
                  ].map(([label, value]) => (
                    <div
                      key={String(label)}
                      className="cx-inset-sm flex flex-col gap-1 rounded-xl p-4 sm:flex-row sm:justify-between"
                    >
                      <span className="text-[var(--cx-muted)]">
                        {label}
                      </span>
                      <span className="break-all font-semibold">
                        {displayValue(value)}
                      </span>
                    </div>
                  ))}
                </div>
              </div>
            </section>

            <section className="mt-8 cx-card rounded-[30px] p-6 sm:p-7">
              <h2 className="text-xl font-bold">
                Technical Snapshot
              </h2>

              <p className="mt-2 text-sm text-[var(--cx-muted)]">
                Captured scanner configuration and execution metadata.
              </p>

              <div className="mt-5 grid gap-4 lg:grid-cols-2">
                <SnapshotJson
                  title="Assessment Configuration"
                  value={report.assessment_snapshot.configuration}
                />

                <SnapshotJson
                  title="Execution Metadata"
                  value={report.assessment_snapshot.execution_metadata}
                />
              </div>
            </section>
          </>
        )}
      </section>
    </main>
  );
}
