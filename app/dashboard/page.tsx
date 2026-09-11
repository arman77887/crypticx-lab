"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import {
  DashboardSummaryResponse,
  getCurrentUser,
  getDashboardSummary,
  getStoredToken,
} from "@/lib/api";

type User = {
  id: string;
  name: string;
  email: string;
  roles?: Array<{
    id?: string;
    name: string;
  }>;
};

type UnknownRecord = Record<string, unknown>;

function isRecord(value: unknown): value is UnknownRecord {
  return typeof value === "object" && value !== null;
}

function extractUser(response: unknown): User | null {
  if (!isRecord(response)) return null;

  const data = response.data;

  if (isRecord(data)) {
    if (isRecord(data.user)) {
      return data.user as unknown as User;
    }

    if (typeof data.id === "string") {
      return data as unknown as User;
    }
  }

  if (isRecord(response.user)) {
    return response.user as unknown as User;
  }

  return null;
}

function statusClass(status?: string) {
  switch ((status || "").toLowerCase()) {
    case "completed":
    case "resolved":
    case "active":
      return "bg-emerald-500/10 text-emerald-300";

    case "running":
    case "queued":
    case "open":
      return "bg-amber-500/10 text-amber-700";

    case "confirmed":
      return "bg-blue-500/10 text-blue-300";

    case "reopened":
      return "bg-orange-500/10 text-orange-700";

    case "failed":
      return "bg-red-500/10 text-red-300";

    default:
      return "bg-white/[0.04] text-[var(--cx-muted)]";
  }
}

function riskClass(level?: string) {
  switch ((level || "").toLowerCase()) {
    case "critical":
      return "bg-red-600/10 text-red-300";

    case "high":
      return "bg-orange-500/10 text-orange-700";

    case "medium":
      return "bg-amber-500/10 text-amber-700";

    case "low":
      return "bg-blue-500/10 text-blue-300";

    case "informational":
      return "border border-white/10 bg-white/[0.04] text-white/55";

    default:
      return "bg-white/[0.04] text-[var(--cx-muted)]";
  }
}

function formatRiskLevel(level?: string) {
  if (!level) return "Unknown";

  return level.charAt(0).toUpperCase() + level.slice(1);
}

