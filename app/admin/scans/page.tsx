"use client";

import {
  useEffect,
  useMemo,
  useState,
} from "react";

import Link from "next/link";

import {
  AdminAssessment,
  getAdminAssessments,
} from "@/lib/api";

type StatusFilter =
  | "All"
  | "queued"
  | "running"
  | "completed"
  | "failed"
  | "blocked";

type ProfileFilter =
  | "All"
  | "discovery"
  | "standard"
  | "deep";

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

function durationLabel(
  assessment: AdminAssessment,
): string {
  if (!assessment.started_at) {
    return "Not started";
  }

  const start = new Date(
    assessment.started_at,
  ).getTime();

  const end = assessment.completed_at
    ? new Date(
        assessment.completed_at,
      ).getTime()
    : Date.now();

  if (
    Number.isNaN(start) ||
    Number.isNaN(end) ||
    end < start
  ) {
    return "—";
  }

  const totalSeconds =
    Math.floor((end - start) / 1000);

  if (totalSeconds < 60) {
    return `${totalSeconds}s`;
  }

  const minutes =
    Math.floor(totalSeconds / 60);

  const seconds =
    totalSeconds % 60;

  if (minutes < 60) {
    return `${minutes}m ${seconds}s`;
  }

  const hours =
    Math.floor(minutes / 60);

  const remainingMinutes =
    minutes % 60;

  return `${hours}h ${remainingMinutes}m`;
}

function statusClasses(
  status: string,
): string {
  switch (status) {
    case "completed":
      return "border-emerald-500/15 bg-emerald-500/[0.05] text-emerald-300";

    case "failed":
    case "blocked":
      return "border-red-500/20 bg-red-500/[0.07] text-red-300";

    case "running":
      return "border-red-500/20 bg-red-500/[0.06] text-red-300";

    case "queued":
      return "border-amber-500/15 bg-amber-500/[0.05] text-amber-300";

    default:
      return "border-white/[0.08] bg-white/[0.03] text-white/50";
  }
}

