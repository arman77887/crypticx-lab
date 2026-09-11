"use client";

import {
  useEffect,
  useMemo,
  useState,
} from "react";

import Link from "next/link";

import {
  AdminFindingLifecycle,
  getAdminFindingLifecycles,
  updateAdminFindingLifecycleStatus,
} from "@/lib/api";

type SeverityFilter =
  | "All"
  | "critical"
  | "high"
  | "medium"
  | "low"
  | "informational";

type StatusFilter =
  | "All"
  | "open"
  | "confirmed"
  | "resolved"
  | "reopened";

function formatDate(
  value?: string | null,
): string {
  if (!value) return "—";

  const date = new Date(value);

  if (Number.isNaN(date.getTime())) {
    return value;
  }

  return date.toLocaleString();
}

function severityClass(
  severity: string,
): string {
  switch (severity) {
    case "critical":
      return "border-red-500/30 bg-red-500/[0.10] text-red-300";
    case "high":
      return "border-red-500/20 bg-red-500/[0.06] text-red-300";
    case "medium":
      return "border-amber-500/20 bg-amber-500/[0.06] text-amber-300";
    case "low":
      return "border-blue-500/20 bg-blue-500/[0.06] text-blue-300";
    default:
      return "border-white/[0.08] bg-white/[0.03] text-white/45";
  }
}

function lifecycleClass(
  status: string,
): string {
  switch (status) {
    case "resolved":
      return "border-emerald-500/20 bg-emerald-500/[0.05] text-emerald-300";
    case "confirmed":
      return "border-red-500/20 bg-red-500/[0.06] text-red-300";
    case "reopened":
      return "border-red-500/30 bg-red-500/[0.10] text-red-300";
    case "open":
      return "border-amber-500/20 bg-amber-500/[0.05] text-amber-300";
    default:
      return "border-white/[0.08] bg-white/[0.03] text-white/45";
  }
}

function riskClass(level: string): string {
  if (
    level === "critical" ||
    level === "high"
  ) {
    return "text-red-300";
  }

  if (level === "medium") {
    return "text-amber-300";
  }

  return "text-white/55";
}

