"use client";

import {
  useEffect,
  useMemo,
  useState,
} from "react";
import Link from "next/link";
import {
  AdminReportRecord,
  getAdminReports,
} from "@/lib/api";

function formatDate(
  value?: string | null,
): string {
  if (!value) {
    return "—";
  }

  const date = new Date(value);

  if (Number.isNaN(date.getTime())) {
    return value;
  }

  return date.toLocaleString();
}

function riskClasses(
  level?: string | null,
): string {
  switch (
    (level ?? "").toLowerCase()
  ) {
    case "critical":
      return "border-red-500/30 bg-red-500/[0.10] text-red-300";

    case "high":
      return "border-orange-500/25 bg-orange-500/[0.08] text-orange-300";

    case "medium":
      return "border-amber-500/20 bg-amber-500/[0.07] text-amber-300";

    case "low":
      return "border-blue-500/20 bg-blue-500/[0.06] text-blue-300";

    case "informational":
      return "border-white/[0.08] bg-white/[0.03] text-white/55";

    default:
      return "border-white/[0.08] bg-white/[0.03] text-white/50";
  }
}

function statusClasses(
  status?: string | null,
): string {
  if (
    (status ?? "").toLowerCase() ===
    "ready"
  ) {
    return "border-emerald-500/20 bg-emerald-500/[0.06] text-emerald-300";
  }

  return "border-white/[0.08] bg-white/[0.03] text-white/55";
}

function getTargetName(
  report: AdminReportRecord,
): string {
  return (
    report.target?.name ||
    report.target_snapshot?.name ||
    report.target?.hostname ||
    report.target_snapshot?.hostname ||
    "Unnamed target"
  );
}

function getTargetHostname(
  report: AdminReportRecord,
): string {
  return (
    report.target?.hostname ||
    report.target_snapshot?.hostname ||
    "—"
  );
}

function getProfile(
  report: AdminReportRecord,
): string {
  return (
    report.assessment?.profile ||
    report.assessment_snapshot?.profile ||
    "—"
  );
}

