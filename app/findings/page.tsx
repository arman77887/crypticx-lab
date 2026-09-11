"use client";

import Link from "next/link";
import { useEffect, useMemo, useState } from "react";
import {
  getFindingLifecycles,
  getStoredToken,
} from "@/lib/api";

type Target = {
  id?: string;
  name?: string;
  url?: string;
  hostname?: string;
};

type LastFinding = {
  id: string;
};

type Risk = {
  score: number;
  level: string;
  components?: {
    severity?: { value?: string; score?: number; max?: number };
    confidence?: { value?: string; score?: number; max?: number };
    recurrence?: { occurrence_count?: number; score?: number; max?: number };
    lifecycle?: { status?: string; score?: number; max?: number };
    asset_importance?: { value?: string; score?: number; max?: number };
  };
};

type Lifecycle = {
  id: string;
  target_id: string;
  fingerprint: string;
  type?: string;
  title: string;
  severity: string;
  confidence?: string;
  status: string;
  first_seen_at?: string;
  last_seen_at?: string;
  resolved_at?: string | null;
  reopened_at?: string | null;
  occurrence_count: number;
  target?: Target;
  last_finding?: LastFinding | null;
  risk?: Risk | null;
};

type UnknownRecord = Record<string, unknown>;

function isRecord(value: unknown): value is UnknownRecord {
  return typeof value === "object" && value !== null;
}

function extractLifecycles(response: unknown): Lifecycle[] {
  if (!isRecord(response)) return [];

  const outerData = response.data;

  if (Array.isArray(outerData)) {
    return outerData as Lifecycle[];
  }

  if (!isRecord(outerData)) return [];

  if (Array.isArray(outerData.data)) {
    return outerData.data as Lifecycle[];
  }

  return [];
}

function severityClass(severity?: string) {
  switch ((severity || "").toLowerCase()) {
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
    default:
      return "bg-white/[0.04] text-[var(--cx-muted)]";
  }
}

function statusClass(status?: string) {
  switch ((status || "").toLowerCase()) {
    case "resolved":
      return "bg-emerald-500/10 text-emerald-300";
    case "confirmed":
      return "bg-blue-500/10 text-blue-300";
    case "open":
      return "bg-amber-500/10 text-amber-700";
    default:
      return "bg-white/[0.04] text-[var(--cx-muted)]";
  }
}

function formatDate(value?: string) {
  if (!value) return "—";

  const date = new Date(value);

  if (Number.isNaN(date.getTime())) {
    return value;
  }

  return date.toLocaleString();
}

