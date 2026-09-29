"use client";

import Link from "next/link";
import { FormEvent, useCallback, useEffect, useState } from "react";
import {
  ApiFinding,
  AssessmentScanType,
  deleteFinding,
  getAssessments,
  getFindings,
  getStoredToken,
} from "@/lib/api";

type UnknownRecord = Record<string, unknown>;

type AssessmentTarget = {
  id?: string;
  name?: string;
  url?: string;
  hostname?: string;
};

type Assessment = {
  id: string;
  target_id?: string;
  profile?: string;
  status?: string;
  progress?: number;
  created_at?: string;
  queued_at?: string | null;
  started_at?: string | null;
  completed_at?: string | null;
  configuration?: Record<string, unknown> | null;
  target?: AssessmentTarget | null;
  findings_count?: number;
  critical_findings_count?: number;
  high_findings_count?: number;
  medium_findings_count?: number;
  low_findings_count?: number;
  informational_findings_count?: number;
};

type Pagination = {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
};

type AssessmentList = {
  assessments: Assessment[];
  pagination: Pagination;
};

function isRecord(value: unknown): value is UnknownRecord {
  return typeof value === "object" && value !== null;
}

function numberValue(value: unknown, fallback = 0): number {
  return typeof value === "number" ? value : fallback;
}

function extractAssessments(response: unknown): AssessmentList {
  const empty: AssessmentList = {
    assessments: [],
    pagination: {
      current_page: 1,
      last_page: 1,
      per_page: 20,
      total: 0,
    },
  };

  if (!isRecord(response) || !isRecord(response.data)) {
    return empty;
  }

  const paginator = response.data;

  const assessments = Array.isArray(paginator.data)
    ? (paginator.data as Assessment[])
    : [];

  return {
    assessments,
    pagination: {
      current_page: numberValue(paginator.current_page, 1),
      last_page: numberValue(paginator.last_page, 1),
      per_page: numberValue(paginator.per_page, 20),
      total: numberValue(paginator.total, assessments.length),
    },
  };
}

function formatDate(value?: string | null) {
  if (!value) return "—";

  const date = new Date(value);

  return Number.isNaN(date.getTime())
    ? value
    : date.toLocaleString();
}

function scanTypeOf(
  assessment: Assessment,
): AssessmentScanType | null {
  const value = assessment.configuration?.scan_type;

  if (
    value === "web_security" ||
    value === "ssl_tls" ||
    value === "dns_intelligence" ||
    value === "api_security"
  ) {
    return value;
  }

  return null;
}

function scanTypeLabel(assessment: Assessment) {
  switch (scanTypeOf(assessment)) {
    case "web_security":
      return "Web Security";
    case "ssl_tls":
      return "SSL / TLS";
    case "dns_intelligence":
      return "DNS Intelligence";
    case "api_security":
      return "API Security";
    default:
      return `Legacy / ${assessment.profile || "Unknown"}`;
  }
}

function statusClass(status?: string) {
  switch ((status || "").toLowerCase()) {
    case "completed":
      return "bg-emerald-500/10 text-emerald-300";
    case "running":
      return "bg-blue-500/10 text-blue-300";
    case "queued":
      return "bg-amber-500/10 text-amber-300";
    case "failed":
      return "bg-red-500/10 text-red-300";
    default:
      return "bg-white/[0.04] text-[var(--cx-muted)]";
  }
}

function severityClass(severity?: string) {
  switch ((severity || "").toLowerCase()) {
    case "critical":
      return "bg-red-600/10 text-red-300";
    case "high":
      return "bg-orange-500/10 text-orange-300";
    case "medium":
      return "bg-amber-500/10 text-amber-300";
    case "low":
      return "bg-blue-500/10 text-blue-300";
    default:
      return "bg-white/[0.04] text-[var(--cx-muted)]";
  }
}

function targetLabel(assessment: Assessment) {



  return (
    assessment.target?.hostname ||
    assessment.target?.name ||
    assessment.target?.url ||
    "Unknown target"
  );
}