export default function AdminReportsPage() {
  const [reports, setReports] =
    useState<AdminReportRecord[]>([]);

  const [totalReports, setTotalReports] =
    useState(0);

  const [query, setQuery] =
    useState("");

  const [loading, setLoading] =
    useState(true);

  const [refreshing, setRefreshing] =
    useState(false);

  const [error, setError] =
    useState("");

  async function loadReports(
    silent = false,
  ) {
    try {
      if (silent) {
        setRefreshing(true);
      } else {
        setLoading(true);
      }

      setError("");

      const response =
        await getAdminReports(100);

      setReports(
        response.data.data ?? [],
      );

      setTotalReports(
        response.data.total ?? 0,
      );
    } catch (err) {
      setError(
        err instanceof Error
          ? err.message
          : "Unable to load reports.",
      );
    } finally {
      if (silent) {
        setRefreshing(false);
      } else {
        setLoading(false);
      }
    }
  }

  useEffect(() => {
    void loadReports();

    const timer =
      window.setInterval(() => {
        void loadReports(true);
      }, 30000);

    return () =>
      window.clearInterval(timer);
  }, []);

  const filteredReports =
    useMemo(() => {
      const search =
        query.trim().toLowerCase();

      if (!search) {
        return reports;
      }

      return reports.filter(
        (report) => {
          const ownerName =
            report.owner?.name ?? "";

          const ownerEmail =
            report.owner?.email ?? "";

          const targetName =
            getTargetName(report);

          const hostname =
            getTargetHostname(report);

          const profile =
            getProfile(report);

          return (
            report.id
              .toLowerCase()
              .includes(search) ||
            (report.title ?? "")
              .toLowerCase()
              .includes(search) ||
            report.assessment_id
              .toLowerCase()
              .includes(search) ||
            ownerName
              .toLowerCase()
              .includes(search) ||
            ownerEmail
              .toLowerCase()
              .includes(search) ||
            targetName
              .toLowerCase()
              .includes(search) ||
            hostname
              .toLowerCase()
              .includes(search) ||
            profile
              .toLowerCase()
              .includes(search)
          );
        },
      );
    }, [reports, query]);

  const readyLoaded =
    reports.filter(
      (report) =>
        report.status
          ?.toLowerCase() === "ready",
    ).length;

  const immutableLoaded =
    reports.filter(
      (report) =>
        report.summary
          ?.immutable_snapshot === true,
    ).length;

  const findingsLoaded =
    reports.reduce(
      (total, report) =>
        total +
        (
          report.summary
            ?.findings_count ?? 0
        ),
      0,
    );

  return (
    <main className="min-h-screen bg-[var(--cx-bg)] text-[var(--cx-text)]">

      <section className="mx-auto max-w-7xl px-4 pb-16 pt-8 sm:px-6 lg:px-8">
        <div className="mb-8 flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
          <div>
            <div className="mb-3 inline-flex items-center gap-2 rounded-full border border-red-500/20 bg-red-500/[0.06] px-3 py-1 text-xs font-medium uppercase tracking-[0.18em] text-red-300">
              <span className="h-1.5 w-1.5 rounded-full bg-red-400 shadow-[0_0_12px_rgba(239,68,68,0.8)]" />
              Admin Intelligence
            </div>

            <h1 className="text-3xl font-semibold tracking-tight text-white sm:text-4xl">
              Reports Registry
            </h1>

            <p className="mt-3 max-w-3xl text-sm leading-6 text-white/50 sm:text-base">
              Platform-wide persisted security
              reports generated from completed
              assessments. Report snapshots are
              preserved independently from later
              finding lifecycle changes.
            </p>
          </div>

          <button
            type="button"
            onClick={() =>
              void loadReports(true)
            }
            disabled={
              loading || refreshing
            }
            className="cx-button cx-button-secondary min-w-[130px] disabled:cursor-not-allowed disabled:opacity-50"
          >
            {refreshing
              ? "Refreshing..."
              : "Refresh"}
          </button>
        </div>

        <div className="mb-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          <div className="cx-card p-5">
            <p className="text-xs font-medium uppercase tracking-[0.15em] text-white/35">
              Total Reports
            </p>

            <p className="mt-3 text-3xl font-semibold text-white">
              {loading
                ? "—"
                : totalReports}
            </p>

            <p className="mt-2 text-xs text-white/35">
              Platform-wide persisted count
            </p>
          </div>

          <div className="cx-card p-5">
            <p className="text-xs font-medium uppercase tracking-[0.15em] text-white/35">
              Ready Loaded
            </p>

            <p className="mt-3 text-3xl font-semibold text-emerald-300">
              {loading
                ? "—"
                : readyLoaded}
            </p>

            <p className="mt-2 text-xs text-white/35">
              Current loaded page only
            </p>
          </div>

          <div className="cx-card p-5">
            <p className="text-xs font-medium uppercase tracking-[0.15em] text-white/35">
              Immutable Loaded
            </p>

            <p className="mt-3 text-3xl font-semibold text-red-300">
              {loading
                ? "—"
                : immutableLoaded}
            </p>

            <p className="mt-2 text-xs text-white/35">
              Frozen report snapshots
            </p>
          </div>

          <div className="cx-card p-5">
            <p className="text-xs font-medium uppercase tracking-[0.15em] text-white/35">
              Findings Loaded
            </p>

            <p className="mt-3 text-3xl font-semibold text-white">
              {loading
                ? "—"
                : findingsLoaded}
            </p>

            <p className="mt-2 text-xs text-white/35">
              Snapshot findings on this page
            </p>
          </div>
        </div>

        <div className="cx-card overflow-hidden">
          <div className="border-b border-white/[0.06] p-5 sm:p-6">
            <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
              <div>
                <h2 className="text-lg font-semibold text-white">
                  Persisted Reports
                </h2>

                <p className="mt-1 text-sm text-white/40">
                  Showing{" "}
                  {
                    filteredReports.length
                  }{" "}
                  of {reports.length} loaded
                  reports.
                </p>
              </div>

              <div className="w-full lg:max-w-md">
                <input
                  value={query}
                  onChange={(event) =>
                    setQuery(
                      event.target.value,
                    )
                  }
                  placeholder="Search report, owner, target, assessment..."
                  className="cx-input w-full"
                />
              </div>
            </div>
          </div>

          {error ? (
            <div className="border-b border-red-500/15 bg-red-500/[0.05] px-5 py-4 text-sm text-red-300 sm:px-6">
              {error}
            </div>
          ) : null}

          {loading ? (
            <div className="px-6 py-16 text-center">
              <div className="mx-auto h-8 w-8 animate-spin rounded-full border-2 border-white/10 border-t-red-500" />

              <p className="mt-4 text-sm text-white/40">
                Loading persisted reports...
              </p>
            </div>
          ) : filteredReports.length ===
            0 ? (
            <div className="px-6 py-16 text-center">
              <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl border border-white/[0.06] bg-white/[0.02] text-xl text-white/30">
                R
              </div>

              <h3 className="mt-4 font-medium text-white">
                {reports.length === 0
                  ? "No reports generated yet"
                  : "No matching reports"}
              </h3>

              <p className="mx-auto mt-2 max-w-md text-sm leading-6 text-white/40">
                {reports.length === 0
                  ? "Reports will appear here after completed assessments are used to generate persisted report snapshots."
                  : "Try a different report ID, owner, target, hostname, profile, or assessment ID."}
              </p>
            </div>
          ) : (
            <>
              <div className="hidden overflow-x-auto xl:block">
                <table className="w-full min-w-[1180px] text-left">
                  <thead className="border-b border-white/[0.06] bg-white/[0.015] text-[11px] uppercase tracking-[0.14em] text-white/30">
                    <tr>
                      <th className="px-5 py-4 font-medium">
                        Report
                      </th>

                      <th className="px-5 py-4 font-medium">
                        Owner
                      </th>

                      <th className="px-5 py-4 font-medium">
                        Target
                      </th>

                      <th className="px-5 py-4 font-medium">
                        Assessment
                      </th>

                      <th className="px-5 py-4 font-medium">
                        Risk
                      </th>

                      <th className="px-5 py-4 font-medium">
                        Findings
                      </th>

                      <th className="px-5 py-4 font-medium">
                        Snapshot
                      </th>

                      <th className="px-5 py-4 font-medium">
                        Generated
                      </th>

                      <th className="px-5 py-4 text-right font-medium">
                        Action
                      </th>
                    </tr>
                  </thead>

                  <tbody className="divide-y divide-white/[0.05]">
                    {filteredReports.map(
                      (report) => (
                        <tr
                          key={report.id}
                          className="transition hover:bg-red-500/[0.025]"
                        >
                          <td className="px-5 py-5 align-top">
                            <div className="max-w-[230px]">
                              <p className="truncate font-medium text-white">
                                {report.title ||
                                  "Security Report"}
                              </p>

                              <p className="mt-1 truncate font-mono text-[11px] text-white/30">
                                {report.id}
                              </p>

                              <span
                                className={`mt-2 inline-flex rounded-full border px-2 py-0.5 text-[10px] font-medium uppercase tracking-wider ${statusClasses(
                                  report.status,
                                )}`}
                              >
                                {report.status ||
                                  "unknown"}
                              </span>
                            </div>
                          </td>

                          <td className="px-5 py-5 align-top">
                            <p className="max-w-[180px] truncate text-sm text-white/80">
                              {report.owner
                                ?.name ??
                                "—"}
                            </p>

                            <p className="mt-1 max-w-[180px] truncate text-xs text-white/35">
                              {report.owner
                                ?.email ??
                                "—"}
                            </p>
                          </td>

                          <td className="px-5 py-5 align-top">
                            <p className="max-w-[190px] truncate text-sm text-white/80">
                              {getTargetName(
                                report,
                              )}
                            </p>

                            <p className="mt-1 max-w-[190px] truncate font-mono text-xs text-white/35">
                              {getTargetHostname(
                                report,
                              )}
                            </p>
                          </td>

                          <td className="px-5 py-5 align-top">
                            <p className="text-sm capitalize text-white/70">
                              {getProfile(
                                report,
                              )}
                            </p>

                            <p className="mt-1 max-w-[160px] truncate font-mono text-[11px] text-white/30">
                              {
                                report.assessment_id
                              }
                            </p>
                          </td>

                          <td className="px-5 py-5 align-top">
                            <span
                              className={`inline-flex rounded-xl border px-2.5 py-1 text-sm font-semibold ${riskClasses(
                                report.summary
                                  ?.risk_level,
                              )}`}
                            >
                              {report.summary
                                ?.risk_score ??
                                "—"}
                              /100
                            </span>

                            <p className="mt-1 text-[11px] capitalize text-white/30">
                              {report.summary
                                ?.risk_level ??
                                "unscored"}
                            </p>
                          </td>

                          <td className="px-5 py-5 align-top text-sm text-white/70">
                            {report.summary
                              ?.findings_count ??
                              0}
                          </td>

                          <td className="px-5 py-5 align-top">
                            {report.summary
                              ?.immutable_snapshot ? (
                              <span className="inline-flex items-center gap-1.5 rounded-full border border-red-500/20 bg-red-500/[0.06] px-2.5 py-1 text-[11px] font-medium text-red-300">
                                <span className="h-1.5 w-1.5 rounded-full bg-red-400" />
                                Immutable
                              </span>
                            ) : (
                              <span className="text-xs text-white/35">
                                Not marked
                              </span>
                            )}
                          </td>

                          <td className="px-5 py-5 align-top text-xs leading-5 text-white/45">
                            {formatDate(
                              report.generated_at,
                            )}
                          </td>

                          <td className="px-5 py-5 text-right align-top">
                            <Link
                              href={`/admin/reports/${report.id}`}
                              className="inline-flex rounded-lg border border-red-500/20 bg-red-500/[0.06] px-3 py-2 text-xs font-medium text-red-300 transition hover:bg-red-500/[0.10]"
                            >
                              View Report
                            </Link>
                          </td>
                        </tr>
                      ),
                    )}
                  </tbody>
                </table>
              </div>

              <div className="divide-y divide-white/[0.06] xl:hidden">
                {filteredReports.map(
                  (report) => (
                    <article
                      key={report.id}
                      className="p-5 sm:p-6"
                    >
                      <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div className="min-w-0">
                          <p className="truncate font-medium text-white">
                            {report.title ||
                              "Security Report"}
                          </p>

                          <p className="mt-1 truncate font-mono text-[11px] text-white/30">
                            {report.id}
                          </p>
                        </div>

                        <div className="flex flex-wrap gap-2">
                          <span
                            className={`inline-flex rounded-full border px-2.5 py-1 text-[10px] font-medium uppercase tracking-wider ${statusClasses(
                              report.status,
                            )}`}
                          >
                            {report.status}
                          </span>

                          {report.summary
                            ?.immutable_snapshot ? (
                            <span className="inline-flex rounded-full border border-red-500/20 bg-red-500/[0.06] px-2.5 py-1 text-[10px] font-medium text-red-300">
                              Immutable
                            </span>
                          ) : null}
                        </div>
                      </div>

                      <div className="mt-5 grid gap-4 sm:grid-cols-2">
                        <div>
                          <p className="text-[10px] uppercase tracking-wider text-white/25">
                            Owner
                          </p>

                          <p className="mt-1 text-sm text-white/70">
                            {report.owner
                              ?.name ?? "—"}
                          </p>

                          <p className="mt-1 truncate text-xs text-white/35">
                            {report.owner
                              ?.email ?? "—"}
                          </p>
                        </div>

                        <div>
                          <p className="text-[10px] uppercase tracking-wider text-white/25">
                            Target
                          </p>

                          <p className="mt-1 truncate text-sm text-white/70">
                            {getTargetName(
                              report,
                            )}
                          </p>

                          <p className="mt-1 truncate font-mono text-xs text-white/35">
                            {getTargetHostname(
                              report,
                            )}
                          </p>
                        </div>

                        <div>
                          <p className="text-[10px] uppercase tracking-wider text-white/25">
                            Assessment
                          </p>

                          <p className="mt-1 text-sm capitalize text-white/70">
                            {getProfile(
                              report,
                            )}
                          </p>

                          <p className="mt-1 truncate font-mono text-[11px] text-white/30">
                            {
                              report.assessment_id
                            }
                          </p>
                        </div>

                        <div>
                          <p className="text-[10px] uppercase tracking-wider text-white/25">
                            Risk / Findings
                          </p>

                          <p className="mt-1 text-sm text-white/70">
                            {report.summary
                              ?.risk_score ??
                              "—"}
                            /100
                            <span className="mx-2 text-white/20">
                              ·
                            </span>
                            {report.summary
                              ?.findings_count ??
                              0}{" "}
                            findings
                          </p>
                        </div>
                      </div>

                      <div className="mt-5 flex flex-col gap-3 border-t border-white/[0.05] pt-4 sm:flex-row sm:items-center sm:justify-between">
                        <p className="text-xs text-white/35">
                          Generated{" "}
                          {formatDate(
                            report.generated_at,
                          )}
                        </p>

                        <Link
                          href={`/admin/reports/${report.id}`}
                          className="cx-button cx-button-secondary text-center text-sm"
                        >
                          View Report
                        </Link>
                      </div>
                    </article>
                  ),
                )}
              </div>
            </>
          )}
        </div>

        <div className="mt-6 rounded-2xl border border-red-500/10 bg-red-500/[0.025] px-5 py-4 text-xs leading-5 text-white/40">
          Risk values are prioritization
          scores on a 0–100 scale, not
          probabilities of compromise.
          Report data shown here comes
          from persisted generation-time
          snapshots rather than a fresh
          recalculation of current finding
          lifecycle state.
        </div>
      </section>
    </main>
  );
}