export default function FindingsPage() {
  const [findings, setFindings] = useState<Lifecycle[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  useEffect(() => {
    let mounted = true;

    async function load() {
      if (!getStoredToken()) {
        window.location.href = "/login";
        return;
      }

      try {
        setError("");

        const response = await getFindingLifecycles({
          per_page: 100,
          sort: "last_seen_at",
          direction: "desc",
        });

        if (mounted) {
          setFindings(extractLifecycles(response));
        }
      } catch (err) {
        if (mounted) {
          setError(
            err instanceof Error
              ? err.message
              : "Unable to load findings.",
          );
        }
      } finally {
        if (mounted) {
          setLoading(false);
        }
      }
    }

    load();

    return () => {
      mounted = false;
    };
  }, []);

  const openCount = useMemo(
    () =>
      findings.filter(
        (finding) =>
          finding.status?.toLowerCase() === "open",
      ).length,
    [findings],
  );

  const confirmedCount = useMemo(
    () =>
      findings.filter(
        (finding) =>
          finding.status?.toLowerCase() === "confirmed",
      ).length,
    [findings],
  );

  const highRiskCount = useMemo(
    () =>
      findings.filter((finding) =>
        ["critical", "high"].includes(
          finding.risk?.level?.toLowerCase() || "",
        ),
      ).length,
    [findings],
  );

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
                Current Security Findings
              </h1>

              <p className="mt-2 max-w-2xl text-sm text-[var(--cx-muted)]">
                Each logical security issue appears once, with its
                lifecycle and recurrence tracked across assessments.
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
        <section className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <div className="cx-card p-6">
            <p className="text-sm text-[var(--cx-muted)]">
              Unique Findings
            </p>
            <p className="mt-2 text-3xl font-bold">
              {findings.length}
            </p>
          </div>

          <div className="cx-card p-6">
            <p className="text-sm text-[var(--cx-muted)]">
              Open
            </p>
            <p className="mt-2 text-3xl font-bold">
              {openCount}
            </p>
          </div>

          <div className="cx-card p-6">
            <p className="text-sm text-[var(--cx-muted)]">
              Confirmed
            </p>
            <p className="mt-2 text-3xl font-bold">
              {confirmedCount}
            </p>
          </div>

          <div className="cx-card p-6">
            <p className="text-sm text-[var(--cx-muted)]">
              Critical / High Risk
            </p>
            <p className="mt-2 text-3xl font-bold">
              {highRiskCount}
            </p>
          </div>
        </section>

        <section className="mt-8">
          <div className="cx-card overflow-hidden">
            {loading ? (
              <div className="p-8 text-sm text-[var(--cx-muted)]">
                Loading lifecycle findings...
              </div>
            ) : error ? (
              <div className="p-8 text-sm text-red-400">
                {error}
              </div>
            ) : findings.length === 0 ? (
              <div className="p-8">
                <p className="font-semibold">
                  No current findings.
                </p>

                <p className="mt-2 text-sm text-[var(--cx-muted)]">
                  Run an authorized assessment to generate
                  security findings.
                </p>
              </div>
            ) : (
              <div className="divide-y divide-[var(--cx-border)]">
                {findings.map((finding) => {
                  const findingId = finding.last_finding?.id;

                  const content = (
                    <div className="grid gap-4 md:grid-cols-[minmax(0,1fr)_auto_auto_auto_auto] md:items-center">
                      <div className="min-w-0">
                        <p className="font-semibold">
                          {finding.title}
                        </p>

                        <p className="mt-1 truncate text-xs text-[var(--cx-muted)]">
                          {finding.target?.hostname ||
                            finding.target?.name ||
                            finding.type ||
                            "Security finding"}
                        </p>

                        <div className="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-[var(--cx-muted)]">
                          <span>
                            Occurrences:{" "}
                            {finding.occurrence_count ?? 1}
                          </span>

                          <span>
                            First seen:{" "}
                            {formatDate(finding.first_seen_at)}
                          </span>

                          <span>
                            Last seen:{" "}
                            {formatDate(finding.last_seen_at)}
                          </span>
                        </div>
                      </div>

                      <div className="min-w-[90px]">
                        <p className="text-[10px] font-semibold uppercase tracking-wider text-[var(--cx-muted)]">
                          Risk
                        </p>
                        <span
                          className={`mt-1 inline-flex rounded-full px-3 py-1 text-xs font-bold ${riskClass(
                            finding.risk?.level,
                          )}`}
                        >
                          {finding.risk
                            ? `${finding.risk.score}/100 ${finding.risk.level}`
                            : "—"}
                        </span>
                      </div>

                      <span
                        className={`h-fit w-fit rounded-full px-3 py-1 text-xs font-semibold ${severityClass(
                          finding.severity,
                        )}`}
                      >
                        {finding.severity}
                      </span>

                      <span
                        className={`h-fit w-fit rounded-full px-3 py-1 text-xs font-semibold ${statusClass(
                          finding.status,
                        )}`}
                      >
                        {finding.status}
                      </span>

                      <span className="text-sm font-semibold">
                        {findingId ? "View →" : "Unavailable"}
                      </span>
                    </div>
                  );

                  if (!findingId) {
                    return (
                      <div
                        key={finding.id}
                        className="p-5 opacity-70"
                      >
                        {content}
                      </div>
                    );
                  }

                  return (
                    <Link
                      key={finding.id}
                      href={`/findings/${findingId}`}
                      className="block p-5 transition hover:bg-red-500/[0.04]"
                    >
                      {content}
                    </Link>
                  );
                })}
              </div>
            )}
          </div>
        </section>
      </div>
    </main>
  );
}