export default function AdminScansPage() {
  const [assessments, setAssessments] =
    useState<AdminAssessment[]>([]);

  const [query, setQuery] =
    useState("");

  const [status, setStatus] =
    useState<StatusFilter>("All");

  const [profile, setProfile] =
    useState<ProfileFilter>("All");

  const [loading, setLoading] =
    useState(true);

  const [error, setError] =
    useState("");

  const [selected, setSelected] =
    useState<AdminAssessment | null>(
      null,
    );

  async function loadAssessments(
    silent = false,
  ) {
    try {
      if (!silent) {
        setLoading(true);
      }

      setError("");

      const response =
        await getAdminAssessments({
          per_page: 100,
        });

      setAssessments(
        response.data.data ?? [],
      );

      setSelected((current) => {
        if (!current) {
          return null;
        }

        return (
          response.data.data.find(
            (item) =>
              item.id === current.id,
          ) ?? current
        );
      });
    } catch (err) {
      setError(
        err instanceof Error
          ? err.message
          : "Unable to load assessment jobs.",
      );
    } finally {
      if (!silent) {
        setLoading(false);
      }
    }
  }

  useEffect(() => {
    void loadAssessments();

    const timer =
      window.setInterval(() => {
        void loadAssessments(true);
      }, 15000);

    return () =>
      window.clearInterval(timer);
  }, []);

  const filtered =
    useMemo(() => {
      const search =
        query.trim().toLowerCase();

      return assessments.filter(
        (assessment) => {
          const owner =
            assessment.owner;

          const target =
            assessment.target;

          const matchesSearch =
            !search ||
            assessment.id
              .toLowerCase()
              .includes(search) ||
            assessment.profile
              .toLowerCase()
              .includes(search) ||
            (
              assessment.worker_id ??
              ""
            )
              .toLowerCase()
              .includes(search) ||
            (owner?.name ?? "")
              .toLowerCase()
              .includes(search) ||
            (owner?.email ?? "")
              .toLowerCase()
              .includes(search) ||
            (target?.name ?? "")
              .toLowerCase()
              .includes(search) ||
            (target?.url ?? "")
              .toLowerCase()
              .includes(search) ||
            (target?.hostname ?? "")
              .toLowerCase()
              .includes(search);

          const matchesStatus =
            status === "All" ||
            assessment.status ===
              status;

          const matchesProfile =
            profile === "All" ||
            assessment.profile ===
              profile;

          return (
            matchesSearch &&
            matchesStatus &&
            matchesProfile
          );
        },
      );
    }, [
      assessments,
      query,
      status,
      profile,
    ]);

  const countStatus = (
    targetStatus: string,
  ) =>
    assessments.filter(
      (item) =>
        item.status === targetStatus,
    ).length;

  const running =
    countStatus("running");

  const queued =
    countStatus("queued");

  const completed =
    countStatus("completed");

  const failed =
    countStatus("failed");

  const blocked =
    countStatus("blocked");

  return (
    <main className="min-h-screen bg-[#050505] text-white">

      <section className="mx-auto max-w-7xl px-5 pb-24 pt-28 sm:px-8">
        <div className="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
          <div>
            <div className="mb-3 inline-flex items-center gap-2 rounded-full border border-red-500/15 bg-red-500/[0.04] px-4 py-2 text-[10px] font-bold uppercase tracking-[0.22em] text-red-400">
              <span className="h-1.5 w-1.5 rounded-full bg-red-500 shadow-[0_0_12px_rgba(239,68,68,.7)]" />

              Admin · Assessment Operations
            </div>

            <h1 className="text-4xl font-black tracking-tight sm:text-5xl">
              Scan Operations
            </h1>

            <p className="mt-4 max-w-3xl text-sm leading-7 text-white/40">
              Platform-wide assessment
              execution telemetry including
              targets, operators, profiles,
              workers, progress, findings and
              failure state.
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
              disabled={loading}
              onClick={() =>
                void loadAssessments()
              }
              className="cx-button cx-button-primary rounded-xl px-5 py-3 text-sm font-semibold disabled:opacity-40"
            >
              {loading
                ? "Synchronizing..."
                : "↻ Refresh Jobs"}
            </button>
          </div>
        </div>

        {error && (
          <div className="mt-6 rounded-2xl border border-red-500/20 bg-red-500/[0.06] px-4 py-3 text-sm text-red-200">
            {error}
          </div>
        )}

        <section className="mt-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
          {[
            [
              assessments.length,
              "Total",
            ],
            [running, "Running"],
            [queued, "Queued"],
            [completed, "Completed"],
            [failed, "Failed"],
            [blocked, "Blocked"],
          ].map(([value, label]) => (
            <div
              key={String(label)}
              className="rounded-2xl border border-white/[0.07] bg-[#09090b] p-5 shadow-[0_14px_40px_rgba(0,0,0,.35)]"
            >
              <div className="flex items-start justify-between">
                <div className="text-3xl font-black">
                  {loading
                    ? "—"
                    : value}
                </div>

                <span className="mt-1 h-2 w-2 rounded-full bg-red-500/70 shadow-[0_0_12px_rgba(239,68,68,.45)]" />
              </div>

              <div className="mt-3 text-[10px] font-bold uppercase tracking-[0.15em] text-white/45">
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
                setQuery(
                  event.target.value,
                )
              }
              placeholder="Search job UUID, target, owner, profile or worker..."
              className="cx-input w-full rounded-xl px-4 py-3 text-sm xl:max-w-lg"
            />

            <div className="text-xs text-white/25">
              Auto-refresh every 15 seconds
            </div>
          </div>

          <div className="mt-4 flex flex-wrap gap-2">
            {(
              [
                "All",
                "running",
                "queued",
                "completed",
                "failed",
                "blocked",
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
                  : `Status: ${item}`}
              </button>
            ))}
          </div>

          <div className="mt-3 flex flex-wrap gap-2">
            {(
              [
                "All",
                "discovery",
                "standard",
                "deep",
              ] as const
            ).map((item) => (
              <button
                key={item}
                type="button"
                onClick={() =>
                  setProfile(item)
                }
                className={
                  profile === item
                    ? "cx-button cx-button-primary rounded-xl px-3 py-2 text-xs font-semibold"
                    : "cx-button cx-button-secondary rounded-xl px-3 py-2 text-xs font-semibold"
                }
              >
                {item === "All"
                  ? "Profile: All"
                  : `Profile: ${item}`}
              </button>
            ))}
          </div>
        </section>

        <section className="mt-5 overflow-hidden rounded-2xl border border-white/[0.07] bg-[#09090b]">
          <div className="flex flex-col gap-3 border-b border-white/[0.06] px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <div className="text-[10px] font-bold uppercase tracking-[0.22em] text-red-400/75">
                Assessment Pipeline
              </div>

              <h2 className="mt-1 text-base font-bold">
                Execution Jobs
              </h2>
            </div>

            <div className="text-xs text-white/25">
              {loading
                ? "Loading..."
                : `${filtered.length} of ${assessments.length} jobs`}
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
                    <div className="h-4 w-56 rounded bg-white/[0.05]" />

                    <div className="mt-3 h-3 w-80 max-w-full rounded bg-white/[0.04]" />
                  </div>
                ),
              )}
            </div>
          ) : filtered.length ? (
            <div className="divide-y divide-white/[0.05]">
              {filtered.map(
                (assessment) => (
                  <article
                    key={assessment.id}
                    className="p-5 transition hover:bg-red-500/[0.025]"
                  >
                    <div className="flex flex-col gap-5 xl:flex-row xl:items-center xl:justify-between">
                      <div className="min-w-0 flex-1">
                        <div className="flex flex-wrap items-center gap-2">
                          <h3 className="font-bold text-white/90">
                            {assessment.target?.name ??
                              "Unknown target"}
                          </h3>

                          <span className="rounded-full border border-white/[0.08] bg-white/[0.03] px-3 py-1 text-[10px] font-bold uppercase tracking-wider text-white/50">
                            {
                              assessment.profile
                            }
                          </span>

                          <span
                            className={`rounded-full border px-3 py-1 text-[10px] font-bold uppercase tracking-wider ${statusClasses(
                              assessment.status,
                            )}`}
                          >
                            {
                              assessment.status
                            }
                          </span>
                        </div>

                        <div className="mt-2 break-all text-sm text-white/40">
                          {assessment.target?.url ??
                            "Target unavailable"}
                        </div>

                        <div className="mt-3 flex flex-wrap gap-x-5 gap-y-2 text-[11px] text-white/30">
                          <span>
                            Owner:{" "}
                            <span className="text-white/55">
                              {assessment.owner?.name ??
                                "Unknown"}
                            </span>
                          </span>

                          <span>
                            Worker:{" "}
                            <span className="text-white/55">
                              {assessment.worker_id ??
                                "Not assigned"}
                            </span>
                          </span>

                          <span>
                            Findings:{" "}
                            <span className="text-white/55">
                              {
                                assessment.findings_count
                              }
                            </span>
                          </span>

                          <span>
                            Duration:{" "}
                            <span className="text-white/55">
                              {durationLabel(
                                assessment,
                              )}
                            </span>
                          </span>
                        </div>

                        <div className="mt-4">
                          <div className="mb-2 flex items-center justify-between text-[10px] font-semibold uppercase tracking-wider text-white/30">
                            <span>
                              Execution Progress
                            </span>

                            <span>
                              {
                                assessment.progress
                              }
                              %
                            </span>
                          </div>

                          <div className="h-2 overflow-hidden rounded-full border border-white/[0.05] bg-black/40">
                            <div
                              className="h-full rounded-full bg-red-500 shadow-[0_0_14px_rgba(239,68,68,.5)] transition-all"
                              style={{
                                width: `${Math.min(
                                  Math.max(
                                    assessment.progress,
                                    0,
                                  ),
                                  100,
                                )}%`,
                              }}
                            />
                          </div>
                        </div>

                        <div className="mt-4 flex flex-wrap gap-x-5 gap-y-2 text-[10px] text-white/25">
                          <span>
                            Queued:{" "}
                            {formatDate(
                              assessment.queued_at,
                            )}
                          </span>

                          <span>
                            Started:{" "}
                            {formatDate(
                              assessment.started_at,
                            )}
                          </span>

                          <span>
                            Completed:{" "}
                            {formatDate(
                              assessment.completed_at,
                            )}
                          </span>
                        </div>

                        {assessment.error_message && (
                          <div className="mt-4 rounded-xl border border-red-500/15 bg-red-500/[0.05] px-4 py-3 text-xs leading-6 text-red-200/80">
                            {
                              assessment.error_message
                            }
                          </div>
                        )}

                        <div className="mt-3 break-all text-[10px] text-white/15">
                          Assessment ID:{" "}
                          {assessment.id}
                        </div>
                      </div>

                      <button
                        type="button"
                        onClick={() =>
                          setSelected(
                            assessment,
                          )
                        }
                        className="cx-button cx-button-secondary shrink-0 rounded-xl px-4 py-2 text-xs font-semibold"
                      >
                        Inspect
                      </button>
                    </div>
                  </article>
                ),
              )}
            </div>
          ) : (
            <div className="px-5 py-16 text-center">
              <div className="text-sm font-semibold text-white/50">
                No assessment jobs match
                these filters.
              </div>
            </div>
          )}
        </section>

        <section className="mt-6 grid gap-5 lg:grid-cols-2">
          <div className="rounded-2xl border border-white/[0.07] bg-[#09090b] p-6">
            <div className="text-[10px] font-bold uppercase tracking-[0.22em] text-red-400">
              Execution Lifecycle
            </div>

            <h2 className="mt-2 text-xl font-black">
              Queue → Worker → Result
            </h2>

            <p className="mt-3 text-sm leading-7 text-white/35">
              Assessments are queued, claimed
              by a worker and completed or
              failed. Authorization is checked
              again by the worker before
              scanning; revoked scope can
              therefore produce a blocked job.
            </p>
          </div>

          <div className="rounded-2xl border border-white/[0.07] bg-[#09090b] p-6">
            <div className="text-[10px] font-bold uppercase tracking-[0.22em] text-red-400">
              Control Surface
            </div>

            <h2 className="mt-2 text-xl font-black">
              Cancel / Retry Not Simulated
            </h2>

            <p className="mt-3 text-sm leading-7 text-white/35">
              The current worker engine has no
              cancellation token or safe
              interrupt protocol. Failed jobs
              also remain immutable evidence;
              a retry should create a new
              assessment rather than rewriting
              historical execution state.
            </p>
          </div>
        </section>
      </section>

      {selected && (
        <div className="fixed inset-0 z-[100] flex items-center justify-center bg-black/80 p-4 backdrop-blur-sm">
          <div className="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-3xl border border-red-500/15 bg-[#09090b] p-6 shadow-[0_30px_100px_rgba(0,0,0,.8)]">
            <div className="flex items-start justify-between gap-5">
              <div>
                <div className="text-[10px] font-bold uppercase tracking-[0.22em] text-red-400">
                  Execution Detail
                </div>

                <h2 className="mt-2 text-2xl font-black">
                  Assessment Inspector
                </h2>
              </div>

              <button
                type="button"
                onClick={() =>
                  setSelected(null)
                }
                className="text-xl text-white/35 transition hover:text-white"
              >
                ×
              </button>
            </div>

            <div className="mt-6 grid gap-3 sm:grid-cols-2">
              {[
                [
                  "Assessment ID",
                  selected.id,
                ],
                [
                  "Target",
                  selected.target?.name ??
                    "Unknown",
                ],
                [
                  "Hostname",
                  selected.target?.hostname ??
                    "Unknown",
                ],
                [
                  "Owner",
                  selected.owner?.name ??
                    "Unknown",
                ],
                [
                  "Owner Email",
                  selected.owner?.email ??
                    "Unknown",
                ],
                [
                  "Profile",
                  selected.profile,
                ],
                [
                  "Status",
                  selected.status,
                ],
                [
                  "Progress",
                  `${selected.progress}%`,
                ],
                [
                  "Worker",
                  selected.worker_id ??
                    "Not assigned",
                ],
                [
                  "Findings",
                  String(
                    selected.findings_count,
                  ),
                ],
                [
                  "Queued",
                  formatDate(
                    selected.queued_at,
                  ),
                ],
                [
                  "Started",
                  formatDate(
                    selected.started_at,
                  ),
                ],
                [
                  "Completed",
                  formatDate(
                    selected.completed_at,
                  ),
                ],
                [
                  "Duration",
                  durationLabel(selected),
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

                    <div className="mt-2 break-all text-sm font-semibold text-white/65">
                      {value}
                    </div>
                  </div>
                ),
              )}
            </div>

            {selected.error_message && (
              <div className="mt-5 rounded-xl border border-red-500/15 bg-red-500/[0.05] p-4">
                <div className="text-[9px] font-bold uppercase tracking-[0.16em] text-red-400">
                  Failure / Block Reason
                </div>

                <div className="mt-2 whitespace-pre-wrap break-words text-xs leading-6 text-red-200/80">
                  {
                    selected.error_message
                  }
                </div>
              </div>
            )}

            <div className="mt-6 flex justify-end">
              <button
                type="button"
                onClick={() =>
                  setSelected(null)
                }
                className="cx-button cx-button-primary rounded-xl px-5 py-3 text-sm font-semibold"
              >
                Close Inspector
              </button>
            </div>
          </div>
        </div>
      )}
    </main>
  );
}
