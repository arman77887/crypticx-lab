"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import dynamic from "next/dynamic";

const WorldActivityMap = dynamic(
  () => import("./components/WorldActivityMap"),
  { ssr: false },
);
import {
  getAdminDashboard,
  getAdminTelemetry,
  type AdminDashboardResponse,
  type AdminTelemetryResponse,
} from "@/lib/api";

type DashboardData = AdminDashboardResponse["data"];

function MetricCard({
  label,
  value,
  detail,
}: {
  label: string;
  value: string | number;
  detail: string;
}) {
  return (
    <div className="group rounded-2xl border border-white/[0.07] bg-[#0a0a0c] p-5 shadow-[0_12px_35px_rgba(0,0,0,0.35)] transition-all hover:border-white/[0.13]">
      <div className="flex items-start justify-between gap-3">
        <div>
          <div className="text-[10px] font-semibold uppercase tracking-[0.2em] text-white/35">
            {label}
          </div>

          <div className="mt-3 text-3xl font-bold tracking-tight text-white">
            {value}
          </div>
        </div>

        <span className="mt-1 h-2 w-2 rounded-full bg-red-500/60 shadow-[0_0_10px_rgba(239,68,68,.35)]" />
      </div>

      <div className="mt-3 text-xs text-white/35">{detail}</div>
    </div>
  );
}

function StatusRow({
  label,
  status,
}: {
  label: string;
  status: string;
}) {
  const normalized = status.toLowerCase();

  const indicator =
    status === "Operational" ||
    normalized.includes("active worker") ||
    normalized.includes("online")
      ? "bg-emerald-400"
      : normalized.includes("unavailable") ||
          normalized.includes("failed") ||
          normalized.includes("offline")
        ? "bg-red-400"
        : "bg-amber-400";

  return (
    <div className="flex items-center justify-between rounded-xl border border-white/[0.06] bg-black/20 px-4 py-3">
      <span className="text-sm font-medium text-white/70">{label}</span>

      <span className="flex items-center gap-2 text-xs font-semibold text-white/55">
        <span className={`h-1.5 w-1.5 rounded-full ${indicator}`} />

        {status}
      </span>
    </div>
  );
}