export default function FindingsPage() {
  const [assessments, setAssessments] = useState<Assessment[]>([]);
  const [pagination, setPagination] = useState<Pagination>({
    current_page: 1,
    last_page: 1,
    per_page: 20,
    total: 0,
  });

  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  const [searchInput, setSearchInput] = useState("");
  const [search, setSearch] = useState("");
  const [scanType, setScanType] = useState("");
  const [status, setStatus] = useState("");
  const [severity, setSeverity] = useState("");
  const [from, setFrom] = useState("");
  const [to, setTo] = useState("");
  const [page, setPage] = useState(1);

  const [expanded, setExpanded] = useState<string | null>(null);
  const [deletingFindingId, setDeletingFindingId] =
    useState<string | null>(null);
  const [deleteError, setDeleteError] =
    useState<string | null>(null);

  const [findingMap, setFindingMap] = useState<
    Record<string, ApiFinding[]>
  >({});
  const [findingLoading, setFindingLoading] = useState<
    Record<string, boolean>
  >({});
  const [findingErrors, setFindingErrors] = useState<
    Record<string, string>
  >({});

  const loadAssessments = useCallback(async () => {
    if (!getStoredToken()) {
      window.location.href = "/login";
      return;
    }

    setLoading(true);
    setError("");

    try {
      const response = await getAssessments({
        page,
        per_page: 20,
        search: search || undefined,
        scan_type:
          (scanType as AssessmentScanType) || undefined,
        status: status || undefined,
        severity: severity || undefined,
        from: from || undefined,
        to: to || undefined,
      });

      const parsed = extractAssessments(response);

      setAssessments(parsed.assessments);
      setPagination(parsed.pagination);
    } catch (err) {
      setError(
        err instanceof Error
          ? err.message
          : "Unable to load assessment history.",
      );
    } finally {
      setLoading(false);
    }
  }, [page, search, scanType, status, severity, from, to]);

  useEffect(() => {
    void loadAssessments();
  }, [loadAssessments]);

  async function toggleAssessment(assessmentId: string) {
    if (expanded === assessmentId) {
      setExpanded(null);
      return;
    }

    setExpanded(assessmentId);

    if (findingMap[assessmentId]) {
      return;
    }

    setFindingLoading((current) => ({
      ...current,
      [assessmentId]: true,
    }));

    setFindingErrors((current) => ({
      ...current,
      [assessmentId]: "",
    }));

    try {
      const response = await getFindings({
        assessment_id: assessmentId,
        per_page: 100,
      });

      setFindingMap((current) => ({
        ...current,
        [assessmentId]: Array.isArray(response.data)
          ? response.data
          : [],
      }));
    } catch (err) {
      setFindingErrors((current) => ({
        ...current,
        [assessmentId]:
          err instanceof Error
            ? err.message
            : "Unable to load findings.",
      }));
    } finally {
      setFindingLoading((current) => ({
        ...current,
        [assessmentId]: false,
      }));
    }
  }

  function submitSearch(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setPage(1);
    setSearch(searchInput.trim());
  }

  function resetFilters() {
    setSearchInput("");
    setSearch("");
    setScanType("");
    setStatus("");
    setSeverity("");
    setFrom("");
    setTo("");
    setPage(1);
    setExpanded(null);
  }

  const handleDeleteFinding = async (
    finding: ApiFinding,
  ) => {
    const confirmed = window.confirm(
      `Delete "${finding.title}" from this scan?\n\n` +
        "This permanently deletes this finding occurrence. " +
        "Other scan history will be preserved.",
    );

    if (!confirmed) {
      return;
    }

    setDeleteError(null);
    setDeletingFindingId(finding.id);

    try {
      await deleteFinding(finding.id);

      setFindingMap((current) => {
        const next = { ...current };

        for (const assessmentId of Object.keys(next)) {
          next[assessmentId] = next[assessmentId].filter(
            (item) => item.id !== finding.id,
          );
        }

        return next;
      });

      await loadAssessments();
    } catch (error) {
      setDeleteError(
        error instanceof Error
          ? error.message
          : "Unable to delete finding.",
      );
    } finally {
      setDeletingFindingId(null);
    }
  };


  return (
    <main className="min-h-screen bg-[var(--cx-bg)] text-[var(--cx-text)]">
      <section className="border-b border-[var(--cx-border)]">
        <div className="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
          <p className="text-xs font-semibold uppercase tracking-[0.18em] text-[var(--cx-muted)]">
            Finding Intelligence
          </p>

          <div className="mt-2 flex flex-col gap-5 md:flex-row md:items-end md:justify-between">
            <div>
              <h1 className="text-3xl font-bold tracking-tight">
                Assessment History
              </h1>

              <p className="mt-2 max-w-3xl text-sm text-[var(--cx-muted)]">
                Every assessment is preserved separately. Open a scan
                to inspect only the findings produced by that
                assessment.
              </p>
            </div>

            <div className="flex flex-wrap gap-3">
              <Link
                href="/dashboard"
                className="cx-button cx-button-secondary"
              >
                Dashboard
              </Link>

              <Link
                href="/scanner"
                className="cx-button cx-button-primary"
              >
                New Assessment
              </Link>
            </div>
          </div>
        </div>
      </section>

      <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <section className="cx-card p-5">
          <form
            onSubmit={submitSearch}
            className="grid gap-3 lg:grid-cols-[minmax(220px,2fr)_repeat(3,minmax(140px,1fr))]"
          >
            <input
              value={searchInput}
              onChange={(event) =>
                setSearchInput(event.target.value)
              }
              placeholder="Search domain, URL or assessment ID"
              className="cx-input"
            />

            <select
              value={scanType}
              onChange={(event) => {
                setScanType(event.target.value);
                setPage(1);
              }}
              className="cx-input"
            >
              <option value="">All scan types</option>
              <option value="web_security">Web Security</option>
              <option value="ssl_tls">SSL / TLS</option>
              <option value="dns_intelligence">
                DNS Intelligence
              </option>
              <option value="api_security">API Security</option>
            </select>

            <select
              value={status}
              onChange={(event) => {
                setStatus(event.target.value);
                setPage(1);
              }}
              className="cx-input"
            >
              <option value="">All statuses</option>
              <option value="queued">Queued</option>
              <option value="running">Running</option>
              <option value="completed">Completed</option>
              <option value="failed">Failed</option>
            </select>

            <select
              value={severity}
              onChange={(event) => {
                setSeverity(event.target.value);
                setPage(1);
              }}
              className="cx-input"
            >
              <option value="">All severities</option>
              <option value="critical">Critical</option>
              <option value="high">High</option>
              <option value="medium">Medium</option>
              <option value="low">Low</option>
              <option value="informational">
                Informational
              </option>
            </select>

            <input
              type="date"
              value={from}
              onChange={(event) => {
                setFrom(event.target.value);
                setPage(1);
              }}
              className="cx-input"
              aria-label="From date"
            />

            <input
              type="date"
              value={to}
              onChange={(event) => {
                setTo(event.target.value);
                setPage(1);
              }}
              className="cx-input"
              aria-label="To date"
            />

            <button
              type="submit"
              className="cx-button cx-button-primary"
            >
              Search
            </button>

            <button
              type="button"
              onClick={resetFilters}
              className="cx-button cx-button-secondary"
            >
              Reset
            </button>
          </form>
        </section>

        <div className="mt-6 flex items-center justify-between gap-4">
          <p className="text-sm text-[var(--cx-muted)]">
            {loading
              ? "Loading assessments..."
              : `${pagination.total} assessment${
                  pagination.total === 1 ? "" : "s"
                }`}
          </p>

          <p className="text-xs text-[var(--cx-muted)]">
            Page {pagination.current_page} of{" "}
            {pagination.last_page}
          </p>
        </div>

        {error ? (
          <div className="cx-card mt-5 p-6 text-sm text-red-400">
            {error}
          </div>
        ) : loading ? (
          <div className="cx-card mt-5 p-8 text-sm text-[var(--cx-muted)]">
            Loading assessment history...
          </div>
        ) : assessments.length === 0 ? (
          <div className="cx-card mt-5 p-8">
            <p className="font-semibold">
              No assessments match these filters.
            </p>
            <p className="mt-2 text-sm text-[var(--cx-muted)]">
              Adjust the filters or run a new authorized assessment.
            </p>
          </div>
        ) : (
          <section className="mt-5 space-y-4">
        {deleteError && (
          <div
            role="alert"
            className="mb-4 rounded-xl border border-red-500/30 bg-red-500/10 p-4 text-sm text-red-300"
          >
            {deleteError}
          </div>
        )}

            {assessments.map((assessment) => {
              const isOpen = expanded === assessment.id;
              const findings = findingMap[assessment.id] || [];

              return (
                <article
                  key={assessment.id}
                  className="cx-card overflow-hidden"
                >
                  <button
                    type="button"
                    onClick={() =>
                      void toggleAssessment(assessment.id)
                    }
                    className="w-full p-5 text-left sm:p-6"
                  >
                    <div className="flex flex-col gap-5 xl:flex-row xl:items-center xl:justify-between">
                      <div className="min-w-0">
                        <div className="flex flex-wrap items-center gap-2">
                          <h2 className="truncate text-lg font-bold">
                            {targetLabel(assessment)}
                          </h2>

                          <span className="rounded-full bg-white/[0.05] px-3 py-1 text-xs font-semibold">
                            {scanTypeLabel(assessment)}
                          </span>

                          <span
                            className={`rounded-full px-3 py-1 text-xs font-semibold ${statusClass(
                              assessment.status,
                            )}`}
                          >
                            {assessment.status || "unknown"}
                          </span>
                        </div>

                        {assessment.target?.url ? (
                          <p className="mt-2 truncate text-sm text-[var(--cx-muted)]">
                            {assessment.target.url}
                          </p>
                        ) : null}

                        <div className="mt-3 flex flex-wrap gap-x-5 gap-y-2 text-xs text-[var(--cx-muted)]">
                          <span>
                            Scan:{" "}
                            {formatDate(
                              assessment.completed_at ||
                                assessment.created_at,
                            )}
                          </span>

                          <span className="font-mono">
                            ID: {assessment.id}
                          </span>

                          <span>
                            Profile:{" "}
                            {assessment.profile || "—"}
                          </span>
                        </div>
                      </div>

                      <div className="flex flex-wrap items-center gap-2">
                        <span className="rounded-lg bg-white/[0.04] px-3 py-2 text-xs">
                          Total{" "}
                          <strong>
                            {assessment.findings_count ?? 0}
                          </strong>
                        </span>

                        <span className="rounded-lg bg-red-600/10 px-3 py-2 text-xs text-red-300">
                          Critical{" "}
                          <strong>
                            {assessment.critical_findings_count ?? 0}
                          </strong>
                        </span>

                        <span className="rounded-lg bg-orange-500/10 px-3 py-2 text-xs text-orange-300">
                          High{" "}
                          <strong>
                            {assessment.high_findings_count ?? 0}
                          </strong>
                        </span>

                        <span className="rounded-lg bg-amber-500/10 px-3 py-2 text-xs text-amber-300">
                          Medium{" "}
                          <strong>
                            {assessment.medium_findings_count ?? 0}
                          </strong>
                        </span>

                        <span className="rounded-lg bg-blue-500/10 px-3 py-2 text-xs text-blue-300">
                          Low{" "}
                          <strong>
                            {assessment.low_findings_count ?? 0}
                          </strong>
                        </span>

                        <span className="rounded-lg bg-white/[0.04] px-3 py-2 text-xs text-[var(--cx-muted)]">
                          Info{" "}
                          <strong>
                            {assessment.informational_findings_count ??
                              0}
                          </strong>
                        </span>

                        <span className="ml-1 text-sm font-semibold">
                          {isOpen ? "Hide findings ↑" : "View findings ↓"}
                        </span>
                      </div>
                    </div>
                  </button>

                  {isOpen ? (
                    <div className="border-t border-[var(--cx-border)] bg-black/10">
                      {findingLoading[assessment.id] ? (
                        <div className="p-6 text-sm text-[var(--cx-muted)]">
                          Loading findings...
                        </div>
                      ) : findingErrors[assessment.id] ? (
                        <div className="p-6 text-sm text-red-400">
                          {findingErrors[assessment.id]}
                        </div>
                      ) : findings.length === 0 ? (
                        <div className="p-6 text-sm text-[var(--cx-muted)]">
                          This assessment produced no findings.
                        </div>
                      ) : (
                        <div className="divide-y divide-[var(--cx-border)]">
                          {findings.map((finding) => (
                            <div
                              key={finding.id}
                              className="p-5 sm:p-6"
                            >
                              <div className="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                                <div className="min-w-0">
                                  <div className="flex flex-wrap items-center gap-2">
                                    <Link
                                      href={`/findings/${finding.id}`}
                                      className="font-semibold hover:underline"
                                    >
                                      {finding.title}
                                    </Link>

                          <button
                            type="button"
                            onClick={() =>
                              void handleDeleteFinding(finding)
                            }
                            disabled={
                              deletingFindingId === finding.id
                            }
                            className="rounded-lg border border-red-500/40 px-3 py-2 text-sm font-medium text-red-300 transition hover:bg-red-500/10 disabled:cursor-not-allowed disabled:opacity-50"
                          >
                            {deletingFindingId === finding.id
                              ? "Deleting..."
                              : "Delete"}
                          </button>

                                    <span
                                      className={`rounded-full px-3 py-1 text-xs font-semibold ${severityClass(
                                        finding.severity,
                                      )}`}
                                    >
                                      {finding.severity}
                                    </span>

                                    <span className="rounded-full bg-white/[0.04] px-3 py-1 text-xs text-[var(--cx-muted)]">
                                      {finding.status}
                                    </span>
                                  </div>

                                  <p className="mt-2 text-sm text-[var(--cx-muted)]">
                                    {finding.description}
                                  </p>

                                  <div className="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-[var(--cx-muted)]">
                                    <span>
                                      Type: {finding.type}
                                    </span>
                                    <span>
                                      Confidence:{" "}
                                      {finding.confidence}
                                    </span>
                                    <span>
                                      Found:{" "}
                                      {formatDate(
                                        finding.created_at,
                                      )}
                                    </span>
                                  </div>
                                </div>

                                <Link
                                  href={`/findings/${finding.id}`}
                                  className="cx-button cx-button-secondary shrink-0"
                                >
                                  View details
                                </Link>
                              </div>
                            </div>
                          ))}
                        </div>
                      )}
                    </div>
                  ) : null}
                </article>
              );
            })}
          </section>
        )}

        {pagination.last_page > 1 ? (
          <div className="mt-6 flex items-center justify-center gap-3">
            <button
              type="button"
              disabled={pagination.current_page <= 1 || loading}
              onClick={() =>
                setPage((current) => Math.max(1, current - 1))
              }
              className="cx-button cx-button-secondary disabled:cursor-not-allowed disabled:opacity-40"
            >
              Previous
            </button>

            <span className="text-sm text-[var(--cx-muted)]">
              {pagination.current_page} / {pagination.last_page}
            </span>

            <button
              type="button"
              disabled={
                pagination.current_page >= pagination.last_page ||
                loading
              }
              onClick={() =>
                setPage((current) =>
                  Math.min(
                    pagination.last_page,
                    current + 1,
                  ),
                )
              }
              className="cx-button cx-button-secondary disabled:cursor-not-allowed disabled:opacity-40"
            >
              Next
            </button>
          </div>
        ) : null}
      </div>
    </main>
  );
}
