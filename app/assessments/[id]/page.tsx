"use client";

import Link from "next/link";
import { useParams, useRouter } from "next/navigation";
import { useEffect, useState } from "react";
import {
  AssessmentIntelligenceComparison,
  AssessmentIntelligenceResponse,
  CreateAssessmentResponse,
  generateReport,
  getAssessment,
  getAssessmentIntelligence,
  getStoredToken,
} from "@/lib/api";

function badgeClass(value: string) {
  const v = value.toLowerCase();

  if (v === "critical" || v === "failed") {
    return "border-red-500/40 bg-red-500/10 text-red-300";
  }

  if (v === "high" || v === "increased") {
    return "border-orange-500/30 bg-orange-500/10 text-orange-300";
  }

  if (v === "medium") {
    return "border-amber-500/30 bg-amber-500/10 text-amber-300";
  }

  if (
    v === "low" ||
    v === "completed" ||
    v === "decreased"
  ) {
    return "border-emerald-500/30 bg-emerald-500/10 text-emerald-300";
  }

  return "border-white/10 bg-white/[0.04] text-[var(--cx-muted)]";
}

function Badge({ value }: { value: string }) {
  return (
    <span
      className={`inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold uppercase tracking-[0.12em] ${badgeClass(
        value,
      )}`}
    >
      {value}
    </span>
  );
}

function Metric({
  label,
  value,
  note,
}: {
  label: string;
  value: string | number;
  note?: string;
}) {
  return (
    <div className="cx-card p-5">
      <p className="text-xs uppercase tracking-[0.18em] text-[var(--cx-subtle)]">
        {label}
      </p>
      <p className="mt-3 text-3xl font-bold text-white">
        {value}
      </p>
      {note ? (
        <p className="mt-2 text-xs text-[var(--cx-muted)]">
          {note}
        </p>
      ) : null}
    </div>
  );
}

function FindingRow({
  title,
  severity,
  confidence,
  change,
}: {
  title: string;
  severity: string;
  confidence: string;
  change?: string;
}) {
  return (
    <div className="border-b border-white/[0.06] py-4 last:border-0">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <p className="font-medium text-white">{title}</p>
          {change ? (
            <p className="mt-1 text-xs text-red-300">
              {change}
            </p>
          ) : null}
        </div>

        <div className="flex flex-wrap gap-2">
          <Badge value={severity} />
          <span className="rounded-full border border-white/10 px-2.5 py-1 text-xs text-[var(--cx-muted)]">
            confidence: {confidence}
          </span>
        </div>
      </div>
    </div>
  );
}

function Intelligence({
  comparison,
}: {
  comparison: AssessmentIntelligenceComparison;
}) {
  const { risk, summary } = comparison;
  const delta =
    risk.delta_points > 0
      ? `+${risk.delta_points}`
      : String(risk.delta_points);

  return (
    <>
      <section className="mt-8">
        <div className="mb-4 flex flex-wrap items-end justify-between gap-3">
          <div>
            <p className="text-xs font-semibold uppercase tracking-[0.2em] text-red-400">
              Assessment Intelligence
            </p>
            <h2 className="mt-2 text-2xl font-bold text-white">
              Risk Delta
            </h2>
          </div>

          <Badge value={risk.trend} />
        </div>

        <div className="grid gap-4 md:grid-cols-3">
          <Metric
            label="Previous Risk"
            value={`${risk.previous.score}/100`}
            note={risk.previous.level}
          />
          <Metric
            label="Current Risk"
            value={`${risk.current.score}/100`}
            note={risk.current.level}
          />
          <Metric
            label="Risk Delta"
            value={delta}
            note="risk points — not probability"
          />
        </div>
      </section>

      <section className="mt-8">
        <h2 className="text-xl font-bold text-white">
          Finding Delta
        </h2>

        <div className="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <Metric label="New" value={summary.new} />
          <Metric
            label="Persistent"
            value={summary.persistent}
          />
          <Metric
            label="No Longer Detected"
            value={summary.no_longer_detected}
          />
          <Metric
            label="Net Change"
            value={
              summary.net_change > 0
                ? `+${summary.net_change}`
                : summary.net_change
            }
          />
        </div>
      </section>

      <div className="mt-8 grid gap-6 xl:grid-cols-3">
        <section className="cx-card p-6">
          <h3 className="text-lg font-bold text-white">
            New Findings
          </h3>
          <p className="mt-1 text-xs text-[var(--cx-muted)]">
            Detected now, absent from the previous assessment.
          </p>

          <div className="mt-4">
            {comparison.new.length ? (
              comparison.new.map((finding) => (
                <FindingRow
                  key={finding.id}
                  title={finding.title}
                  severity={finding.severity}
                  confidence={finding.confidence}
                />
              ))
            ) : (
              <p className="py-5 text-sm text-[var(--cx-muted)]">
                No new findings.
              </p>
            )}
          </div>
        </section>

        <section className="cx-card p-6">
          <h3 className="text-lg font-bold text-white">
            Persistent Findings
          </h3>
          <p className="mt-1 text-xs text-[var(--cx-muted)]">
            Same deterministic finding identity in both assessments.
          </p>

          <div className="mt-4">
            {comparison.persistent.length ? (
              comparison.persistent.map((item) => {
                const changes = Object.entries(item.changes)
                  .map(
                    ([field, values]) =>
                      `${field}: ${values.from ?? "—"} → ${
                        values.to ?? "—"
                      }`,
                  )
                  .join(" · ");

                return (
                  <FindingRow
                    key={item.fingerprint}
                    title={item.current.title}
                    severity={item.current.severity}
                    confidence={item.current.confidence}
                    change={changes || undefined}
                  />
                );
              })
            ) : (
              <p className="py-5 text-sm text-[var(--cx-muted)]">
                No persistent findings.
              </p>
            )}
          </div>
        </section>

        <section className="cx-card p-6">
          <h3 className="text-lg font-bold text-white">
            No Longer Detected
          </h3>
          <p className="mt-1 text-xs text-[var(--cx-muted)]">
            Present previously but absent from this assessment.
          </p>

          <div className="mt-4">
            {comparison.no_longer_detected.length ? (
              comparison.no_longer_detected.map((finding) => (
                <FindingRow
                  key={finding.id}
                  title={finding.title}
                  severity={finding.severity}
                  confidence={finding.confidence}
                />
              ))
            ) : (
              <p className="py-5 text-sm text-[var(--cx-muted)]">
                Nothing moved out of detection.
              </p>
            )}
          </div>
        </section>
      </div>

      <div className="mt-6 rounded-2xl border border-amber-500/20 bg-amber-500/[0.05] p-5">
        <p className="text-sm font-semibold text-amber-200">
          Interpretation boundary
        </p>
        <p className="mt-2 text-sm leading-6 text-[var(--cx-muted)]">
          “No longer detected” does not prove that a vulnerability
          has been remediated. Risk values are derived using the
          current lifecycle scoring state and are not immutable
          historical snapshots or vulnerability probabilities.
        </p>
      </div>
    </>
  );
}

