"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import {
  AdminMonitoringDelivery,
  AdminMonitoringEvent,
  AdminMonitoringPolicyItem,
  AdminMonitoringSummary,
  getAdminMonitoring,
  getAdminMonitoringDeliveries,
  getAdminMonitoringEvents,
} from "@/lib/api";

function formatDate(value?: string | null) {
  if (!value) return "Not available";

  const date = new Date(value);

  if (Number.isNaN(date.getTime())) {
    return value;
  }

  return date.toLocaleString();
}

function formatInterval(minutes: number) {
  if (minutes < 60) {
    return `Every ${minutes} min`;
  }

  if (minutes % 1440 === 0) {
    const days = minutes / 1440;
    return `Every ${days} day${days === 1 ? "" : "s"}`;
  }

  if (minutes % 60 === 0) {
    const hours = minutes / 60;
    return `Every ${hours} hour${hours === 1 ? "" : "s"}`;
  }

  return `Every ${minutes} min`;
}

function labelEventType(value: string) {
  return value
    .split("_")
    .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
    .join(" ");
}

function statusClass(status?: string | null) {
  switch ((status || "").toLowerCase()) {
    case "active":
    case "completed":
    case "sent":
      return "border-emerald-500/20 bg-emerald-500/10 text-emerald-300";

    case "pending":
    case "processing":
    case "queued":
    case "running":
      return "border-amber-500/20 bg-amber-500/10 text-amber-300";

    case "failed":
    case "blocked":
      return "border-red-500/20 bg-red-500/10 text-red-300";

    default:
      return "border-white/[0.08] bg-white/[0.03] text-white/45";
  }
}

function Metric({
  label,
  value,
  detail,
}: {
  label: string;
  value: number | string;
  detail: string;
}) {
  return (
    <div className="rounded-2xl border border-white/[0.07] bg-[#09090b] p-5 shadow-[0_15px_40px_rgba(0,0,0,.35)]">
      <div className="text-[10px] font-bold uppercase tracking-[0.2em] text-white/35">
        {label}
      </div>

      <div className="mt-3 text-3xl font-bold tracking-tight text-white">
        {value}
      </div>

      <div className="mt-2 text-xs text-white/30">{detail}</div>
    </div>
  );
}

