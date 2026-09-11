"use client";

import Link from "next/link";
import { useCallback, useEffect, useState } from "react";
import { useParams } from "next/navigation";
import {
  confirmFinding,
  getFinding,
  getFindingHistory,
  getFindingLifecycle,
  getStoredToken,
  resolveFinding,
} from "@/lib/api";

type UnknownRecord = Record<string, unknown>;

function isRecord(value: unknown): value is UnknownRecord {
  return typeof value === "object" && value !== null;
}

function nested(
  value: UnknownRecord | null,
  key: string,
): UnknownRecord | null {
  const result = value?.[key];
  return isRecord(result) ? result : null;
}

function display(
  record: UnknownRecord | null,
  key: string,
): string {
  const result = record?.[key];

  if (result === null || result === undefined || result === "") {
    return "—";
  }

  if (
    typeof result === "string" ||
    typeof result === "number" ||
    typeof result === "boolean"
  ) {
    return String(result);
  }

  return JSON.stringify(result, null, 2);
}

function extractFinding(response: unknown): UnknownRecord | null {
  if (!isRecord(response)) return null;

  const data = response.data;

  if (!isRecord(data)) return null;

  if (isRecord(data.finding)) {
    return data.finding;
  }

  return typeof data.id === "string" ? data : null;
}

function extractLifecycle(response: unknown): UnknownRecord | null {
  if (!isRecord(response)) return null;

  const data = response.data;

  if (!isRecord(data)) return null;

  return isRecord(data.lifecycle) ? data.lifecycle : null;
}

function extractRisk(response: unknown): UnknownRecord | null {
  if (!isRecord(response)) return null;

  const data = response.data;

  if (!isRecord(data)) return null;

  return isRecord(data.risk) ? data.risk : null;
}

function extractHistory(response: unknown): UnknownRecord[] {
  if (!isRecord(response)) return [];

  const data = response.data;

  if (!isRecord(data) || !Array.isArray(data.history)) {
    return [];
  }

  return data.history.filter(isRecord);
}

function riskBadge(level: string) {
  switch (level.toLowerCase()) {
    case "critical":
      return "bg-red-600/10 text-red-300";
    case "high":
      return "bg-orange-500/10 text-orange-700";
    case "medium":
      return "bg-amber-500/10 text-amber-700";
    case "low":
      return "bg-blue-500/10 text-blue-300";
    default:
      return "bg-white/[0.04] text-[var(--cx-muted)]";
  }
}

function statusBadge(status: string) {
  switch (status.toLowerCase()) {
    case "confirmed":
      return "bg-blue-500/10 text-blue-300";
    case "resolved":
      return "bg-emerald-500/10 text-emerald-300";
    case "open":
      return "bg-amber-500/10 text-amber-700";
    case "reopened":
      return "bg-orange-500/10 text-orange-700";
    default:
      return "bg-white/[0.04] text-[var(--cx-muted)]";
  }
}