export default function AssessmentDetailPage() {
  const params = useParams();
  const router = useRouter();

  const assessmentId = Array.isArray(params?.id)
    ? params.id[0]
    : String(params?.id ?? "");

  const [assessment, setAssessment] =
    useState<CreateAssessmentResponse["data"] | null>(null);

  const [intelligence, setIntelligence] =
    useState<AssessmentIntelligenceResponse["data"] | null>(
      null,
    );

  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  const [generatingReport, setGeneratingReport] = useState(false);
  const [reportError, setReportError] = useState("");

  useEffect(() => {
    let cancelled = false;

    async function load() {
      if (!assessmentId) {
        return;
      }

      if (!getStoredToken()) {
        router.replace("/login");
        return;
      }

      setLoading(true);
      setError("");

      try {
        const assessmentResponse =
          await getAssessment(assessmentId);

        if (cancelled) return;

        setAssessment(assessmentResponse.data);

        if (assessmentResponse.data.status === "completed") {
          const intelligenceResponse =
            await getAssessmentIntelligence(assessmentId);

          if (!cancelled) {
            setIntelligence(intelligenceResponse.data);
          }
        }
      } catch (err) {
        if (!cancelled) {
          setError(
            err instanceof Error
              ? err.message
              : "Assessment could not be loaded.",
          );
        }
      } finally {
        if (!cancelled) {
          setLoading(false);
        }
      }
    }

    load();

    return () => {
      cancelled = true;
    };
  }, [assessmentId, router]);

  async function handleGenerateReport() {
    if (!assessment || assessment.status !== "completed") {
      return;
    }

    setGeneratingReport(true);
    setReportError("");

    try {
      const response = await generateReport(assessment.id);
      router.push(`/reports/${response.data.id}`);
    } catch (err) {
      setReportError(
        err instanceof Error
          ? err.message
          : "Report could not be generated.",
      );
      setGeneratingReport(false);
    }
  }

  if (loading) {
    return (
      <main className="min-h-screen bg-[var(--cx-bg)] px-6 py-24">
        <div className="mx-auto max-w-7xl">
          <div className="cx-card animate-pulse p-8">
            <p className="text-sm text-[var(--cx-muted)]">
              Loading assessment intelligence…
            </p>
          </div>
        </div>
      </main>
    );
  }

  if (error || !assessment) {
    return (
      <main className="min-h-screen bg-[var(--cx-bg)] px-6 py-24">
        <div className="mx-auto max-w-3xl">
          <div className="cx-card p-8">
            <p className="text-xs font-semibold uppercase tracking-[0.2em] text-red-400">
              Assessment
            </p>
            <h1 className="mt-3 text-2xl font-bold text-white">
              Unable to load assessment
            </h1>
            <p className="mt-3 text-sm text-[var(--cx-muted)]">
              {error || "Assessment data is unavailable."}
            </p>
            <Link
              href="/dashboard"
              className="cx-button cx-button-secondary mt-6 inline-flex"
            >
              Back to Dashboard
            </Link>
          </div>
        </div>
      </main>
    );
  }

  return (
    <main className="min-h-screen bg-[var(--cx-bg)] px-6 py-20">
      <div className="mx-auto max-w-7xl">
        <div className="flex flex-col gap-6 border-b border-white/[0.07] pb-8 lg:flex-row lg:items-end lg:justify-between">
          <div>
            <Link
              href="/dashboard"
              className="text-sm text-[var(--cx-muted)] transition hover:text-red-300"
            >
              ← Dashboard
            </Link>

            <p className="mt-6 text-xs font-semibold uppercase tracking-[0.22em] text-red-400">
              Security Assessment
            </p>

            <h1 className="mt-2 text-3xl font-bold text-white md:text-4xl">
              {assessment.target?.name || "Assessment"}
            </h1>

            <p className="mt-2 break-all text-sm text-[var(--cx-muted)]">
              {assessment.target?.hostname ||
                assessment.target?.url ||
                assessment.target_id}
            </p>
          </div>

          <div className="flex flex-col items-start gap-3 lg:items-end">
            <div className="flex flex-wrap gap-2">
              <Badge value={assessment.status} />

              <span className="rounded-full border border-white/10 px-3 py-1 text-xs text-[var(--cx-muted)]">
                {assessment.profile}
              </span>
            </div>

            {assessment.status === "completed" ? (
              <button
                type="button"
                onClick={handleGenerateReport}
                disabled={generatingReport}
                className="cx-button cx-button-primary disabled:cursor-not-allowed disabled:opacity-60"
              >
                {generatingReport
                  ? "Generating Report…"
                  : "Generate Report"}
              </button>
            ) : null}
          </div>
        </div>

        {reportError ? (
          <div className="mt-6 rounded-2xl border border-red-500/30 bg-red-500/[0.06] p-4">
            <p className="text-sm font-semibold text-red-300">
              Report generation failed
            </p>

            <p className="mt-1 text-sm text-[var(--cx-muted)]">
              {reportError}
            </p>
          </div>
        ) : null}

        <section className="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <Metric
            label="Progress"
            value={`${assessment.progress}%`}
          />
          <Metric
            label="Findings"
            value={assessment.findings?.length ?? 0}
          />
          <Metric
            label="Profile"
            value={assessment.profile}
          />
          <Metric
            label="Status"
            value={assessment.status}
          />
        </section>

        {assessment.status !== "completed" ? (
          <div className="cx-card mt-8 p-6">
            <h2 className="text-lg font-bold text-white">
              Intelligence pending
            </h2>
            <p className="mt-2 text-sm text-[var(--cx-muted)]">
              Assessment Intelligence becomes available after the
              assessment reaches completed status.
            </p>
          </div>
        ) : intelligence?.available === true ? (
          <Intelligence comparison={intelligence.comparison} />
        ) : (
          <div className="cx-card mt-8 p-6">
            <p className="text-xs font-semibold uppercase tracking-[0.2em] text-red-400">
              Assessment Intelligence
            </p>
            <h2 className="mt-2 text-xl font-bold text-white">
              Baseline assessment
            </h2>
            <p className="mt-2 text-sm leading-6 text-[var(--cx-muted)]">
              No previous completed assessment exists for this
              target yet. This assessment establishes the baseline
              for future comparison.
            </p>
          </div>
        )}

        <section className="mt-8 cx-card p-6">
          <div className="flex flex-wrap items-center justify-between gap-3">
            <div>
              <h2 className="text-xl font-bold text-white">
                Current Findings
              </h2>
              <p className="mt-1 text-sm text-[var(--cx-muted)]">
                Findings produced by this assessment.
              </p>
            </div>

            <Link
              href={`/findings?assessment_id=${assessment.id}`}
              className="cx-button cx-button-secondary"
            >
              Open Findings
            </Link>
          </div>

          <div className="mt-4">
            {assessment.findings?.length ? (
              assessment.findings.map((finding) => (
                <Link
                  key={finding.id}
                  href={`/findings/${finding.id}`}
                  className="block border-b border-white/[0.06] py-4 transition hover:bg-red-500/[0.03] last:border-0"
                >
                  <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                      <p className="font-medium text-white">
                        {finding.title}
                      </p>
                      <p className="mt-1 text-xs text-[var(--cx-muted)]">
                        {finding.type}
                      </p>
                    </div>

                    <div className="flex gap-2">
                      <Badge value={finding.severity} />
                      <Badge value={finding.status} />
                    </div>
                  </div>
                </Link>
              ))
            ) : (
              <p className="py-6 text-sm text-[var(--cx-muted)]">
                No findings were produced by this assessment.
              </p>
            )}
          </div>
        </section>
      </div>
    </main>
  );
}