export default function AdminMonitoringPage() {
  const [summary, setSummary] = useState<AdminMonitoringSummary | null>(null);
  const [policies, setPolicies] = useState<AdminMonitoringPolicyItem[]>([]);
  const [events, setEvents] = useState<AdminMonitoringEvent[]>([]);
  const [deliveries, setDeliveries] = useState<AdminMonitoringDelivery[]>([]);
  const [generatedAt, setGeneratedAt] = useState<string | null>(null);

  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  const load = useCallback(async () => {
    try {
      setLoading(true);
      setError("");

      const [monitoring, eventResponse, deliveryResponse] =
        await Promise.all([
          getAdminMonitoring(),
          getAdminMonitoringEvents(),
          getAdminMonitoringDeliveries(),
        ]);

      setSummary(monitoring.data.summary);
      setPolicies(monitoring.data.policies);
      setEvents(eventResponse.data.events);
      setDeliveries(deliveryResponse.data.deliveries);
      setGeneratedAt(monitoring.data.generated_at);
    } catch (err) {
      setError(
        err instanceof Error
          ? err.message
          : "Unable to load platform monitoring data.",
      );
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  const nextScheduled = useMemo(() => {
    const values = policies
      .filter((policy) => policy.enabled && policy.next_run_at)
      .map((policy) => new Date(policy.next_run_at as string))
      .filter((date) => !Number.isNaN(date.getTime()))
      .sort((a, b) => a.getTime() - b.getTime());

    return values[0] ?? null;
  }, [policies]);

  return (
    <main className="min-h-screen bg-[#050505] px-4 py-6 text-white sm:px-6 lg:px-8 xl:px-10">
      <section className="flex flex-col gap-5 border-b border-white/[0.06] pb-6 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <div className="flex items-center gap-3">
            <span className="h-2 w-2 rounded-full bg-red-500 shadow-[0_0_14px_rgba(239,68,68,.7)]" />
            <span className="text-[10px] font-bold uppercase tracking-[0.28em] text-red-400/70">
              Security Operations
            </span>
          </div>

          <h1 className="mt-3 text-3xl font-bold tracking-tight">
            Platform Monitoring
          </h1>

          <p className="mt-2 max-w-2xl text-sm leading-6 text-white/35">
            Real scheduled monitoring policies, persisted change events and
            notification delivery state across the platform.
          </p>
        </div>

        <button
          type="button"
          onClick={() => void load()}
          disabled={loading}
          className="rounded-xl border border-white/[0.08] bg-white/[0.04] px-5 py-3 text-xs font-semibold text-white/70 transition hover:bg-white/[0.07] disabled:opacity-40"
        >
          {loading ? "Refreshing..." : "Refresh"}
        </button>
      </section>

      {error && (
        <div className="mt-5 rounded-2xl border border-red-500/20 bg-red-500/[0.07] p-4 text-sm text-red-200">
          {error}
        </div>
      )}

      <section className="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <Metric
          label="Monitoring Policies"
          value={loading ? "—" : summary?.total_policies ?? 0}
          detail={`${summary?.active_policies ?? 0} active · ${summary?.disabled_policies ?? 0} disabled`}
        />

        <Metric
          label="Due Policies"
          value={loading ? "—" : summary?.due_policies ?? 0}
          detail={
            nextScheduled
              ? `Next ${formatDate(nextScheduled.toISOString())}`
              : "No scheduled run available"
          }
        />

        <Metric
          label="Change Events"
          value={loading ? "—" : summary?.change_events ?? 0}
          detail="Persisted monitoring changes"
        />

        <Metric
          label="Delivery Failures"
          value={loading ? "—" : summary?.failed_deliveries ?? 0}
          detail={`${summary?.pending_deliveries ?? 0} pending · ${summary?.sent_deliveries ?? 0} sent`}
        />
      </section>

      <section className="mt-6 rounded-2xl border border-white/[0.07] bg-[#09090b]">
        <div className="border-b border-white/[0.06] px-5 py-4">
          <div className="text-[10px] font-bold uppercase tracking-[0.2em] text-red-400/70">
            Scheduler
          </div>
          <h2 className="mt-1 text-lg font-bold">Monitoring Policies</h2>
        </div>

        <div className="overflow-x-auto">
          <table className="min-w-full text-left text-sm">
            <thead className="border-b border-white/[0.06] text-[10px] uppercase tracking-wider text-white/30">
              <tr>
                <th className="px-5 py-3">Target</th>
                <th className="px-5 py-3">Owner</th>
                <th className="px-5 py-3">State</th>
                <th className="px-5 py-3">Profile</th>
                <th className="px-5 py-3">Interval</th>
                <th className="px-5 py-3">Last Assessment</th>
                <th className="px-5 py-3">Next Run</th>
              </tr>
            </thead>

            <tbody className="divide-y divide-white/[0.05]">
              {policies.map((policy) => (
                <tr key={policy.id} className="text-white/55">
                  <td className="px-5 py-4">
                    <div className="font-semibold text-white/80">
                      {policy.target?.name ?? "Unavailable target"}
                    </div>
                    <div className="mt-1 max-w-xs truncate text-xs text-white/30">
                      {policy.target?.hostname ?? policy.target?.url ?? "—"}
                    </div>
                  </td>

                  <td className="px-5 py-4">
                    <div>{policy.owner?.name ?? "Unknown"}</div>
                    <div className="mt-1 text-xs text-white/30">
                      {policy.owner?.email ?? "—"}
                    </div>
                  </td>

                  <td className="px-5 py-4">
                    <span
                      className={`inline-flex rounded-lg border px-2.5 py-1 text-xs font-semibold ${
                        policy.enabled
                          ? statusClass("active")
                          : statusClass("disabled")
                      }`}
                    >
                      {policy.enabled ? "Active" : "Disabled"}
                    </span>
                  </td>

                  <td className="px-5 py-4 capitalize">{policy.profile}</td>
                  <td className="px-5 py-4">
                    {formatInterval(policy.interval_minutes)}
                  </td>

                  <td className="px-5 py-4">
                    {policy.last_assessment ? (
                      <>
                        <span
                          className={`inline-flex rounded-lg border px-2 py-1 text-xs ${statusClass(
                            policy.last_assessment.status,
                          )}`}
                        >
                          {policy.last_assessment.status}
                        </span>
                        <div className="mt-2 text-xs text-white/30">
                          {formatDate(policy.last_assessment.completed_at)}
                        </div>
                      </>
                    ) : (
                      "No assessment"
                    )}
                  </td>

                  <td className="px-5 py-4 text-xs">
                    {formatDate(policy.next_run_at)}
                  </td>
                </tr>
              ))}

              {!loading && policies.length === 0 && (
                <tr>
                  <td
                    colSpan={7}
                    className="px-5 py-10 text-center text-sm text-white/30"
                  >
                    No persisted monitoring policies.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </section>

      <section className="mt-6 grid gap-6 2xl:grid-cols-2">
        <div className="rounded-2xl border border-white/[0.07] bg-[#09090b]">
          <div className="border-b border-white/[0.06] px-5 py-4">
            <div className="text-[10px] font-bold uppercase tracking-[0.2em] text-red-400/70">
              Detection
            </div>
            <h2 className="mt-1 text-lg font-bold">Recent Change Events</h2>
          </div>

          <div className="divide-y divide-white/[0.05]">
            {events.slice(0, 20).map((event) => (
              <div key={event.id} className="p-5">
                <div className="flex flex-wrap items-center justify-between gap-3">
                  <span className="rounded-lg border border-white/[0.08] bg-white/[0.03] px-2.5 py-1 text-xs font-semibold text-white/70">
                    {labelEventType(event.event_type)}
                  </span>

                  <span className="text-xs text-white/25">
                    {formatDate(event.detected_at)}
                  </span>
                </div>

                <div className="mt-3 text-sm font-semibold text-white/75">
                  {event.target?.name ?? "Unavailable target"}
                </div>

                <div className="mt-1 text-xs text-white/30">
                  {event.owner?.email ?? "Unknown owner"}
                </div>
              </div>
            ))}

            {!loading && events.length === 0 && (
              <div className="p-8 text-center text-sm text-white/30">
                No real monitoring change events have been detected yet.
              </div>
            )}
          </div>
        </div>

        <div className="rounded-2xl border border-white/[0.07] bg-[#09090b]">
          <div className="border-b border-white/[0.06] px-5 py-4">
            <div className="text-[10px] font-bold uppercase tracking-[0.2em] text-red-400/70">
              Notifications
            </div>
            <h2 className="mt-1 text-lg font-bold">Recent Deliveries</h2>
          </div>

          <div className="divide-y divide-white/[0.05]">
            {deliveries.slice(0, 20).map((delivery) => (
              <div key={delivery.id} className="p-5">
                <div className="flex flex-wrap items-center justify-between gap-3">
                  <span
                    className={`rounded-lg border px-2.5 py-1 text-xs font-semibold ${statusClass(
                      delivery.status,
                    )}`}
                  >
                    {delivery.status}
                  </span>

                  <span className="text-xs text-white/25">
                    {formatDate(delivery.created_at)}
                  </span>
                </div>

                <div className="mt-3 text-sm font-semibold text-white/75">
                  {delivery.event
                    ? labelEventType(delivery.event.event_type)
                    : "Unavailable event"}
                </div>

                <div className="mt-1 text-xs text-white/30">
                  {delivery.recipient} · {delivery.channel}
                </div>

                <div className="mt-2 text-xs text-white/25">
                  Attempts: {delivery.attempt_count}
                  {delivery.failure_class
                    ? ` · ${delivery.failure_class}`
                    : ""}
                </div>
              </div>
            ))}

            {!loading && deliveries.length === 0 && (
              <div className="p-8 text-center text-sm text-white/30">
                No notification deliveries have been created yet.
              </div>
            )}
          </div>
        </div>
      </section>

      <div className="mt-6 text-xs text-white/25">
        {generatedAt
          ? `Backend snapshot generated ${formatDate(generatedAt)}`
          : "Waiting for backend monitoring snapshot."}
      </div>
    </main>
  );
}
