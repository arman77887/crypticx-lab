"use client";

import {
  useEffect,
  useMemo,
  useState,
} from "react";

import Link from "next/link";

import {
  AdminWorkersResponse,
  getAdminWorkers,
} from "@/lib/api";

type WorkerData =
  AdminWorkersResponse["data"];

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

function durationSince(
  value?: string | null,
): string {
  if (!value) return "—";

  const start = new Date(value).getTime();

  if (Number.isNaN(start)) {
    return "—";
  }

  const seconds = Math.max(
    0,
    Math.floor(
      (Date.now() - start) / 1000,
    ),
  );

  if (seconds < 60) {
    return `${seconds}s`;
  }

  const minutes = Math.floor(
    seconds / 60,
  );

  if (minutes < 60) {
    return `${minutes}m ${seconds % 60}s`;
  }

  const hours = Math.floor(
    minutes / 60,
  );

  return `${hours}h ${minutes % 60}m`;
}

export default function AdminWorkersPage() {
  const [data, setData] =
    useState<WorkerData | null>(null);

  const [loading, setLoading] =
    useState(true);

  const [error, setError] =
    useState("");

  const [query, setQuery] =
    useState("");

  async function load(
    silent = false,
  ) {
    try {
      if (!silent) {
        setLoading(true);
      }

      setError("");

      const response =
        await getAdminWorkers();

      setData(response.data);
    } catch (err) {
      setError(
        err instanceof Error
          ? err.message
          : "Unable to load worker telemetry.",
      );
    } finally {
      if (!silent) {
        setLoading(false);
      }
    }
  }

  useEffect(() => {
    void load();

    const timer =
      window.setInterval(() => {
        void load(true);
      }, 10000);

    return () =>
      window.clearInterval(timer);
  }, []);

  const running =
    useMemo(() => {
      const search =
        query.trim().toLowerCase();

      const rows =
        data?.running_executions ?? [];

      if (!search) return rows;

      return rows.filter((item) =>
        [
          item.assessment_id,
          item.worker_id ?? "",
          item.profile,
          item.owner?.name ?? "",
          item.owner?.email ?? "",
          item.target?.name ?? "",
          item.target?.url ?? "",
          item.target?.hostname ?? "",
        ].some((value) =>
          value
            .toLowerCase()
            .includes(search),
        ),
      );
    }, [data, query]);

  return (
    <main className="min-h-screen bg-[#050505] text-white">

      <section className="mx-auto max-w-7xl px-5 pb-24 pt-28 sm:px-8">
        <div className="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
          <div>
            <div className="mb-3 inline-flex items-center gap-2 rounded-full border border-red-500/15 bg-red-500/[0.04] px-4 py-2 text-[10px] font-bold uppercase tracking-[0.22em] text-red-400">
              <span className="h-1.5 w-1.5 rounded-full bg-red-500 shadow-[0_0_12px_rgba(239,68,68,.7)]" />
              Admin · Execution Infrastructure
            </div>

            <h1 className="text-4xl font-black tracking-tight sm:text-5xl">
              Queue & Worker Console
            </h1>

            <p className="mt-4 max-w-3xl text-sm leading-7 text-white/40">
              Observe queue depth,
              running scanner executions,
              queued assessments and failure
              records without simulating
              process health or unsupported
              worker controls.
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
                void load()
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
              data?.queue.driver ?? "—",
              "Queue Driver",
            ],
            [
              data?.queue.total_jobs ??
                "—",
              "Queue Jobs",
            ],
            [
              data?.scanner
                .running_assessments ??
                "—",
              "Running",
            ],
            [
              data?.scanner
                .queued_assessments ??
                "—",
              "Queued Assessments",
            ],
            [
              data?.failed_jobs.total ??
                "—",
              "Failed Queue Jobs",
            ],
          ].map(([value, label]) => (
            <div
              key={String(label)}
              className="rounded-2xl border border-white/[0.07] bg-[#09090b] p-5 shadow-[0_14px_40px_rgba(0,0,0,.35)]"
            >
              <div className="break-words text-2xl font-black">
                {loading ? "—" : value}
              </div>

              <div className="mt-3 text-[10px] font-bold uppercase tracking-[0.15em] text-white/35">
                {label}
              </div>
            </div>
          ))}
        </section>

        <section className="mt-5 grid gap-5 xl:grid-cols-3">
          <div className="rounded-2xl border border-white/[0.07] bg-[#09090b] p-6">
            <div className="text-[10px] font-bold uppercase tracking-[0.2em] text-red-400">
              Queue State
            </div>

            <div className="mt-5 space-y-3">
              {[
                [
                  "Connection",
                  data?.queue
                    .connection_status ??
                    "—",
                ],
                [
                  "Queue",
                  data?.queue
                    .queue_name ?? "—",
                ],
                [
                  "Ready",
                  data?.queue
                    .ready_jobs ?? "—",
                ],
                [
                  "Reserved",
                  data?.queue
                    .reserved_jobs ?? "—",
                ],
                [
                  "Delayed",
                  data?.queue
                    .delayed_jobs ?? "—",
                ],
                [
                  "Oldest Job",
                  formatDate(
                    data?.queue
                      .oldest_job_at,
                  ),
                ],
              ].map(([label, value]) => (
                <div
                  key={String(label)}
                  className="flex items-center justify-between gap-4 rounded-xl border border-white/[0.05] bg-black/20 px-4 py-3"
                >
                  <span className="text-xs text-white/30">
                    {label}
                  </span>

                  <span className="break-all text-right text-xs font-bold text-white/65">
                    {value}
                  </span>
                </div>
              ))}
            </div>

            <p className="mt-4 text-xs leading-6 text-white/25">
              {data?.queue.note ??
                "Queue telemetry unavailable."}
            </p>
          </div>

          <div className="rounded-2xl border border-white/[0.07] bg-[#09090b] p-6">
            <div className="text-[10px] font-bold uppercase tracking-[0.2em] text-red-400">
              Execution Observation
            </div>

            <div className="mt-5 space-y-3">
              {[
                [
                  "Observed IDs",
                  data?.scanner
                    .observed_execution_ids ??
                    "—",
                ],
                [
                  "Running Assessments",
                  data?.scanner
                    .running_assessments ??
                    "—",
                ],
                [
                  "Queued Assessments",
                  data?.scanner
                    .queued_assessments ??
                    "—",
                ],
                [
                  "Process Health",
                  "Not Observable",
                ],
              ].map(([label, value]) => (
                <div
                  key={String(label)}
                  className="flex items-center justify-between gap-4 rounded-xl border border-white/[0.05] bg-black/20 px-4 py-3"
                >
                  <span className="text-xs text-white/30">
                    {label}
                  </span>

                  <span className="text-right text-xs font-bold text-white/65">
                    {value}
                  </span>
                </div>
              ))}
            </div>

            <p className="mt-4 text-xs leading-6 text-white/25">
              {data?.scanner
                .worker_process_health_note ??
                "No process heartbeat registry is available."}
            </p>
          </div>

          <div className="rounded-2xl border border-white/[0.07] bg-[#09090b] p-6">
            <div className="text-[10px] font-bold uppercase tracking-[0.2em] text-red-400">
              Failed Job Store
            </div>

            <div className="mt-5 space-y-3">
              {[
                [
                  "Driver",
                  data?.failed_jobs
                    .driver ?? "—",
                ],
                [
                  "Observable",
                  data?.failed_jobs
                    .observable
                    ? "Yes"
                    : "No",
                ],
                [
                  "Recorded",
                  data?.failed_jobs
                    .total ?? "—",
                ],
                [
                  "Refresh",
                  "10 seconds",
                ],
              ].map(([label, value]) => (
                <div
                  key={String(label)}
                  className="flex items-center justify-between gap-4 rounded-xl border border-white/[0.05] bg-black/20 px-4 py-3"
                >
                  <span className="text-xs text-white/30">
                    {label}
                  </span>

                  <span className="text-right text-xs font-bold text-white/65">
                    {value}
                  </span>
                </div>
              ))}
            </div>

            <p className="mt-4 text-xs leading-6 text-white/25">
              Job payloads and serialized
              exceptions are intentionally not
              exposed through this console.
            </p>
          </div>
        </section>

        <section className="mt-5 rounded-2xl border border-white/[0.07] bg-[#09090b]">
          <div className="flex flex-col gap-4 border-b border-white/[0.06] px-5 py-5 lg:flex-row lg:items-center lg:justify-between">
            <div>
              <div className="text-[10px] font-bold uppercase tracking-[0.2em] text-red-400">
                Live Execution Registry
              </div>

              <h2 className="mt-1 text-lg font-black">
                Running Assessments
              </h2>
            </div>

            <input
              type="search"
              value={query}
              onChange={(event) =>
                setQuery(
                  event.target.value,
                )
              }
              placeholder="Search execution ID, target, owner..."
              className="cx-input w-full rounded-xl px-4 py-3 text-sm lg:max-w-md"
            />
          </div>

          {loading ? (
            <div className="p-5 text-sm text-white/35">
              Loading execution telemetry...
            </div>
          ) : running.length ? (
            <div className="divide-y divide-white/[0.05]">
              {running.map((item) => (
                <article
                  key={
                    item.assessment_id
                  }
                  className="p-5"
                >
                  <div className="flex flex-col gap-5 xl:flex-row xl:items-center xl:justify-between">
                    <div className="min-w-0 flex-1">
                      <div className="flex flex-wrap items-center gap-2">
                        <h3 className="font-bold text-white/85">
                          {item.target?.name ??
                            item.target
                              ?.hostname ??
                            "Unknown Target"}
                        </h3>

                        <span className="rounded-full border border-red-500/20 bg-red-500/[0.06] px-3 py-1 text-[10px] font-bold uppercase tracking-wider text-red-300">
                          Running
                        </span>

                        <span className="rounded-full border border-white/[0.08] bg-white/[0.03] px-3 py-1 text-[10px] font-bold uppercase tracking-wider text-white/45">
                          {item.profile}
                        </span>
                      </div>

                      <div className="mt-2 break-all text-xs text-white/35">
                        {item.target?.url ??
                          "—"}
                      </div>

                      <div className="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <div>
                          <div className="text-[9px] uppercase tracking-wider text-white/20">
                            Execution ID
                          </div>

                          <div className="mt-1 break-all text-xs font-semibold text-white/55">
                            {item.worker_id ??
                              "Not assigned"}
                          </div>
                        </div>

                        <div>
                          <div className="text-[9px] uppercase tracking-wider text-white/20">
                            Owner
                          </div>

                          <div className="mt-1 text-xs font-semibold text-white/55">
                            {item.owner
                              ?.name ??
                              "Unknown"}
                          </div>
                        </div>

                        <div>
                          <div className="text-[9px] uppercase tracking-wider text-white/20">
                            Running For
                          </div>

                          <div className="mt-1 text-xs font-semibold text-white/55">
                            {durationSince(
                              item.started_at,
                            )}
                          </div>
                        </div>

                        <div>
                          <div className="text-[9px] uppercase tracking-wider text-white/20">
                            Progress
                          </div>

                          <div className="mt-1 text-xs font-semibold text-white/55">
                            {item.progress}%
                          </div>
                        </div>
                      </div>

                      <div className="mt-4 h-1.5 overflow-hidden rounded-full bg-white/[0.05]">
                        <div
                          className="h-full rounded-full bg-red-500 transition-all"
                          style={{
                            width: `${Math.min(
                              100,
                              Math.max(
                                0,
                                item.progress,
                              ),
                            )}%`,
                          }}
                        />
                      </div>

                      <div className="mt-3 break-all text-[10px] text-white/15">
                        Assessment:{" "}
                        {item.assessment_id}
                      </div>
                    </div>
                  </div>
                </article>
              ))}
            </div>
          ) : (
            <div className="px-5 py-14 text-center text-sm font-semibold text-white/35">
              No running assessment
              executions are currently
              observed.
            </div>
          )}
        </section>

        <section className="mt-5 grid gap-5 xl:grid-cols-2">
          <div className="overflow-hidden rounded-2xl border border-white/[0.07] bg-[#09090b]">
            <div className="border-b border-white/[0.06] px-5 py-4">
              <div className="text-[10px] font-bold uppercase tracking-[0.2em] text-red-400">
                Assessment Queue
              </div>

              <h2 className="mt-1 font-black">
                Queued Assessments
              </h2>
            </div>

            {data?.queued_assessments
              .length ? (
              <div className="divide-y divide-white/[0.05]">
                {data.queued_assessments.map(
                  (item) => (
                    <div
                      key={
                        item.assessment_id
                      }
                      className="p-4"
                    >
                      <div className="font-semibold text-white/70">
                        {item.target
                          ?.name ??
                          "Unknown Target"}
                      </div>

                      <div className="mt-1 text-xs text-white/30">
                        {item.profile} ·{" "}
                        {formatDate(
                          item.queued_at,
                        )}
                      </div>

                      <div className="mt-2 break-all text-[10px] text-white/15">
                        {
                          item.assessment_id
                        }
                      </div>
                    </div>
                  ),
                )}
              </div>
            ) : (
              <div className="p-5 text-sm text-white/30">
                No queued assessments.
              </div>
            )}
          </div>

          <div className="overflow-hidden rounded-2xl border border-white/[0.07] bg-[#09090b]">
            <div className="border-b border-white/[0.06] px-5 py-4">
              <div className="text-[10px] font-bold uppercase tracking-[0.2em] text-red-400">
                Assessment Failures
              </div>

              <h2 className="mt-1 font-black">
                Failed / Blocked
              </h2>
            </div>

            {data?.recent_failed_assessments
              .length ? (
              <div className="divide-y divide-white/[0.05]">
                {data.recent_failed_assessments.map(
                  (item) => (
                    <div
                      key={
                        item.assessment_id
                      }
                      className="p-4"
                    >
                      <div className="flex items-center gap-2">
                        <div className="font-semibold text-white/70">
                          {item.target
                            ?.name ??
                            "Unknown Target"}
                        </div>

                        <span className="rounded-full border border-red-500/20 bg-red-500/[0.06] px-2 py-1 text-[9px] font-bold uppercase text-red-300">
                          {item.status}
                        </span>
                      </div>

                      <div className="mt-2 text-xs leading-6 text-white/35">
                        {item.error_message ||
                          "No error message recorded."}
                      </div>

                      <div className="mt-2 text-[10px] text-white/20">
                        {formatDate(
                          item.completed_at,
                        )}
                      </div>
                    </div>
                  ),
                )}
              </div>
            ) : (
              <div className="p-5 text-sm text-white/30">
                No recent failed or
                blocked assessments.
              </div>
            )}
          </div>
        </section>

        <section className="mt-5 rounded-2xl border border-red-500/15 bg-red-500/[0.025] p-6">
          <div className="text-[10px] font-bold uppercase tracking-[0.2em] text-red-400">
            Control Boundary
          </div>

          <h2 className="mt-2 text-xl font-black">
            Observation Only
          </h2>

          <p className="mt-3 max-w-4xl text-sm leading-7 text-white/35">
            CrypticX Lab currently has no
            durable worker heartbeat,
            capacity registry, drain protocol,
            process restart API or safe job
            cancellation mechanism. This
            console therefore reports only
            verifiable queue and assessment
            state and does not simulate those
            controls.
          </p>
        </section>
      </section>
    </main>
  );
}