export default function DashboardPage() {
  const [user, setUser] = useState<User | null>(null);
  const [summary, setSummary] =
    useState<DashboardSummaryResponse["data"] | null>(null);

  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  useEffect(() => {
    let mounted = true;

    async function loadDashboard() {
      if (!getStoredToken()) {
        window.location.href = "/login";
        return;
      }

      try {
        setError("");

        const [meResponse, dashboardResponse] = await Promise.all([
          getCurrentUser(),
          getDashboardSummary(),
        ]);

        if (!mounted) return;

        const currentUser = extractUser(meResponse);

        if (!currentUser) {
          throw new Error("Unable to load your account.");
        }

        setUser(currentUser);
        setSummary(dashboardResponse.data);
      } catch (err) {
        if (!mounted) return;

        setError(
          err instanceof Error
            ? err.message
            : "Unable to load dashboard data.",
        );
      } finally {
        if (mounted) {
          setLoading(false);
        }
      }
    }

    loadDashboard();

    return () => {
      mounted = false;
    };
  }, []);

  if (loading) {
    return (
      <main className="min-h-[70vh] bg-[var(--cx-bg)]">
        <div className="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
          <div className="cx-card p-8">
            <p className="text-sm text-[var(--cx-muted)]">
              Loading your CrypticX workspace...
            </p>
          </div>
        </div>
      </main>
    );
  }

  if (error || !summary) {
    return (
      <main className="min-h-[70vh] bg-[var(--cx-bg)]">
        <div className="mx-auto max-w-3xl px-4 py-16 sm:px-6">
          <div className="cx-card p-8">
            <p className="text-xs font-semibold uppercase tracking-[0.18em] text-red-400">
              Dashboard unavailable
            </p>

            <h1 className="mt-3 text-2xl font-bold text-[var(--cx-text)]">
              We could not load your workspace.
            </h1>

            <p className="mt-3 text-sm text-[var(--cx-muted)]">
              {error || "Dashboard summary is unavailable."}
            </p>

            <div className="mt-6 flex gap-3">
              <button
                type="button"
                onClick={() => window.location.reload()}
                className="cx-button cx-button-primary"
              >
                Retry
              </button>

              <Link href="/" className="cx-button cx-button-secondary">
                Home
              </Link>
            </div>
          </div>
        </div>
      </main>
    );
  }

  const overallRisk = summary.overall_risk;
  const findings = summary.findings;
  const assessments = summary.assessments;
  const targetRisk = summary.targets.risk ?? [];
  const recentAssessments = summary.recent_assessments ?? [];

  const runningAssessments =
    assessments.running + assessments.queued;

  return (
    <main className="min-h-screen bg-[var(--cx-bg)] text-[var(--cx-text)]">
      <section className="border-b border-[var(--cx-border)]">
        <div className="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
          <div className="flex flex-col gap-6 md:flex-row md:items-end md:justify-between">
            <div>
              <p className="text-xs font-semibold uppercase tracking-[0.2em] text-[var(--cx-muted)]">
                Security Workspace
              </p>

              <h1 className="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">
                Welcome, {user?.name || "Researcher"}
              </h1>

              <p className="mt-3 max-w-2xl text-sm leading-6 text-[var(--cx-muted)]">
                Live security posture calculated from your authorized targets,
                assessment history and current finding lifecycles.
              </p>
            </div>

            <Link
              href="/scanner"
              className="cx-button cx-button-primary"
            >
              New Assessment
            </Link>
          </div>
        </div>
      </section>

      <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <nav className="mb-8 flex flex-wrap gap-2">
          <Link
            href="/dashboard"
            className="cx-button cx-button-primary"
          >
            Overview
          </Link>

          <Link
            href="/scanner"
            className="cx-button cx-button-secondary"
          >
            Scanner
          </Link>

          <a
            href="#targets"
            className="cx-button cx-button-secondary"
          >
            My Targets
          </a>

          <a
            href="#assessments"
            className="cx-button cx-button-secondary"
          >
            Assessments
          </a>

          <Link
            href="/findings"
            className="cx-button cx-button-secondary"
          >
            Findings
          </Link>
        </nav>

        <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          <div className="cx-card p-6">
            <p className="text-sm text-[var(--cx-muted)]">
              Overall Risk
            </p>

            <div className="mt-3 flex items-end gap-3">
              <p className="text-4xl font-bold">
                {overallRisk.score}
                <span className="text-lg text-[var(--cx-muted)]">
                  /100
                </span>
              </p>

              <span
                className={`mb-1 rounded-full px-3 py-1 text-xs font-semibold ${riskClass(
                  overallRisk.level,
                )}`}
              >
                {formatRiskLevel(overallRisk.level)}
              </span>
            </div>

            <p className="mt-3 text-xs text-[var(--cx-muted)]">
              Highest {overallRisk.highest}/100 · Average{" "}
              {overallRisk.average}/100
            </p>
          </div>

          <div className="cx-card p-6">
            <p className="text-sm text-[var(--cx-muted)]">
              Active Targets
            </p>

            <p className="mt-3 text-3xl font-bold">
              {summary.targets.active}
            </p>

            <p className="mt-3 text-xs text-[var(--cx-muted)]">
              {summary.targets.total} total authorized targets
            </p>
          </div>

          <div className="cx-card p-6">
            <p className="text-sm text-[var(--cx-muted)]">
              Current Findings
            </p>

            <p className="mt-3 text-3xl font-bold">
              {findings.current}
            </p>

            <p className="mt-3 text-xs text-[var(--cx-muted)]">
              {findings.status.resolved} resolved
            </p>
          </div>

          <div className="cx-card p-6">
            <p className="text-sm text-[var(--cx-muted)]">
              Critical / High Risk
            </p>

            <p className="mt-3 text-3xl font-bold">
              {findings.critical_or_high}
            </p>

            <p className="mt-3 text-xs text-[var(--cx-muted)]">
              Current lifecycle findings
            </p>
          </div>
        </section>

        <section className="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          {[
            ["Open", findings.status.open],
            ["Confirmed", findings.status.confirmed],
            ["Reopened", findings.status.reopened],
            ["Resolved", findings.status.resolved],
          ].map(([label, value]) => (
            <div key={String(label)} className="cx-card p-5">
              <p className="text-xs font-semibold uppercase tracking-[0.14em] text-[var(--cx-muted)]">
                {label}
              </p>

              <p className="mt-2 text-2xl font-bold">{value}</p>
            </div>
          ))}
        </section>

        {runningAssessments > 0 && (
          <section className="mt-6 rounded-2xl border border-amber-500/20 bg-amber-500/5 p-5">
            <p className="font-semibold">
              {runningAssessments} assessment
              {runningAssessments === 1 ? "" : "s"} currently
              queued or running.
            </p>
          </section>
        )}

        <section className="mt-10 grid gap-6 lg:grid-cols-2">
          <div className="cx-card p-6">
            <div>
              <p className="text-xs font-semibold uppercase tracking-[0.18em] text-[var(--cx-muted)]">
                Risk Intelligence
              </p>

              <h2 className="mt-1 text-2xl font-bold">
                Risk Distribution
              </h2>
            </div>

            <div className="mt-6 space-y-4">
              {[
                [
                  "Critical",
                  findings.risk_distribution.critical,
                  "critical",
                ],
                ["High", findings.risk_distribution.high, "high"],
                [
                  "Medium",
                  findings.risk_distribution.medium,
                  "medium",
                ],
                ["Low", findings.risk_distribution.low, "low"],
                [
                  "Informational",
                  findings.risk_distribution.informational,
                  "informational",
                ],
              ].map(([label, value, level]) => {
                const numericValue = Number(value);
                const denominator = Math.max(findings.current, 1);
                const percentage = Math.round(
                  (numericValue / denominator) * 100,
                );

                return (
                  <div key={String(label)}>
                    <div className="flex items-center justify-between gap-4">
                      <span
                        className={`rounded-full px-3 py-1 text-xs font-semibold ${riskClass(
                          String(level),
                        )}`}
                      >
                        {label}
                      </span>

                      <span className="text-sm font-semibold">
                        {numericValue}
                        <span className="ml-2 text-xs font-normal text-[var(--cx-muted)]">
                          {percentage}%
                        </span>
                      </span>
                    </div>

                    <div className="mt-2 h-2 overflow-hidden rounded-full bg-white/[0.04]">
                      <div
                        className="h-full rounded-full bg-current opacity-40"
                        style={{
                          width: `${Math.min(percentage, 100)}%`,
                        }}
                      />
                    </div>
                  </div>
                );
              })}
            </div>
          </div>

          <div className="cx-card p-6">
            <p className="text-xs font-semibold uppercase tracking-[0.18em] text-[var(--cx-muted)]">
              Assessment Health
            </p>

            <h2 className="mt-1 text-2xl font-bold">
              Scan Activity
            </h2>

            <div className="mt-6 grid grid-cols-2 gap-4">
              {[
                ["Total", assessments.total],
                ["Completed", assessments.completed],
                ["Running", assessments.running],
                ["Queued", assessments.queued],
                ["Failed", assessments.failed],
              ].map(([label, value]) => (
                <div
                  key={String(label)}
                  className="cx-inset-sm rounded-2xl p-4"
                >
                  <p className="text-xs text-[var(--cx-muted)]">
                    {label}
                  </p>

                  <p className="mt-2 text-2xl font-bold">
                    {value}
                  </p>
                </div>
              ))}
            </div>
          </div>
        </section>

        <section id="targets" className="mt-10">
          <div className="mb-4 flex items-center justify-between gap-4">
            <div>
              <p className="text-xs font-semibold uppercase tracking-[0.18em] text-[var(--cx-muted)]">
                Scope Intelligence
              </p>

              <h2 className="mt-1 text-2xl font-bold">
                Top Risk Targets
              </h2>
            </div>

            <Link
              href="/scanner"
              className="text-sm font-semibold"
            >
              Add target →
            </Link>
          </div>

          <div className="cx-card overflow-hidden">
            {targetRisk.length === 0 ? (
              <div className="p-8">
                <p className="font-semibold">
                  No authorized targets yet.
                </p>
              </div>
            ) : (
              <div className="divide-y divide-[var(--cx-border)]">
                {targetRisk.slice(0, 8).map((target) => (
                  <div
                    key={target.target_id}
                    className="grid gap-4 p-5 sm:grid-cols-[1fr_auto_auto] sm:items-center"
                  >
                    <div className="min-w-0">
                      <p className="font-semibold">
                        {target.name}
                      </p>

                      <p className="mt-1 truncate text-sm text-[var(--cx-muted)]">
                        {target.hostname}
                      </p>

                      <p className="mt-1 text-xs text-[var(--cx-muted)]">
                        {target.finding_count} current finding
                        {target.finding_count === 1 ? "" : "s"}
                      </p>
                    </div>

                    <span
                      className={`w-fit rounded-full px-3 py-1 text-xs font-semibold ${statusClass(
                        target.status,
                      )}`}
                    >
                      {target.status}
                    </span>

                    <span
                      className={`w-fit rounded-full px-3 py-1 text-xs font-semibold ${riskClass(
                        target.risk_level,
                      )}`}
                    >
                      {target.risk_score}/100{" "}
                      {formatRiskLevel(target.risk_level)}
                    </span>
                  </div>
                ))}
              </div>
            )}
          </div>
        </section>

        <section id="assessments" className="mt-10 pb-16">
          <div className="mb-4">
            <p className="text-xs font-semibold uppercase tracking-[0.18em] text-[var(--cx-muted)]">
              Execution Intelligence
            </p>

            <h2 className="mt-1 text-2xl font-bold">
              Recent Assessments
            </h2>
          </div>

          <div className="cx-card overflow-hidden">
            {recentAssessments.length === 0 ? (
              <div className="p-8">
                <p className="font-semibold">
                  No assessments yet.
                </p>
              </div>
            ) : (
              <div className="divide-y divide-[var(--cx-border)]">
                {recentAssessments.slice(0, 10).map((assessment) => (
                  <Link
                    key={assessment.assessment_id}
                    href={`/assessments/${assessment.assessment_id}`}
                    className="grid gap-4 p-5 transition hover:bg-red-500/[0.04] md:grid-cols-[1fr_auto_auto_auto] md:items-center"
                  >
                    <div className="min-w-0">
                      <p className="font-semibold">
                        {assessment.target?.name ||
                          `Assessment ${assessment.assessment_id.slice(
                            0,
                            8,
                          )}`}
                      </p>

                      <p className="mt-1 truncate text-xs text-[var(--cx-muted)]">
                        {assessment.target?.hostname ||
                          assessment.assessment_id}
                      </p>

                      <p className="mt-1 text-xs text-[var(--cx-muted)]">
                        {assessment.finding_count} finding
                        {assessment.finding_count === 1 ? "" : "s"}
                      </p>
                    </div>

                    <span className="text-sm text-[var(--cx-muted)]">
                      {assessment.progress ?? 0}%
                    </span>

                    <span
                      className={`w-fit rounded-full px-3 py-1 text-xs font-semibold ${statusClass(
                        assessment.status,
                      )}`}
                    >
                      {assessment.status}
                    </span>

                    <span
                      className={`w-fit rounded-full px-3 py-1 text-xs font-semibold ${riskClass(
                        assessment.risk_level,
                      )}`}
                    >
                      {assessment.risk_score}/100{" "}
                      {formatRiskLevel(assessment.risk_level)}
                    </span>
                  </Link>
                ))}
              </div>
            )}
          </div>
        </section>

        <section className="mb-16">
          <div className="cx-card p-5">
            <p className="text-xs font-semibold uppercase tracking-[0.16em] text-[var(--cx-muted)]">
              Risk Model
            </p>

            <p className="mt-2 text-sm text-[var(--cx-muted)]">
              {summary.scoring.version} · {summary.scoring.formula}
            </p>

            <p className="mt-1 text-xs text-[var(--cx-subtle)]">
              Risk scores represent CrypticX security prioritization,
              not a percentage probability of compromise.
            </p>
          </div>
        </section>
      </div>
    </main>
  );
}
