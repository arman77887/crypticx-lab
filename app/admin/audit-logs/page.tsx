"use client";

import {
  useEffect,
  useMemo,
  useState,
} from "react";

import Link from "next/link";

import {
  AdminActivityEvent,
  AdminAuditLog,
  getAdminTelemetry,
} from "@/lib/api";

type ViewMode = "audit" | "activity";

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

function methodClass(method?: string | null) {
  switch ((method ?? "").toUpperCase()) {
    case "DELETE":
      return "border-red-500/25 bg-red-500/[0.08] text-red-300";

    case "POST":
      return "border-amber-500/20 bg-amber-500/[0.06] text-amber-300";

    case "PATCH":
    case "PUT":
      return "border-blue-500/20 bg-blue-500/[0.06] text-blue-300";

    default:
      return "border-white/[0.08] bg-white/[0.03] text-white/45";
  }
}

export default function AdminAuditLogsPage() {
  const [auditLogs, setAuditLogs] =
    useState<AdminAuditLog[]>([]);

  const [activityEvents, setActivityEvents] =
    useState<AdminActivityEvent[]>([]);

  const [view, setView] =
    useState<ViewMode>("audit");

  const [query, setQuery] = useState("");
  const [category, setCategory] = useState("All");
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  const [selectedAudit, setSelectedAudit] =
    useState<AdminAuditLog | null>(null);

  const [selectedActivity, setSelectedActivity] =
    useState<AdminActivityEvent | null>(null);

  async function load(silent = false) {
    try {
      if (!silent) {
        setLoading(true);
      }

      setError("");

      const response =
        await getAdminTelemetry(200);

      setAuditLogs(
        response.data.audit_logs ?? [],
      );

      setActivityEvents(
        response.data.activity_events ?? [],
      );
    } catch (err) {
      setError(
        err instanceof Error
          ? err.message
          : "Unable to load telemetry.",
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
      }, 15000);

    return () =>
      window.clearInterval(timer);
  }, []);

  const categories = useMemo(() => {
    return Array.from(
      new Set(
        auditLogs
          .map((item) => item.category)
          .filter(
            (item): item is string =>
              Boolean(item),
          ),
      ),
    ).sort();
  }, [auditLogs]);

  const filteredAudit = useMemo(() => {
    const search =
      query.trim().toLowerCase();

    return auditLogs.filter((item) => {
      const matchesCategory =
        category === "All" ||
        item.category === category;

      if (!matchesCategory) {
        return false;
      }

      if (!search) {
        return true;
      }

      return [
        item.id,
        item.action,
        item.category ?? "",
        item.method ?? "",
        item.route ?? "",
        item.ip_address ?? "",
        item.resource_type ?? "",
        item.resource_id ?? "",
        item.user?.name ?? "",
        item.user?.email ?? "",
      ].some((value) =>
        value
          .toLowerCase()
          .includes(search),
      );
    });
  }, [auditLogs, query, category]);

  const filteredActivity = useMemo(() => {
    const search =
      query.trim().toLowerCase();

    if (!search) {
      return activityEvents;
    }

    return activityEvents.filter((item) =>
      [
        item.id,
        item.event_type,
        item.ip_address ?? "",
        item.country_code ?? "",
        item.country_name ?? "",
        item.region ?? "",
        item.city ?? "",
        item.device_type ?? "",
        item.browser ?? "",
        item.platform ?? "",
        item.user?.name ?? "",
        item.user?.email ?? "",
      ].some((value) =>
        value
          .toLowerCase()
          .includes(search),
      ),
    );
  }, [activityEvents, query]);

  const uniqueActors = useMemo(() => {
    return new Set(
      [
        ...auditLogs.map(
          (item) => item.user?.id,
        ),
        ...activityEvents.map(
          (item) => item.user?.id,
        ),
      ].filter(Boolean),
    ).size;
  }, [auditLogs, activityEvents]);

  const uniqueIps = useMemo(() => {
    return new Set(
      [
        ...auditLogs.map(
          (item) => item.ip_address,
        ),
        ...activityEvents.map(
          (item) => item.ip_address,
        ),
      ].filter(Boolean),
    ).size;
  }, [auditLogs, activityEvents]);

  return (
    <main className="min-h-screen bg-[#050505] text-white">

      <section className="mx-auto max-w-7xl px-5 pb-24 pt-28 sm:px-8">
        <div className="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
          <div>
            <div className="mb-3 inline-flex items-center gap-2 rounded-full border border-red-500/15 bg-red-500/[0.04] px-4 py-2 text-[10px] font-bold uppercase tracking-[0.22em] text-red-400">
              <span className="h-1.5 w-1.5 rounded-full bg-red-500 shadow-[0_0_12px_rgba(239,68,68,.7)]" />
              Admin · Security Telemetry
            </div>

            <h1 className="text-4xl font-black tracking-tight sm:text-5xl">
              Audit Intelligence
            </h1>

            <p className="mt-4 max-w-3xl text-sm leading-7 text-white/40">
              Review immutable administrative
              audit records and observed
              activity telemetry across the
              CrypticX Lab platform.
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
              onClick={() => void load()}
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

        <section className="mt-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          {[
            [
              auditLogs.length,
              "Audit Records",
            ],
            [
              activityEvents.length,
              "Activity Events",
            ],
            [
              uniqueActors,
              "Observed Actors",
            ],
            [
              uniqueIps,
              "Observed IPs",
            ],
          ].map(([value, label]) => (
            <div
              key={String(label)}
              className="rounded-2xl border border-white/[0.07] bg-[#09090b] p-5 shadow-[0_14px_40px_rgba(0,0,0,.35)]"
            >
              <div className="text-3xl font-black">
                {loading ? "—" : value}
              </div>

              <div className="mt-3 text-[10px] font-bold uppercase tracking-[0.15em] text-white/35">
                {label}
              </div>
            </div>
          ))}
        </section>

        <section className="mt-5 rounded-2xl border border-white/[0.07] bg-[#09090b] p-5">
          <div className="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
            <div className="flex gap-2">
              <button
                type="button"
                onClick={() => {
                  setView("audit");
                  setCategory("All");
                }}
                className={
                  view === "audit"
                    ? "cx-button cx-button-primary rounded-xl px-4 py-2 text-sm font-semibold"
                    : "cx-button cx-button-secondary rounded-xl px-4 py-2 text-sm font-semibold"
                }
              >
                Audit Logs
              </button>

              <button
                type="button"
                onClick={() => {
                  setView("activity");
                  setCategory("All");
                }}
                className={
                  view === "activity"
                    ? "cx-button cx-button-primary rounded-xl px-4 py-2 text-sm font-semibold"
                    : "cx-button cx-button-secondary rounded-xl px-4 py-2 text-sm font-semibold"
                }
              >
                Activity Events
              </button>
            </div>

            <input
              type="search"
              value={query}
              onChange={(event) =>
                setQuery(event.target.value)
              }
              placeholder={
                view === "audit"
                  ? "Search action, route, actor, resource, IP..."
                  : "Search event, actor, location, device, IP..."
              }
              className="cx-input w-full rounded-xl px-4 py-3 text-sm xl:max-w-xl"
            />
          </div>

          {view === "audit" &&
            categories.length > 0 && (
              <div className="mt-4 flex flex-wrap gap-2">
                <button
                  type="button"
                  onClick={() =>
                    setCategory("All")
                  }
                  className={
                    category === "All"
                      ? "cx-button cx-button-primary rounded-xl px-3 py-2 text-xs font-semibold"
                      : "cx-button cx-button-secondary rounded-xl px-3 py-2 text-xs font-semibold"
                  }
                >
                  All Categories
                </button>

                {categories.map((item) => (
                  <button
                    key={item}
                    type="button"
                    onClick={() =>
                      setCategory(item)
                    }
                    className={
                      category === item
                        ? "cx-button cx-button-primary rounded-xl px-3 py-2 text-xs font-semibold"
                        : "cx-button cx-button-secondary rounded-xl px-3 py-2 text-xs font-semibold"
                    }
                  >
                    {item}
                  </button>
                ))}
              </div>
            )}

          <div className="mt-4 text-xs text-white/20">
            Auto-refresh every 15 seconds ·
            read-only telemetry
          </div>
        </section>

        {view === "audit" ? (
          <section className="mt-5 overflow-hidden rounded-2xl border border-white/[0.07] bg-[#09090b]">
            <div className="flex items-center justify-between border-b border-white/[0.06] px-5 py-4">
              <div>
                <div className="text-[10px] font-bold uppercase tracking-[0.2em] text-red-400">
                  Administrative Trail
                </div>

                <h2 className="mt-1 font-black">
                  Audit Records
                </h2>
              </div>

              <span className="text-xs text-white/25">
                {filteredAudit.length} records
              </span>
            </div>

            {loading ? (
              <div className="p-5 text-sm text-white/35">
                Loading audit records...
              </div>
            ) : filteredAudit.length ? (
              <div className="divide-y divide-white/[0.05]">
                {filteredAudit.map((item) => (
                  <article
                    key={item.id}
                    className="p-5 transition hover:bg-red-500/[0.025]"
                  >
                    <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                      <div className="min-w-0 flex-1">
                        <div className="flex flex-wrap items-center gap-2">
                          <h3 className="font-bold text-white/85">
                            {item.action}
                          </h3>

                          {item.category && (
                            <span className="rounded-full border border-red-500/15 bg-red-500/[0.04] px-3 py-1 text-[10px] font-bold uppercase tracking-wider text-red-300">
                              {item.category}
                            </span>
                          )}

                          {item.method && (
                            <span
                              className={`rounded-full border px-3 py-1 text-[10px] font-bold uppercase ${methodClass(
                                item.method,
                              )}`}
                            >
                              {item.method}
                            </span>
                          )}
                        </div>

                        <div className="mt-3 flex flex-wrap gap-x-5 gap-y-2 text-xs text-white/30">
                          <span>
                            Actor:{" "}
                            <span className="text-white/55">
                              {item.user?.name ??
                                "System / Unknown"}
                            </span>
                          </span>

                          <span>
                            IP:{" "}
                            <span className="text-white/55">
                              {item.ip_address ??
                                "—"}
                            </span>
                          </span>

                          <span>
                            Resource:{" "}
                            <span className="text-white/55">
                              {item.resource_type ??
                                "—"}
                            </span>
                          </span>

                          <span>
                            {formatDate(
                              item.created_at,
                            )}
                          </span>
                        </div>

                        {item.route && (
                          <div className="mt-2 break-all text-[11px] text-white/20">
                            {item.route}
                          </div>
                        )}
                      </div>

                      <button
                        type="button"
                        onClick={() =>
                          setSelectedAudit(item)
                        }
                        className="cx-button cx-button-secondary shrink-0 rounded-xl px-4 py-2 text-xs font-semibold"
                      >
                        Inspect
                      </button>
                    </div>
                  </article>
                ))}
              </div>
            ) : (
              <div className="px-5 py-14 text-center text-sm text-white/35">
                No audit records match the
                current filters.
              </div>
            )}
          </section>
        ) : (
          <section className="mt-5 overflow-hidden rounded-2xl border border-white/[0.07] bg-[#09090b]">
            <div className="flex items-center justify-between border-b border-white/[0.06] px-5 py-4">
              <div>
                <div className="text-[10px] font-bold uppercase tracking-[0.2em] text-red-400">
                  Observed Activity
                </div>

                <h2 className="mt-1 font-black">
                  Activity Events
                </h2>
              </div>

              <span className="text-xs text-white/25">
                {filteredActivity.length} events
              </span>
            </div>

            {loading ? (
              <div className="p-5 text-sm text-white/35">
                Loading activity events...
              </div>
            ) : filteredActivity.length ? (
              <div className="divide-y divide-white/[0.05]">
                {filteredActivity.map((item) => (
                  <article
                    key={item.id}
                    className="p-5 transition hover:bg-red-500/[0.025]"
                  >
                    <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                      <div className="min-w-0 flex-1">
                        <h3 className="font-bold text-white/85">
                          {item.event_type}
                        </h3>

                        <div className="mt-3 flex flex-wrap gap-x-5 gap-y-2 text-xs text-white/30">
                          <span>
                            User:{" "}
                            <span className="text-white/55">
                              {item.user?.name ??
                                "Unknown"}
                            </span>
                          </span>

                          <span>
                            IP:{" "}
                            <span className="text-white/55">
                              {item.ip_address ??
                                "—"}
                            </span>
                          </span>

                          <span>
                            Location:{" "}
                            <span className="text-white/55">
                              {[
                                item.city,
                                item.region,
                                item.country_name,
                              ]
                                .filter(Boolean)
                                .join(", ") || "—"}
                            </span>
                          </span>

                          <span>
                            {formatDate(
                              item.created_at,
                            )}
                          </span>
                        </div>

                        <div className="mt-2 text-[11px] text-white/20">
                          {[
                            item.device_type,
                            item.browser,
                            item.platform,
                          ]
                            .filter(Boolean)
                            .join(" · ") || "Device unknown"}
                        </div>
                      </div>

                      <button
                        type="button"
                        onClick={() =>
                          setSelectedActivity(item)
                        }
                        className="cx-button cx-button-secondary shrink-0 rounded-xl px-4 py-2 text-xs font-semibold"
                      >
                        Inspect
                      </button>
                    </div>
                  </article>
                ))}
              </div>
            ) : (
              <div className="px-5 py-14 text-center text-sm text-white/35">
                No activity events match the
                current search.
              </div>
            )}
          </section>
        )}

        <section className="mt-5 rounded-2xl border border-red-500/15 bg-red-500/[0.025] p-6">
          <div className="text-[10px] font-bold uppercase tracking-[0.2em] text-red-400">
            Audit Integrity
          </div>

          <h2 className="mt-2 text-xl font-black">
            Read-only administrative view
          </h2>

          <p className="mt-3 max-w-4xl text-sm leading-7 text-white/35">
            This console does not expose
            edit, delete or clear-log actions.
            Audit and activity records are
            displayed as recorded security
            telemetry.
          </p>
        </section>
      </section>

      {selectedAudit && (
        <div className="fixed inset-0 z-[100] flex items-center justify-center bg-black/80 p-4 backdrop-blur-sm">
          <div className="max-h-[92vh] w-full max-w-3xl overflow-y-auto rounded-3xl border border-red-500/15 bg-[#09090b] p-6">
            <div className="flex items-start justify-between gap-4">
              <div>
                <div className="text-[10px] font-bold uppercase tracking-[0.2em] text-red-400">
                  Audit Record
                </div>

                <h2 className="mt-2 text-2xl font-black">
                  {selectedAudit.action}
                </h2>
              </div>

              <button
                type="button"
                onClick={() =>
                  setSelectedAudit(null)
                }
                className="text-xl text-white/35 hover:text-white"
              >
                ×
              </button>
            </div>

            <div className="mt-6 grid gap-3 sm:grid-cols-2">
              {[
                ["ID", selectedAudit.id],
                [
                  "Category",
                  selectedAudit.category ?? "—",
                ],
                [
                  "Method",
                  selectedAudit.method ?? "—",
                ],
                [
                  "Route",
                  selectedAudit.route ?? "—",
                ],
                [
                  "Actor",
                  selectedAudit.user?.name ??
                    "System / Unknown",
                ],
                [
                  "Actor Email",
                  selectedAudit.user?.email ??
                    "—",
                ],
                [
                  "IP Address",
                  selectedAudit.ip_address ??
                    "—",
                ],
                [
                  "Resource Type",
                  selectedAudit.resource_type ??
                    "—",
                ],
                [
                  "Resource ID",
                  selectedAudit.resource_id ??
                    "—",
                ],
                [
                  "Timestamp",
                  formatDate(
                    selectedAudit.created_at,
                  ),
                ],
              ].map(([label, value]) => (
                <div
                  key={label}
                  className="rounded-xl border border-white/[0.06] bg-black/25 p-4"
                >
                  <div className="text-[9px] font-bold uppercase tracking-wider text-white/25">
                    {label}
                  </div>

                  <div className="mt-2 break-all text-sm text-white/60">
                    {value}
                  </div>
                </div>
              ))}
            </div>

            <div className="mt-4 rounded-xl border border-white/[0.06] bg-black/25 p-4">
              <div className="text-[9px] font-bold uppercase tracking-wider text-white/25">
                User Agent
              </div>

              <div className="mt-2 break-all text-xs leading-6 text-white/50">
                {selectedAudit.user_agent ??
                  "—"}
              </div>
            </div>

            <div className="mt-4 rounded-xl border border-white/[0.06] bg-black/25 p-4">
              <div className="text-[9px] font-bold uppercase tracking-wider text-white/25">
                Metadata
              </div>

              <pre className="mt-3 overflow-x-auto whitespace-pre-wrap break-words text-xs leading-6 text-white/45">
                {JSON.stringify(
                  selectedAudit.metadata ??
                    {},
                  null,
                  2,
                )}
              </pre>
            </div>
          </div>
        </div>
      )}

      {selectedActivity && (
        <div className="fixed inset-0 z-[100] flex items-center justify-center bg-black/80 p-4 backdrop-blur-sm">
          <div className="max-h-[92vh] w-full max-w-3xl overflow-y-auto rounded-3xl border border-red-500/15 bg-[#09090b] p-6">
            <div className="flex items-start justify-between gap-4">
              <div>
                <div className="text-[10px] font-bold uppercase tracking-[0.2em] text-red-400">
                  Activity Event
                </div>

                <h2 className="mt-2 text-2xl font-black">
                  {selectedActivity.event_type}
                </h2>
              </div>

              <button
                type="button"
                onClick={() =>
                  setSelectedActivity(null)
                }
                className="text-xl text-white/35 hover:text-white"
              >
                ×
              </button>
            </div>

            <div className="mt-6 grid gap-3 sm:grid-cols-2">
              {[
                ["ID", selectedActivity.id],
                [
                  "User",
                  selectedActivity.user?.name ??
                    "Unknown",
                ],
                [
                  "Email",
                  selectedActivity.user?.email ??
                    "—",
                ],
                [
                  "IP Address",
                  selectedActivity.ip_address ??
                    "—",
                ],
                [
                  "Country",
                  selectedActivity.country_name ??
                    "—",
                ],
                [
                  "Region",
                  selectedActivity.region ?? "—",
                ],
                [
                  "City",
                  selectedActivity.city ?? "—",
                ],
                [
                  "Device",
                  selectedActivity.device_type ??
                    "—",
                ],
                [
                  "Browser",
                  selectedActivity.browser ?? "—",
                ],
                [
                  "Platform",
                  selectedActivity.platform ?? "—",
                ],
                [
                  "Timestamp",
                  formatDate(
                    selectedActivity.created_at,
                  ),
                ],
              ].map(([label, value]) => (
                <div
                  key={label}
                  className="rounded-xl border border-white/[0.06] bg-black/25 p-4"
                >
                  <div className="text-[9px] font-bold uppercase tracking-wider text-white/25">
                    {label}
                  </div>

                  <div className="mt-2 break-all text-sm text-white/60">
                    {value}
                  </div>
                </div>
              ))}
            </div>

            <div className="mt-4 rounded-xl border border-white/[0.06] bg-black/25 p-4">
              <div className="text-[9px] font-bold uppercase tracking-wider text-white/25">
                Metadata
              </div>

              <pre className="mt-3 overflow-x-auto whitespace-pre-wrap break-words text-xs leading-6 text-white/45">
                {JSON.stringify(
                  selectedActivity.metadata ??
                    {},
                  null,
                  2,
                )}
              </pre>
            </div>
          </div>
        </div>
      )}
    </main>
  );
}