export default function FindingDetailsPage() {
  const params = useParams();
  const id = String(params?.id ?? "");

  const [finding, setFinding] =
    useState<UnknownRecord | null>(null);

  const [lifecycle, setLifecycle] =
    useState<UnknownRecord | null>(null);

  const [risk, setRisk] =
    useState<UnknownRecord | null>(null);

  const [history, setHistory] =
    useState<UnknownRecord[]>([]);

  const [loading, setLoading] = useState(true);
  const [actionLoading, setActionLoading] = useState(false);
  const [error, setError] = useState("");
  const [notice, setNotice] = useState("");

  const loadData = useCallback(async () => {
    const [
      findingResponse,
      lifecycleResponse,
      historyResponse,
    ] = await Promise.all([
      getFinding(id),
      getFindingLifecycle(id),
      getFindingHistory(id),
    ]);

    setFinding(extractFinding(findingResponse));
    setLifecycle(extractLifecycle(lifecycleResponse));
    setRisk(extractRisk(lifecycleResponse));
    setHistory(extractHistory(historyResponse));
  }, [id]);

  useEffect(() => {
    let active = true;

    async function start() {
      if (!getStoredToken()) {
        window.location.href = "/login";
        return;
      }

      try {
        await loadData();
      } catch (err) {
        if (active) {
          setError(
            err instanceof Error
              ? err.message
              : "Unable to load finding.",
          );
        }
      } finally {
        if (active) {
          setLoading(false);
        }
      }
    }

    if (id) {
      start();
    }

    return () => {
      active = false;
    };
  }, [id, loadData]);

  async function runAction(action: "confirm" | "resolve") {
    setActionLoading(true);
    setError("");
    setNotice("");

    try {
      if (action === "confirm") {
        await confirmFinding(id);
        setNotice("Finding confirmed successfully.");
      } else {
        await resolveFinding(id);
        setNotice("Finding resolved successfully.");
      }

      await loadData();
    } catch (err) {
      setError(
        err instanceof Error
          ? err.message
          : "Unable to update finding status.",
      );
    } finally {
      setActionLoading(false);
    }
  }

  const target = nested(finding, "target");
  const lifecycleStatus = display(lifecycle, "status");
  const reopenedAt = display(lifecycle, "reopened_at");

  const riskLevel = display(risk, "level");
  const riskScore = display(risk, "score");
  const riskComponents = nested(risk, "components");

  return (
    <main className="min-h-screen bg-[var(--cx-bg)] text-[var(--cx-text)]">
      <div className="mx-auto max-w-6xl px-4 py-10 sm:px-6 lg:px-8">
        <Link href="/findings" className="text-sm font-semibold">
          ← Back to Findings
        </Link>

        {loading ? (
          <div className="cx-card mt-6 p-8 text-sm text-[var(--cx-muted)]">
            Loading finding intelligence...
          </div>
        ) : !finding ? (
          <div className="cx-card mt-6 p-8">
            Finding not found.
          </div>
        ) : (
          <>
            <section className="mt-6">
              <p className="text-xs font-semibold uppercase tracking-[0.18em] text-[var(--cx-muted)]">
                Finding Intelligence
              </p>

              <div className="mt-3 flex flex-col gap-5 md:flex-row md:items-start md:justify-between">
                <div>
                  <h1 className="text-3xl font-bold tracking-tight">
                    {display(finding, "title")}
                  </h1>

                  <p className="mt-3 text-sm text-[var(--cx-muted)]">
                    {display(target, "hostname")}
                  </p>
                </div>

                <div className="flex flex-wrap gap-3">
                  {["open", "reopened"].includes(lifecycleStatus) && (
                    <button
                      type="button"
                      disabled={actionLoading}
                      onClick={() => runAction("confirm")}
                      className="cx-button cx-button-primary disabled:cursor-not-allowed disabled:opacity-60"
                    >
                      {actionLoading
                        ? "Updating..."
                        : "Confirm Finding"}
                    </button>
                  )}

                  {lifecycleStatus === "confirmed" && (
                    <button
                      type="button"
                      disabled={actionLoading}
                      onClick={() => runAction("resolve")}
                      className="cx-button cx-button-primary disabled:cursor-not-allowed disabled:opacity-60"
                    >
                      {actionLoading
                        ? "Updating..."
                        : "Mark Resolved"}
                    </button>
                  )}

                  {lifecycleStatus === "resolved" && (
                    <span className="rounded-xl bg-emerald-500/10 px-4 py-3 text-sm font-semibold text-emerald-300">
                      Resolved
                    </span>
                  )}
                </div>
              </div>
            </section>

            {notice && (
              <div className="mt-6 rounded-2xl bg-emerald-500/10 p-4 text-sm text-emerald-300">
                {notice}
              </div>
            )}

            {error && (
              <div
                role="alert"
                className="mt-6 rounded-2xl bg-red-500/10 p-4 text-sm text-red-300"
              >
                {error}
              </div>
            )}

            <section className="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
              <div className="cx-card p-5">
                <p className="text-xs uppercase tracking-[0.14em] text-[var(--cx-muted)]">
                  Severity
                </p>
                <p className="mt-2 font-semibold">
                  {display(finding, "severity")}
                </p>
              </div>

              <div className="cx-card p-5">
                <p className="text-xs uppercase tracking-[0.14em] text-[var(--cx-muted)]">
                  Confidence
                </p>
                <p className="mt-2 font-semibold">
                  {display(finding, "confidence")}
                </p>
              </div>

              <div className="cx-card p-5">
                <p className="text-xs uppercase tracking-[0.14em] text-[var(--cx-muted)]">
                  Lifecycle Status
                </p>

                <span
                  className={`mt-2 inline-flex rounded-full px-3 py-1 text-xs font-semibold ${statusBadge(
                    lifecycleStatus,
                  )}`}
                >
                  {lifecycleStatus}
                </span>
              </div>

              <div className="cx-card p-5">
                <p className="text-xs uppercase tracking-[0.14em] text-[var(--cx-muted)]">
                  Type
                </p>
                <p className="mt-2 font-semibold">
                  {display(finding, "type")}
                </p>
              </div>
            </section>

            {reopenedAt !== "—" && lifecycleStatus === "reopened" && (
              <section className="mt-6 rounded-2xl border border-amber-500/20 bg-amber-500/5 p-5">
                <p className="font-semibold">
                  This finding was reopened automatically.
                </p>
                <p className="mt-1 text-sm text-[var(--cx-muted)]">
                  It was detected again after being previously resolved.
                  Reopened at {reopenedAt}.
                </p>
              </section>
            )}

            <section className="cx-card mt-8 p-6">
              <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                  <p className="text-xs font-semibold uppercase tracking-[0.16em] text-[var(--cx-muted)]">
                    Risk Intelligence
                  </p>
                  <h2 className="mt-1 text-xl font-bold">
                    Overall Risk
                  </h2>
                </div>

                <div className="flex items-center gap-3">
                  <span className="text-3xl font-bold">
                    {riskScore === "—" ? "—" : `${riskScore}/100`}
                  </span>

                  {riskLevel !== "—" && (
                    <span
                      className={`rounded-full px-3 py-1 text-xs font-bold uppercase ${riskBadge(
                        riskLevel,
                      )}`}
                    >
                      {riskLevel}
                    </span>
                  )}
                </div>
              </div>

              <div className="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                {[
                  ["Severity", nested(riskComponents, "severity")],
                  ["Confidence", nested(riskComponents, "confidence")],
                  ["Recurrence", nested(riskComponents, "recurrence")],
                  ["Lifecycle", nested(riskComponents, "lifecycle")],
                  [
                    "Asset Importance",
                    nested(riskComponents, "asset_importance"),
                  ],
                ].map(([label, component]) => {
                  const item = isRecord(component) ? component : null;

                  const value =
                    label === "Recurrence"
                      ? display(item, "occurrence_count")
                      : label === "Lifecycle"
                        ? display(item, "status")
                        : display(item, "value");

                  return (
                    <div
                      key={String(label)}
                      className="cx-inset-sm rounded-2xl p-4"
                    >
                      <p className="text-xs text-[var(--cx-muted)]">
                        {String(label)}
                      </p>

                      <p className="mt-2 font-semibold capitalize">
                        {value}
                      </p>

                      <p className="mt-1 text-xs text-[var(--cx-muted)]">
                        {display(item, "score")} / {display(item, "max")}
                      </p>
                    </div>
                  );
                })}
              </div>

              <p className="mt-5 text-xs leading-5 text-[var(--cx-muted)]">
                Risk is calculated deterministically from severity,
                confidence, recurrence, lifecycle state, and asset importance.
              </p>
            </section>

            <section className="mt-8 grid gap-6 lg:grid-cols-2">
              <div className="cx-card p-6">
                <h2 className="text-lg font-bold">Description</h2>
                <p className="mt-3 whitespace-pre-wrap text-sm leading-6 text-[var(--cx-muted)]">
                  {display(finding, "description")}
                </p>
              </div>

              <div className="cx-card p-6">
                <h2 className="text-lg font-bold">Remediation</h2>
                <p className="mt-3 whitespace-pre-wrap text-sm leading-6 text-[var(--cx-muted)]">
                  {display(finding, "remediation")}
                </p>
              </div>
            </section>

            <section className="cx-card mt-6 p-6">
              <h2 className="text-lg font-bold">Evidence</h2>

              <pre className="mt-4 overflow-x-auto whitespace-pre-wrap rounded-2xl bg-white/[0.04] p-4 text-xs leading-6">
                {display(finding, "evidence_data")}
              </pre>
            </section>

            <section className="cx-card mt-6 p-6">
              <h2 className="text-lg font-bold">Lifecycle</h2>

              <div className="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                {[
                  ["Status", display(lifecycle, "status")],
                  [
                    "Occurrences",
                    display(lifecycle, "occurrence_count"),
                  ],
                  [
                    "First Seen",
                    display(lifecycle, "first_seen_at"),
                  ],
                  [
                    "Last Seen",
                    display(lifecycle, "last_seen_at"),
                  ],
                ].map(([label, content]) => (
                  <div
                    key={label}
                    className="cx-inset-sm rounded-2xl p-4"
                  >
                    <p className="text-xs text-[var(--cx-muted)]">
                      {label}
                    </p>
                    <p className="mt-2 text-sm font-semibold">
                      {content}
                    </p>
                  </div>
                ))}
              </div>
            </section>

            <section className="cx-card mt-6 p-6">
              <h2 className="text-lg font-bold">
                Assessment History
              </h2>

              {history.length === 0 ? (
                <p className="mt-3 text-sm text-[var(--cx-muted)]">
                  No historical occurrences found.
                </p>
              ) : (
                <div className="mt-5 divide-y divide-[var(--cx-border)]">
                  {history.map((item, index) => (
                    <div
                      key={`${display(item, "id")}-${index}`}
                      className="py-4"
                    >
                      <div className="flex flex-wrap items-center justify-between gap-3">
                        <p className="font-semibold">
                          {display(item, "title")}
                        </p>

                        <span className="text-xs text-[var(--cx-muted)]">
                          {display(item, "created_at")}
                        </span>
                      </div>

                      <div className="mt-2 flex flex-wrap gap-4 text-xs text-[var(--cx-muted)]">
                        <span>
                          Assessment:{" "}
                          {display(item, "assessment_id")}
                        </span>

                        <span>
                          Status: {display(item, "status")}
                        </span>
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </section>
          </>
        )}
      </div>
    </main>
  );
}