export default function AdminFindingsPage() {
  const [findings, setFindings] =
    useState<AdminFindingLifecycle[]>([]);

  const [query, setQuery] =
    useState("");

  const [severity, setSeverity] =
    useState<SeverityFilter>("All");

  const [status, setStatus] =
    useState<StatusFilter>("All");

  const [loading, setLoading] =
    useState(true);

  const [savingId, setSavingId] =
    useState<string | null>(null);

  const [error, setError] =
    useState("");

  const [selected, setSelected] =
    useState<AdminFindingLifecycle | null>(
      null,
    );

  async function loadFindings(
    silent = false,
  ) {
    try {
      if (!silent) {
        setLoading(true);
      }

      setError("");

      const response =
        await getAdminFindingLifecycles({
          per_page: 100,
        });

      const rows =
        response.data.data ?? [];

      setFindings(rows);

      setSelected((current) => {
        if (!current) return null;

        return (
          rows.find(
            (item) =>
              item.id === current.id,
          ) ?? current
        );
      });
    } catch (err) {
      setError(
        err instanceof Error
          ? err.message
          : "Unable to load finding registry.",
      );
    } finally {
      if (!silent) {
        setLoading(false);
      }
    }
  }

  useEffect(() => {
    void loadFindings();

    const timer =
      window.setInterval(() => {
        void loadFindings(true);
      }, 20000);

    return () =>
      window.clearInterval(timer);
  }, []);

  const filtered =
    useMemo(() => {
      const search =
        query.trim().toLowerCase();

      return findings.filter(
        (finding) => {
          const matchesQuery =
            !search ||
            finding.id
              .toLowerCase()
              .includes(search) ||
            finding.fingerprint
              .toLowerCase()
              .includes(search) ||
            finding.title
              .toLowerCase()
              .includes(search) ||
            finding.type
              .toLowerCase()
              .includes(search) ||
            (finding.target?.name ?? "")
              .toLowerCase()
              .includes(search) ||
            (finding.target?.url ?? "")
              .toLowerCase()
              .includes(search) ||
            (
              finding.target?.hostname ??
              ""
            )
              .toLowerCase()
              .includes(search) ||
            (finding.owner?.name ?? "")
              .toLowerCase()
              .includes(search) ||
            (finding.owner?.email ?? "")
              .toLowerCase()
              .includes(search);

          const matchesSeverity =
            severity === "All" ||
            finding.severity === severity;

          const matchesStatus =
            status === "All" ||
            finding.status === status;

          return (
            matchesQuery &&
            matchesSeverity &&
            matchesStatus
          );
        },
      );
    }, [
      findings,
      query,
      severity,
      status,
    ]);

  async function transition(
    finding: AdminFindingLifecycle,
    nextStatus: "confirmed" | "resolved",
  ) {
    const verb =
      nextStatus === "confirmed"
        ? "confirm"
        : "resolve";

    if (
      !window.confirm(
        `Are you sure you want to ${verb} "${finding.title}"?`,
      )
    ) {
      return;
    }

    try {
      setSavingId(finding.id);
      setError("");

      const response =
        await updateAdminFindingLifecycleStatus(
          finding.id,
          nextStatus,
        );

      setFindings((items) =>
        items.map((item) =>
          item.id === finding.id
            ? response.data
            : item,
        ),
      );

      setSelected((current) =>
        current?.id === finding.id
          ? response.data
          : current,
      );
    } catch (err) {
      setError(
        err instanceof Error
          ? err.message
          : "Finding transition failed.",
      );
    } finally {
      setSavingId(null);
    }
  }

  const currentCount =
    findings.filter((item) =>
      ["open", "confirmed", "reopened"].includes(
        item.status,
      ),
    ).length;

  const criticalHigh =
    findings.filter((item) =>
      ["critical", "high"].includes(
        item.severity,
      ),
    ).length;

  const reopened =
    findings.filter(
      (item) => item.status === "reopened",
    ).length;

  const resolved =
    findings.filter(
      (item) => item.status === "resolved",
    ).length;

  return (
    <main className="min-h-screen bg-[#050505] text-white">

      <section className="mx-auto max-w-7xl px-5 pb-24 pt-28 sm:px-8">
        <div className="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
          <div>
            <div className="mb-3 inline-flex items-center gap-2 rounded-full border border-red-500/15 bg-red-500/[0.04] px-4 py-2 text-[10px] font-bold uppercase tracking-[0.22em] text-red-400">
              <span className="h-1.5 w-1.5 rounded-full bg-red-500 shadow-[0_0_12px_rgba(239,68,68,.7)]" />
              Admin · Finding Intelligence
            </div>

            <h1 className="text-4xl font-black tracking-tight sm:text-5xl">
              Finding Registry
            </h1>

            <p className="mt-4 max-w-3xl text-sm leading-7 text-white/40">
              Platform-wide vulnerability
              lifecycle intelligence with
              recurrence, evidence,
              remediation and deterministic
              risk prioritization.
            </p>
          </div>

          <div className="flex flex-wrap gap-3">
            <Link
              href="/admin"
              className="cx-button cx-button-secondary rounded-xl px-5 py-3 text-sm font-semibold"
            >
              ← Command Center
            </Link>

            <button
              type="button"
              onClick={() =>
                void loadFindings()
              }
              disabled={loading}
              className="cx-button cx-button-primary rounded-xl px-5 py-3 text-sm font-semibold disabled:opacity-40"
            >
              {loading
                ? "Synchronizing..."
                : "↻ Refresh"}
            </button>
          </div>
        </div>

        {error && (
          <div className="mt-6 rounded-2xl border border-red-500/20 bg-red-500/[0.06] px-4 py-3 text-sm text-red-200">
            {error}
          </div>
        )}

        <section className="mt-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
          {[
            [
              findings.length,
              "Lifecycle Records",
            ],
            [currentCount, "Current"],
            [
              criticalHigh,
              "Critical + High",
            ],
            [reopened, "Reopened"],
            [resolved, "Resolved"],
          ].map(([value, label]) => (
            <div
              key={String(label)}
              className="rounded-2xl border border-white/[0.07] bg-[#09090b] p-5 shadow-[0_14px_40px_rgba(0,0,0,.35)]"
            >
              <div className="text-3xl font-black">
                {loading ? "—" : value}
              </div>

              <div className="mt-3 text-[10px] font-bold uppercase tracking-[0.15em] text-white/40">
                {label}
              </div>
            </div>
          ))}
        </section>

        <section className="mt-5 rounded-2xl border border-white/[0.07] bg-[#09090b] p-5">
          <div className="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
            <input
              type="search"
              value={query}
              onChange={(event) =>
                setQuery(event.target.value)
              }
              placeholder="Search title, fingerprint, target, owner, type..."
              className="cx-input w-full rounded-xl px-4 py-3 text-sm xl:max-w-xl"
            />

            <div className="text-xs text-white/25">
              Auto-refresh every 20 seconds
            </div>
          </div>

          <div className="mt-4 flex flex-wrap gap-2">
            {(
              [
                "All",
                "critical",
                "high",
                "medium",
                "low",
                "informational",
              ] as const
            ).map((item) => (
              <button
                key={item}
                type="button"
                onClick={() =>
                  setSeverity(item)
                }
                className={
                  severity === item
                    ? "cx-button cx-button-primary rounded-xl px-3 py-2 text-xs font-semibold"
                    : "cx-button cx-button-secondary rounded-xl px-3 py-2 text-xs font-semibold"
                }
              >
                {item === "All"
                  ? "Severity: All"
                  : item}
              </button>
            ))}
          </div>

          <div className="mt-3 flex flex-wrap gap-2">
            {(
              [
                "All",
                "open",
                "confirmed",
                "reopened",
                "resolved",
              ] as const
            ).map((item) => (
              <button
                key={item}
                type="button"
                onClick={() =>
                  setStatus(item)
                }
                className={
                  status === item
                    ? "cx-button cx-button-primary rounded-xl px-3 py-2 text-xs font-semibold"
                    : "cx-button cx-button-secondary rounded-xl px-3 py-2 text-xs font-semibold"
                }
              >
                {item === "All"
                  ? "Status: All"
                  : item}
              </button>
            ))}
          </div>
        </section>

        <section className="mt-5 overflow-hidden rounded-2xl border border-white/[0.07] bg-[#09090b]">
          <div className="flex items-center justify-between border-b border-white/[0.06] px-5 py-4">
            <div>
              <div className="text-[10px] font-bold uppercase tracking-[0.22em] text-red-400/75">
                Current Lifecycle State
              </div>

              <h2 className="mt-1 text-base font-bold">
                Security Findings
              </h2>
            </div>

            <div className="text-xs text-white/25">
              {loading
                ? "Loading..."
                : `${filtered.length} of ${findings.length}`}
            </div>
          </div>

          {loading ? (
            <div className="space-y-3 p-5">
              {[1, 2, 3].map(
                (item) => (
                  <div
                    key={item}
                    className="animate-pulse rounded-xl border border-white/[0.06] bg-black/20 p-5"
                  >
                    <div className="h-4 w-72 rounded bg-white/[0.05]" />
                    <div className="mt-3 h-3 w-96 max-w-full rounded bg-white/[0.04]" />
                  </div>
                ),
              )}
            </div>
          ) : filtered.length ? (
            <div className="divide-y divide-white/[0.05]">
              {filtered.map(
                (finding) => (
                  <article
                    key={finding.id}
                    className="p-5 transition hover:bg-red-500/[0.025]"
                  >
                    <div className="flex flex-col gap-5 xl:flex-row xl:items-start xl:justify-between">
                      <div className="min-w-0 flex-1">
                        <div className="flex flex-wrap items-center gap-2">
                          <h3 className="font-bold text-white/90">
                            {finding.title}
                          </h3>

                          <span
                            className={`rounded-full border px-3 py-1 text-[10px] font-bold uppercase tracking-wider ${severityClass(
                              finding.severity,
                            )}`}
                          >
                            {finding.severity}
                          </span>

                          <span
                            className={`rounded-full border px-3 py-1 text-[10px] font-bold uppercase tracking-wider ${lifecycleClass(
                              finding.status,
                            )}`}
                          >
                            {finding.status}
                          </span>
                        </div>

                        <div className="mt-2 break-all text-sm text-white/40">
                          {finding.target?.url ??
                            "Target unavailable"}
                        </div>

                        <div className="mt-3 flex flex-wrap gap-x-5 gap-y-2 text-[11px] text-white/30">
                          <span>
                            Type:{" "}
                            <span className="text-white/55">
                              {finding.type}
                            </span>
                          </span>

                          <span>
                            Confidence:{" "}
                            <span className="text-white/55">
                              {finding.confidence}
                            </span>
                          </span>

                          <span>
                            Occurrences:{" "}
                            <span className="text-white/55">
                              {
                                finding.occurrence_count
                              }
                            </span>
                          </span>

                          <span>
                            Owner:{" "}
                            <span className="text-white/55">
                              {finding.owner?.name ??
                                "Unknown"}
                            </span>
                          </span>
                        </div>

                        <div className="mt-4 flex flex-wrap gap-4">
                          <div className="rounded-xl border border-red-500/15 bg-red-500/[0.04] px-4 py-3">
                            <div className="text-[9px] font-bold uppercase tracking-[0.16em] text-white/25">
                              Risk
                            </div>

                            <div
                              className={`mt-1 text-lg font-black ${riskClass(
                                finding.risk.level,
                              )}`}
                            >
                              {
                                finding.risk
                                  .score
                              }
                              /100
                            </div>
                          </div>

                          <div className="rounded-xl border border-white/[0.06] bg-black/20 px-4 py-3">
                            <div className="text-[9px] font-bold uppercase tracking-[0.16em] text-white/25">
                              Last Seen
                            </div>

                            <div className="mt-1 text-xs font-semibold text-white/55">
                              {formatDate(
                                finding.last_seen_at,
                              )}
                            </div>
                          </div>

                          <div className="rounded-xl border border-white/[0.06] bg-black/20 px-4 py-3">
                            <div className="text-[9px] font-bold uppercase tracking-[0.16em] text-white/25">
                              Last Assessment
                            </div>

                            <div className="mt-1 text-xs font-semibold text-white/55">
                              {finding.last_assessment?.profile ??
                                "—"}
                            </div>
                          </div>
                        </div>

                        <div className="mt-3 break-all text-[10px] text-white/15">
                          Fingerprint:{" "}
                          {finding.fingerprint}
                        </div>
                      </div>

                      <div className="flex shrink-0 flex-wrap gap-2">
                        <button
                          type="button"
                          onClick={() =>
                            setSelected(
                              finding,
                            )
                          }
                          className="cx-button cx-button-secondary rounded-xl px-4 py-2 text-xs font-semibold"
                        >
                          Inspect
                        </button>

                        {[
                          "open",
                          "reopened",
                        ].includes(
                          finding.status,
                        ) && (
                          <button
                            type="button"
                            disabled={
                              savingId ===
                              finding.id
                            }
                            onClick={() =>
                              void transition(
                                finding,
                                "confirmed",
                              )
                            }
                            className="cx-button cx-button-primary rounded-xl px-4 py-2 text-xs font-semibold disabled:opacity-40"
                          >
                            Confirm
                          </button>
                        )}

                        {finding.status ===
                          "confirmed" && (
                          <button
                            type="button"
                            disabled={
                              savingId ===
                              finding.id
                            }
                            onClick={() =>
                              void transition(
                                finding,
                                "resolved",
                              )
                            }
                            className="cx-button cx-button-primary rounded-xl px-4 py-2 text-xs font-semibold disabled:opacity-40"
                          >
                            Resolve
                          </button>
                        )}
                      </div>
                    </div>
                  </article>
                ),
              )}
            </div>
          ) : (
            <div className="px-5 py-16 text-center text-sm font-semibold text-white/40">
              No lifecycle records match
              these filters.
            </div>
          )}
        </section>

        <section className="mt-6 grid gap-5 lg:grid-cols-2">
          <div className="rounded-2xl border border-white/[0.07] bg-[#09090b] p-6">
            <div className="text-[10px] font-bold uppercase tracking-[0.22em] text-red-400">
              Lifecycle Contract
            </div>

            <h2 className="mt-2 text-xl font-black">
              Open → Confirmed → Resolved
            </h2>

            <p className="mt-3 text-sm leading-7 text-white/35">
              Reopened findings can be
              confirmed again. Reopening itself
              remains automatic when a
              previously resolved fingerprint
              is detected by a later successful
              assessment.
            </p>
          </div>

          <div className="rounded-2xl border border-white/[0.07] bg-[#09090b] p-6">
            <div className="text-[10px] font-bold uppercase tracking-[0.22em] text-red-400">
              Risk Semantics
            </div>

            <h2 className="mt-2 text-xl font-black">
              Prioritization, Not Probability
            </h2>

            <p className="mt-3 text-sm leading-7 text-white/35">
              Risk /100 combines severity,
              confidence, recurrence, current
              lifecycle state and configured
              asset importance. It does not
              represent a percentage chance of
              compromise.
            </p>
          </div>
        </section>
      </section>

      {selected && (
        <div className="fixed inset-0 z-[100] flex items-center justify-center bg-black/80 p-4 backdrop-blur-sm">
          <div className="max-h-[92vh] w-full max-w-3xl overflow-y-auto rounded-3xl border border-red-500/15 bg-[#09090b] p-6 shadow-[0_30px_100px_rgba(0,0,0,.8)]">
            <div className="flex items-start justify-between gap-5">
              <div>
                <div className="text-[10px] font-bold uppercase tracking-[0.22em] text-red-400">
                  Finding Intelligence
                </div>

                <h2 className="mt-2 text-2xl font-black">
                  {selected.title}
                </h2>
              </div>

              <button
                type="button"
                onClick={() =>
                  setSelected(null)
                }
                className="text-xl text-white/35 hover:text-white"
              >
                ×
              </button>
            </div>

            <div className="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
              {[
                [
                  "Lifecycle",
                  selected.status,
                ],
                [
                  "Severity",
                  selected.severity,
                ],
                [
                  "Confidence",
                  selected.confidence,
                ],
                [
                  "Risk",
                  `${selected.risk.score}/100 · ${selected.risk.level}`,
                ],
                [
                  "Occurrences",
                  String(
                    selected.occurrence_count,
                  ),
                ],
                [
                  "Type",
                  selected.type,
                ],
                [
                  "First Seen",
                  formatDate(
                    selected.first_seen_at,
                  ),
                ],
                [
                  "Last Seen",
                  formatDate(
                    selected.last_seen_at,
                  ),
                ],
                [
                  "Resolved",
                  formatDate(
                    selected.resolved_at,
                  ),
                ],
              ].map(
                ([label, value]) => (
                  <div
                    key={label}
                    className="rounded-xl border border-white/[0.06] bg-black/25 p-4"
                  >
                    <div className="text-[9px] font-bold uppercase tracking-[0.16em] text-white/25">
                      {label}
                    </div>

                    <div className="mt-2 break-words text-sm font-semibold text-white/65">
                      {value}
                    </div>
                  </div>
                ),
              )}
            </div>

            <div className="mt-5 rounded-xl border border-white/[0.06] bg-black/25 p-4">
              <div className="text-[9px] font-bold uppercase tracking-[0.16em] text-white/25">
                Description
              </div>

              <div className="mt-2 whitespace-pre-wrap text-sm leading-7 text-white/55">
                {selected.latest_finding
                  ?.description ||
                  "No description recorded."}
              </div>
            </div>

            <div className="mt-4 rounded-xl border border-white/[0.06] bg-black/25 p-4">
              <div className="text-[9px] font-bold uppercase tracking-[0.16em] text-white/25">
                Evidence
              </div>

              <div className="mt-2 whitespace-pre-wrap break-words text-sm leading-7 text-white/55">
                {selected.latest_finding
                  ?.evidence ||
                  "No textual evidence recorded."}
              </div>

              {selected.latest_finding
                ?.evidence_data && (
                <pre className="mt-4 overflow-x-auto rounded-xl border border-white/[0.05] bg-black/40 p-4 text-xs leading-6 text-white/45">
                  {JSON.stringify(
                    selected.latest_finding
                      .evidence_data,
                    null,
                    2,
                  )}
                </pre>
              )}
            </div>

            <div className="mt-4 rounded-xl border border-red-500/15 bg-red-500/[0.035] p-4">
              <div className="text-[9px] font-bold uppercase tracking-[0.16em] text-red-400">
                Remediation
              </div>

              <div className="mt-2 whitespace-pre-wrap text-sm leading-7 text-white/55">
                {selected.latest_finding
                  ?.remediation ||
                  "No remediation guidance recorded."}
              </div>
            </div>

            <div className="mt-4 rounded-xl border border-white/[0.06] bg-black/25 p-4">
              <div className="text-[9px] font-bold uppercase tracking-[0.16em] text-white/25">
                Risk Components
              </div>

              <div className="mt-4 grid gap-3 sm:grid-cols-2">
                {[
                  [
                    "Severity",
                    `${selected.risk.components.severity.score}/${selected.risk.components.severity.max}`,
                  ],
                  [
                    "Confidence",
                    `${selected.risk.components.confidence.score}/${selected.risk.components.confidence.max}`,
                  ],
                  [
                    "Recurrence",
                    `${selected.risk.components.recurrence.score}/${selected.risk.components.recurrence.max}`,
                  ],
                  [
                    "Lifecycle",
                    `${selected.risk.components.lifecycle.score}/${selected.risk.components.lifecycle.max}`,
                  ],
                  [
                    "Asset Importance",
                    `${selected.risk.components.asset_importance.score}/${selected.risk.components.asset_importance.max}`,
                  ],
                ].map(
                  ([label, value]) => (
                    <div
                      key={label}
                      className="flex items-center justify-between rounded-lg border border-white/[0.05] px-3 py-2 text-xs"
                    >
                      <span className="text-white/35">
                        {label}
                      </span>

                      <span className="font-bold text-white/65">
                        {value}
                      </span>
                    </div>
                  ),
                )}
              </div>
            </div>

            <div className="mt-6 flex flex-wrap justify-end gap-2">
              {[
                "open",
                "reopened",
              ].includes(
                selected.status,
              ) && (
                <button
                  type="button"
                  disabled={
                    savingId === selected.id
                  }
                  onClick={() =>
                    void transition(
                      selected,
                      "confirmed",
                    )
                  }
                  className="cx-button cx-button-primary rounded-xl px-5 py-3 text-sm font-semibold disabled:opacity-40"
                >
                  Confirm Finding
                </button>
              )}

              {selected.status ===
                "confirmed" && (
                <button
                  type="button"
                  disabled={
                    savingId === selected.id
                  }
                  onClick={() =>
                    void transition(
                      selected,
                      "resolved",
                    )
                  }
                  className="cx-button cx-button-primary rounded-xl px-5 py-3 text-sm font-semibold disabled:opacity-40"
                >
                  Resolve Finding
                </button>
              )}

              <button
                type="button"
                onClick={() =>
                  setSelected(null)
                }
                className="cx-button cx-button-secondary rounded-xl px-5 py-3 text-sm font-semibold"
              >
                Close
              </button>
            </div>
          </div>
        </div>
      )}
    </main>
  );
}