export default function AdminDashboardPage() {
  const [dashboard, setDashboard] = useState<DashboardData | null>(null);
  const [telemetry, setTelemetry] =
    useState<AdminTelemetryResponse["data"] | null>(null);
  const [loading, setLoading] = useState(true);
  const [telemetryLoading, setTelemetryLoading] = useState(true);
  const [error, setError] = useState("");

  async function loadDashboard() {
    try {
      setLoading(true);
      setError("");

      const [response, telemetryResponse] = await Promise.all([
        getAdminDashboard(),
        getAdminTelemetry(),
      ]);

      if (!response.success) {
        throw new Error("Dashboard API returned an unsuccessful response.");
      }

      if (!telemetryResponse.success) {
        throw new Error("Telemetry API returned an unsuccessful response.");
      }

      setDashboard(response.data);
      setTelemetry(telemetryResponse.data);
    } catch (err) {
      setError(
        err instanceof Error
          ? err.message
          : "Unable to load dashboard data.",
      );
    } finally {
      setLoading(false);
      setTelemetryLoading(false);
    }
  }

  useEffect(() => {
    loadDashboard();
  }, []);

  const systemItems = dashboard
    ? [
        ["API", dashboard.system.api],
        ["Database", dashboard.system.database],
        ["Queue", dashboard.system.queue],
        ["Scanner Workers", dashboard.system.scanner_workers],
      ]
    : [];

  return (
    <main className="min-h-screen bg-[#050505] text-white">
      {/* Header */}
      <header className="border-b border-white/[0.06] bg-[#070708]">
        <div className="flex flex-col gap-4 px-4 py-5 sm:px-6 lg:px-8 xl:px-10">
          <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <div className="flex items-center gap-3">
                <span className="h-2 w-2 rounded-full bg-red-500 shadow-[0_0_14px_rgba(239,68,68,.65)]" />

                <span className="text-[10px] font-bold uppercase tracking-[0.28em] text-white/40">
                  CrypticX Lab · Security Operations
                </span>
              </div>

              <h1 className="mt-3 text-2xl font-bold tracking-tight sm:text-3xl">
                Command Center
              </h1>

              <p className="mt-1 text-sm text-white/35">
                Authorized platform security and assessment operations.
              </p>
            </div>

            <div className="flex items-center gap-3">
              <button
                type="button"
                onClick={loadDashboard}
                disabled={loading}
                className="rounded-xl border border-white/[0.08] bg-white/[0.03] px-4 py-2.5 text-xs font-semibold text-white/65 transition hover:bg-white/[0.06] disabled:opacity-40"
              >
                {loading ? "Syncing..." : "Refresh"}
              </button>

              <div className="rounded-xl border border-white/[0.08] bg-white/[0.03] px-4 py-2.5">
                <div className="text-[9px] uppercase tracking-[0.18em] text-white/30">
                  Session
                </div>

                <div className="mt-1 text-xs font-semibold text-white/70">
                  Secure · Admin
                </div>
              </div>
            </div>
          </div>

          {error && (
            <div className="rounded-xl border border-white/[0.08] bg-white/[0.025] px-4 py-3 text-sm">
              <span className="font-semibold text-white/75">
                Dashboard unavailable:
              </span>{" "}
              <span className="text-white/40">{error}</span>
            </div>
          )}
        </div>
      </header>

      <div className="px-4 py-5 sm:px-6 lg:px-8 xl:px-10">
        {/* KPI */}
        <section className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
          <MetricCard
            label="Total Users"
            value={loading ? "—" : dashboard?.users.total ?? 0}
            detail={
              dashboard
                ? `${dashboard.users.verified} verified · ${dashboard.users.pending} pending`
                : "Loading backend data"
            }
          />

          <MetricCard
            label="Active Targets"
            value={loading ? "—" : dashboard?.targets.active ?? 0}
            detail={
              dashboard
                ? `${dashboard.targets.total} total authorized targets`
                : "Target inventory"
            }
          />

          <MetricCard
            label="Assessments"
            value={loading ? "—" : dashboard?.assessments.total ?? 0}
            detail={
              dashboard
                ? `${dashboard.assessments.running} running · ${dashboard.assessments.completed} completed`
                : "Assessment telemetry"
            }
          />

          <MetricCard
            label="Current Findings"
            value={loading ? "—" : dashboard?.findings.current ?? 0}
            detail={
              dashboard
                ? `${dashboard.findings.critical} critical · ${dashboard.findings.high} high`
                : "Current security findings"
            }
          />
        </section>

        {/* Main SOC Grid */}
        <section className="mt-4 grid gap-4 xl:grid-cols-[minmax(0,1fr)_340px]">
          {/* World Map */}
          <div className="overflow-hidden rounded-2xl border border-white/[0.07] bg-[#09090b]">
            <div className="flex flex-col gap-3 border-b border-white/[0.06] px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
              <div>
                <div className="text-[10px] font-bold uppercase tracking-[0.22em] text-red-400/75">
                  Global Intelligence
                </div>

                <h2 className="mt-1 text-base font-bold text-white/90">
                  Global Activity Map
                </h2>
              </div>

              <div className="flex items-center gap-4 text-[10px] uppercase tracking-wider text-white/30">
                <span className="flex items-center gap-2">
                  <span className="h-1.5 w-1.5 rounded-full bg-red-400" />
                  Authorized
                </span>

                <span className="flex items-center gap-2">
                  <span className="h-1.5 w-1.5 rounded-full bg-white/20" />
                  No events
                </span>
              </div>
            </div>

            <WorldActivityMap />
          </div>

          {/* System Health */}
          <div className="rounded-2xl border border-white/[0.07] bg-[#09090b] p-5">
            <div className="text-[10px] font-bold uppercase tracking-[0.22em] text-red-400/75">
              Infrastructure
            </div>

            <h2 className="mt-1 text-base font-bold text-white/90">
              System Health
            </h2>

            <div className="mt-5 space-y-2">
              {loading ? (
                <>
                  {["API", "Database", "Queue", "Scanner Workers"].map(
                    (item) => (
                      <StatusRow
                        key={item}
                        label={item}
                        status="Checking..."
                      />
                    ),
                  )}
                </>
              ) : (
                systemItems.map(([label, status]) => (
                  <StatusRow
                    key={label}
                    label={label}
                    status={status}
                  />
                ))
              )}
            </div>

            <div className="mt-5 border-t border-white/[0.06] pt-5">
              <div className="text-[10px] uppercase tracking-[0.18em] text-white/30">
                Assessment State
              </div>

              <div className="mt-3 grid grid-cols-2 gap-2">
                {[
                  ["Queued", dashboard?.assessments.queued ?? 0],
                  ["Running", dashboard?.assessments.running ?? 0],
                  ["Completed", dashboard?.assessments.completed ?? 0],
                  ["Failed", dashboard?.assessments.failed ?? 0],
                ].map(([label, value]) => (
                  <div
                    key={label}
                    className="rounded-xl border border-white/[0.06] bg-black/20 p-3"
                  >
                    <div className="text-xl font-bold">
                      {loading ? "—" : value}
                    </div>

                    <div className="mt-1 text-[10px] uppercase tracking-wider text-white/30">
                      {label}
                    </div>
                  </div>
                ))}
              </div>
            </div>
          </div>
        </section>

        {/* Intelligence Panels */}
        <section className="mt-4 grid gap-4 lg:grid-cols-3">
          {/* Findings */}
          <div className="rounded-2xl border border-white/[0.07] bg-[#09090b] p-5">
            <div className="text-[10px] font-bold uppercase tracking-[0.22em] text-red-400/75">
              Threat Intelligence
            </div>

            <h2 className="mt-1 text-base font-bold">
              Vulnerabilities
            </h2>

            <div className="mt-5 grid grid-cols-2 gap-2">
              <MetricCard
                label="Critical"
                value={loading ? "—" : dashboard?.findings.critical ?? 0}
                detail="Critical severity"
              />

              <MetricCard
                label="High"
                value={loading ? "—" : dashboard?.findings.high ?? 0}
                detail="High severity"
              />
            </div>

            <Link
              href="/admin/findings"
              className="mt-4 block rounded-xl border border-white/[0.07] px-4 py-3 text-center text-xs font-semibold text-white/55 transition hover:bg-white/[0.04] hover:text-white"
            >
              Open Findings →
            </Link>
          </div>

          {/* Assessments */}
          <div className="rounded-2xl border border-white/[0.07] bg-[#09090b] p-5">
            <div className="text-[10px] font-bold uppercase tracking-[0.22em] text-red-400/75">
              Operations
            </div>

            <h2 className="mt-1 text-base font-bold">
              Assessment Pipeline
            </h2>

            <div className="mt-5 space-y-2">
              {[
                ["Queued", dashboard?.assessments.queued ?? 0],
                ["Running", dashboard?.assessments.running ?? 0],
                ["Completed", dashboard?.assessments.completed ?? 0],
                ["Failed", dashboard?.assessments.failed ?? 0],
              ].map(([label, value]) => (
                <div
                  key={label}
                  className="flex items-center justify-between rounded-xl border border-white/[0.06] bg-black/20 px-4 py-3"
                >
                  <span className="text-xs text-white/45">
                    {label}
                  </span>

                  <span className="text-sm font-bold text-white/80">
                    {loading ? "—" : value}
                  </span>
                </div>
              ))}
            </div>

            <Link
              href="/admin/scans"
              className="mt-4 block rounded-xl border border-white/[0.07] px-4 py-3 text-center text-xs font-semibold text-white/55 transition hover:bg-white/[0.04] hover:text-white"
            >
              Open Assessments →
            </Link>
          </div>

          {/* Users */}
          <div className="rounded-2xl border border-white/[0.07] bg-[#09090b] p-5">
            <div className="text-[10px] font-bold uppercase tracking-[0.22em] text-red-400/75">
              Identity
            </div>

            <h2 className="mt-1 text-base font-bold">
              User Security
            </h2>

            <div className="mt-5 space-y-2">
              {[
                [
                  "Total Users",
                  dashboard?.users.total ?? 0,
                ],
                [
                  "Verified",
                  dashboard?.users.verified ?? 0,
                ],
                [
                  "Pending",
                  dashboard?.users.pending ?? 0,
                ],
              ].map(([label, value]) => (
                <div
                  key={label}
                  className="flex items-center justify-between rounded-xl border border-white/[0.06] bg-black/20 px-4 py-3"
                >
                  <span className="text-xs text-white/45">
                    {label}
                  </span>

                  <span className="text-sm font-bold text-white/80">
                    {loading ? "—" : value}
                  </span>
                </div>
              ))}
            </div>

            <Link
              href="/admin/users"
              className="mt-4 block rounded-xl border border-white/[0.07] px-4 py-3 text-center text-xs font-semibold text-white/55 transition hover:bg-white/[0.04] hover:text-white"
            >
              User Management →
            </Link>
          </div>
        </section>

        {/* Platform Risk Intelligence */}
        <section className="mt-4 grid gap-4 xl:grid-cols-[360px_minmax(0,1fr)]">
          <div className="rounded-2xl border border-red-500/15 bg-[#09090b] p-5 shadow-[0_16px_50px_rgba(127,29,29,0.10)]">
            <div className="text-[10px] font-bold uppercase tracking-[0.22em] text-red-400/75">
              Platform Risk
            </div>

            <div className="mt-4 flex items-end gap-2">
              <div className="text-5xl font-black tracking-tight text-white">
                {loading ? "—" : dashboard?.overall_risk.score ?? 0}
              </div>

              <div className="pb-1 text-sm font-semibold text-white/35">
                /100
              </div>
            </div>

            <div className="mt-2 text-xs font-semibold uppercase tracking-[0.18em] text-red-400">
              {loading
                ? "Calculating"
                : dashboard?.overall_risk.level ?? "informational"}
            </div>

            <div className="mt-5 grid grid-cols-2 gap-2">
              <div className="rounded-xl border border-white/[0.06] bg-black/20 p-3">
                <div className="text-xl font-bold">
                  {loading ? "—" : dashboard?.overall_risk.highest ?? 0}
                </div>
                <div className="mt-1 text-[10px] uppercase tracking-wider text-white/30">
                  Highest
                </div>
              </div>

              <div className="rounded-xl border border-white/[0.06] bg-black/20 p-3">
                <div className="text-xl font-bold">
                  {loading ? "—" : dashboard?.overall_risk.average ?? 0}
                </div>
                <div className="mt-1 text-[10px] uppercase tracking-wider text-white/30">
                  Average
                </div>
              </div>
            </div>

            <div className="mt-4 text-[11px] leading-5 text-white/30">
              Aggregate prioritization score from current finding lifecycle
              risk. It is not a probability of compromise.
            </div>
          </div>

          <div className="grid gap-4 lg:grid-cols-2">
            <div className="rounded-2xl border border-white/[0.07] bg-[#09090b] p-5">
              <div className="text-[10px] font-bold uppercase tracking-[0.22em] text-red-400/75">
                Finding Lifecycle
              </div>

              <h2 className="mt-1 text-base font-bold">
                Current State
              </h2>

              <div className="mt-5 grid grid-cols-2 gap-2">
                {[
                  ["Open", dashboard?.findings.open ?? 0],
                  ["Confirmed", dashboard?.findings.confirmed ?? 0],
                  ["Reopened", dashboard?.findings.reopened ?? 0],
                  ["Resolved", dashboard?.findings.resolved ?? 0],
                ].map(([label, value]) => (
                  <div
                    key={label}
                    className="rounded-xl border border-white/[0.06] bg-black/20 px-4 py-3"
                  >
                    <div className="text-xl font-bold">
                      {loading ? "—" : value}
                    </div>

                    <div className="mt-1 text-[10px] uppercase tracking-wider text-white/30">
                      {label}
                    </div>
                  </div>
                ))}
              </div>
            </div>

            <div className="rounded-2xl border border-white/[0.07] bg-[#09090b] p-5">
              <div className="text-[10px] font-bold uppercase tracking-[0.22em] text-red-400/75">
                Risk Distribution
              </div>

              <h2 className="mt-1 text-base font-bold">
                Current Findings
              </h2>

              <div className="mt-5 space-y-2">
                {[
                  ["Critical", dashboard?.findings.risk_distribution.critical ?? 0, "text-red-300"],
                  ["High", dashboard?.findings.risk_distribution.high ?? 0, "text-orange-300"],
                  ["Medium", dashboard?.findings.risk_distribution.medium ?? 0, "text-amber-300"],
                  ["Low", dashboard?.findings.risk_distribution.low ?? 0, "text-blue-300"],
                  ["Informational", dashboard?.findings.risk_distribution.informational ?? 0, "text-white/55"],
                ].map(([label, value, tone]) => (
                  <div
                    key={label}
                    className="flex items-center justify-between rounded-xl border border-white/[0.06] bg-black/20 px-4 py-3"
                  >
                    <span className={`text-xs font-semibold ${tone}`}>
                      {label}
                    </span>

                    <span className="text-sm font-bold text-white/80">
                      {loading ? "—" : value}
                    </span>
                  </div>
                ))}
              </div>
            </div>
          </div>
        </section>

        {/* Recent Assessments */}
        <section className="mt-4 rounded-2xl border border-white/[0.07] bg-[#09090b] p-5">
          <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <div className="text-[10px] font-bold uppercase tracking-[0.22em] text-red-400/75">
                Assessment Intelligence
              </div>

              <h2 className="mt-1 text-base font-bold">
                Recent Assessments
              </h2>
            </div>

            <span className="rounded-lg border border-white/[0.06] px-3 py-1.5 text-[9px] uppercase tracking-wider text-white/30">
              Last 10
            </span>
          </div>

          <div className="mt-5 space-y-2">
            {loading ? (
              <div className="rounded-xl border border-dashed border-white/[0.08] px-5 py-8 text-center text-xs text-white/30">
                Loading assessments...
              </div>
            ) : dashboard?.recent_assessments.length ? (
              dashboard.recent_assessments.map((assessment) => (
                <div
                  key={assessment.id}
                  className="flex flex-col gap-3 rounded-xl border border-white/[0.06] bg-black/20 px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
                >
                  <div className="min-w-0">
                    <div className="truncate text-xs font-semibold text-white/75">
                      {assessment.target?.name ??
                        assessment.target?.hostname ??
                        "Unknown target"}
                    </div>

                    <div className="mt-1 truncate text-[11px] text-white/30">
                      {assessment.target?.hostname ?? "Target unavailable"}
                    </div>
                  </div>

                  <div className="flex shrink-0 items-center gap-4">
                    <div className="text-right">
                      <div className="text-xs font-semibold uppercase text-white/65">
                        {assessment.status}
                      </div>

                      <div className="mt-1 text-[10px] text-white/30">
                        {assessment.progress}% progress
                      </div>
                    </div>

                    <div className="text-right text-[10px] text-white/25">
                      {assessment.created_at
                        ? new Date(assessment.created_at).toLocaleString()
                        : "—"}
                    </div>
                  </div>
                </div>
              ))
            ) : (
              <div className="rounded-xl border border-dashed border-white/[0.08] px-5 py-8 text-center">
                <div className="text-sm font-semibold text-white/45">
                  No assessments available
                </div>

                <div className="mt-2 text-xs text-white/25">
                  Completed and running assessments will appear here.
                </div>
              </div>
            )}
          </div>

          <Link
            href="/admin/scans"
            className="mt-4 block rounded-xl border border-white/[0.07] px-4 py-3 text-center text-xs font-semibold text-white/55 transition hover:bg-red-500/[0.04] hover:text-white"
          >
            View All Assessments →
          </Link>
        </section>

        {/* Security Events */}
        <section className="mt-4 grid gap-4 lg:grid-cols-[1.4fr_1fr]">
          {/* Recent Security Events */}
          <div className="rounded-2xl border border-white/[0.07] bg-[#09090b] p-5">
            <div className="flex items-center justify-between gap-3">
              <div>
                <div className="text-[10px] font-bold uppercase tracking-[0.22em] text-red-400/75">
                  Security Telemetry
                </div>

                <h2 className="mt-1 text-base font-bold">
                  Recent Security Events
                </h2>
              </div>

              <span className="rounded-lg border border-white/[0.06] px-3 py-1.5 text-[9px] uppercase tracking-wider text-white/30">
                {telemetryLoading ? "SYNCING" : "LIVE DATA"}
              </span>
            </div>

            <div className="mt-5 space-y-2">
              {telemetryLoading ? (
                <div className="rounded-xl border border-dashed border-white/[0.08] px-5 py-10 text-center text-xs text-white/30">
                  Loading security telemetry...
                </div>
              ) : telemetry?.activity_events?.length ? (
                telemetry.activity_events.slice(0, 8).map((event) => (
                  <div
                    key={event.id}
                    className="rounded-xl border border-white/[0.06] bg-black/20 px-4 py-3"
                  >
                    <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                      <div className="min-w-0">
                        <div className="flex items-center gap-2">
                          <span className="h-1.5 w-1.5 shrink-0 rounded-full bg-white/70" />

                          <span className="truncate text-xs font-semibold uppercase tracking-wider text-white/65">
                            {event.event_type.replaceAll("_", " ")}
                          </span>
                        </div>

                        <div className="mt-1 truncate text-[11px] text-white/30">
                          {event.user?.email ?? "Unauthenticated event"}
                          {event.device_type
                            ? ` · ${event.device_type}`
                            : ""}
                          {event.browser ? ` · ${event.browser}` : ""}
                        </div>
                      </div>

                      <div className="shrink-0 text-[10px] text-white/25">
                        {new Date(event.created_at).toLocaleString()}
                      </div>
                    </div>
                  </div>
                ))
              ) : (
                <div className="rounded-xl border border-dashed border-white/[0.08] px-5 py-10 text-center">
                  <div className="text-sm font-semibold text-white/45">
                    No security events available
                  </div>

                  <div className="mt-2 text-xs text-white/25">
                    Real authentication and security events will appear here.
                  </div>
                </div>
              )}
            </div>
          </div>

          {/* Administrative Activity */}
          <div className="rounded-2xl border border-white/[0.07] bg-[#09090b] p-5">
            <div className="flex items-center justify-between gap-3">
              <div>
                <div className="text-[10px] font-bold uppercase tracking-[0.22em] text-red-400/75">
                  Audit
                </div>

                <h2 className="mt-1 text-base font-bold">
                  Administrative Activity
                </h2>
              </div>

              <span className="rounded-lg border border-white/[0.06] px-3 py-1.5 text-[9px] uppercase tracking-wider text-white/30">
                AUDIT LOG
              </span>
            </div>

            <div className="mt-5 space-y-2">
              {telemetryLoading ? (
                <div className="rounded-xl border border-dashed border-white/[0.08] px-5 py-10 text-center text-xs text-white/30">
                  Loading audit logs...
                </div>
              ) : telemetry?.audit_logs?.length ? (
                telemetry.audit_logs.slice(0, 8).map((log) => (
                  <div
                    key={log.id}
                    className="rounded-xl border border-white/[0.06] bg-black/20 px-4 py-3"
                  >
                    <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                      <div className="min-w-0">
                        <div className="truncate text-xs font-semibold text-white/65">
                          {log.action}
                        </div>

                        <div className="mt-1 truncate text-[11px] text-white/30">
                          {log.user?.email ?? "System"}
                          {log.method ? ` · ${log.method}` : ""}
                        </div>
                      </div>

                      <div className="shrink-0 text-[10px] text-white/25">
                        {new Date(log.created_at).toLocaleString()}
                      </div>
                    </div>
                  </div>
                ))
              ) : (
                <div className="rounded-xl border border-dashed border-white/[0.08] px-5 py-10 text-center">
                  <div className="text-sm font-semibold text-white/45">
                    No audit activity available
                  </div>

                  <div className="mt-2 text-xs text-white/25">
                    Administrative actions will appear here as the platform
                    grows.
                  </div>
                </div>
              )}
            </div>

            <Link
              href="/admin/audit-logs"
              className="mt-4 block rounded-xl border border-white/[0.07] px-4 py-3 text-center text-xs font-semibold text-white/55 transition hover:bg-white/[0.04] hover:text-white"
            >
              Open Audit Logs →
            </Link>
          </div>
        </section>

        {/* Footer Status */}
        <section className="mt-4 rounded-2xl border border-white/[0.07] bg-[#09090b] p-5">
          <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <div className="text-[10px] font-bold uppercase tracking-[0.22em] text-white/30">
                Security Posture
              </div>

              <div className="mt-2 text-sm font-semibold text-white/75">
                RBAC protected administrative environment
              </div>

              <div className="mt-1 text-xs text-white/30">
                Sensitive operations require authenticated API access and
                server-side permission enforcement.
              </div>
            </div>

            <div className="flex flex-wrap gap-2">
              {[
                "RBAC",
                "Sanctum",
                "API Protected",
                "Audit Ready",
              ].map((item) => (
                <span
                  key={item}
                  className="rounded-lg border border-white/[0.07] px-3 py-2 text-[9px] font-semibold uppercase tracking-wider text-white/35"
                >
                  {item}
                </span>
              ))}
            </div>
          </div>
        </section>
      </div>
    </main>
  );
}
