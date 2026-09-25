"use client";

import Link from "next/link";

import { useEffect, useMemo, useState } from "react";
import {
  AccountEntitlements,
  ApiError,
  ApiFinding,
  createAssessment,
  createTarget,
  getAccountEntitlements,
  getAssessment,
  getFindings,
  getStoredToken,
} from "@/lib/api";

const scanTypes = [
  {
    title: "Web Security",
    profile: "standard" as const,
    description: "Headers, configuration and common web security checks.",
  },
  {
    title: "SSL / TLS",
    profile: "standard" as const,
    description: "Certificate, protocol and transport-security analysis.",
  },
  {
    title: "DNS Intelligence",
    profile: "discovery" as const,
    description: "DNS records and publicly observable configuration signals.",
  },
  {
    title: "API Security",
    profile: "deep" as const,
    description: "Authorized API endpoint and security-control assessment.",
  },
];

function severityClass(severity: string) {
  switch (severity.toLowerCase()) {
    case "critical":
      return "bg-red-100 text-red-300";
    case "high":
      return "bg-orange-100 text-orange-700";
    case "medium":
      return "bg-amber-100 text-amber-700";
    case "low":
      return "bg-blue-100 text-blue-300";
    default:
      return "border border-white/10 bg-white/[0.04] text-white/55";
  }
}

import { trackEvent } from "@/lib/analytics";
export default function ScannerPage() {
  const [target, setTarget] = useState("");
  const [selected, setSelected] = useState("Web Security");
  const [authorized, setAuthorized] = useState(false);

  const [assessmentId, setAssessmentId] = useState<string | null>(null);
  const [status, setStatus] = useState("Waiting");
  const [progress, setProgress] = useState(0);
  const [findings, setFindings] = useState<ApiFinding[]>([]);
  const [error, setError] = useState("");
  const [busy, setBusy] = useState(false);
  const [entitlements, setEntitlements] =
    useState<AccountEntitlements | null>(null);
  const [quotaModalOpen, setQuotaModalOpen] = useState(false);

  const selectedType = useMemo(
    () => scanTypes.find((item) => item.title === selected) ?? scanTypes[0],
    [selected],
  );

  useEffect(() => {
    const savedAssessmentId =
      window.sessionStorage.getItem("crypticx_scanner_assessment");

    if (savedAssessmentId) {
      setAssessmentId(savedAssessmentId);
      setBusy(true);
    }

    if (!getStoredToken()) return;

    let cancelled = false;

    getAccountEntitlements()
      .then((response) => {
        if (!cancelled) {
          setEntitlements(response.data);
        }
      })
      .catch(() => {
        if (!cancelled) {
          setEntitlements(null);
        }
      });

    return () => {
      cancelled = true;
    };
  }, []);

  useEffect(() => {
    if (!assessmentId) return;

    let cancelled = false;
    let timer: ReturnType<typeof setTimeout> | undefined;

    const poll = async () => {
      try {
        const response = await getAssessment(assessmentId);

        if (cancelled) return;

        const assessment = response.data;

        setStatus(assessment.status);
        setProgress(Number(assessment.progress ?? 0));

        if (
          assessment.status === "completed" ||
          assessment.status === "failed"
        ) {
          const responseFindings = Array.isArray(assessment.findings)
            ? assessment.findings
            : [];

          if (!cancelled) {
            setStatus(assessment.status);
            setProgress(Number(assessment.progress ?? 100));
            setFindings(responseFindings);
            setBusy(false);

            const reloadKey = `crypticx_scanner_reloaded_${assessmentId}`;
            const completionEventKey =
              `crypticx_ga_completed_${assessmentId}`;

            if (
              assessment.status === "completed" &&
              !window.sessionStorage.getItem(completionEventKey)
            ) {
              trackEvent("assessment_completed", {
                finding_count: responseFindings.length,
              });
              window.sessionStorage.setItem(completionEventKey, "1");
            }

            if (
              assessment.status === "completed" &&
              !window.sessionStorage.getItem(reloadKey)
            ) {
              window.sessionStorage.setItem(reloadKey, "1");
              window.sessionStorage.setItem(
                "crypticx_scanner_assessment",
                assessmentId,
              );
              window.location.reload();
              return;
            }
          }

          try {
            const [findingsResponse, entitlementsResponse] =
              await Promise.all([
                getFindings({
                  assessment_id: assessmentId,
                  per_page: 100,
                }),
                getAccountEntitlements().catch(() => null),
              ]);

            if (!cancelled) {
              setFindings(findingsResponse.data ?? responseFindings);

              if (entitlementsResponse) {
                setEntitlements(entitlementsResponse.data);
              }
            }
          } catch {
            // The assessment response already contains the terminal
            // state and loaded findings, so secondary refresh failure
            // must not prevent the completed result from rendering.
          }

          return;
        }

        timer = setTimeout(poll, 2000);
      } catch (pollError) {
        if (!cancelled) {
          setError(
            pollError instanceof Error
              ? pollError.message
              : "Unable to retrieve assessment status.",
          );
          setBusy(false);
        }
      }
    };

    poll();

    return () => {
      cancelled = true;
      if (timer) clearTimeout(timer);
    };
  }, [assessmentId]);

  async function startAssessment() {
    setError("");
    setFindings([]);

    if (!target.trim()) {
      setError("Enter a target URL.");
      return;
    }

    if (!authorized) {
      setError("You must confirm that you are authorized to test this target.");
      return;
    }

    if (!getStoredToken()) {
      setError("Please sign in before starting a security assessment.");
      return;
    }

    const assessmentLimit =
      entitlements?.limits.assessments_monthly ?? null;
    const assessmentsUsed =
      entitlements?.usage.assessments_monthly ?? 0;

    if (
      assessmentLimit !== null &&
      assessmentsUsed >= assessmentLimit
    ) {
      setQuotaModalOpen(true);
      return;
    }

    setBusy(true);
    setStatus("Creating target");
    setProgress(0);
    setAssessmentId(null);

    try {
      const targetResponse = await createTarget(
        `CrypticX Assessment — ${new URL(target.trim()).hostname}`,
        target.trim(),
        "user_confirmed",
      );

      setStatus("Queueing assessment");

      const assessmentResponse = await createAssessment(
        targetResponse.data.id,
        selectedType.profile,
      );

      trackEvent("assessment_started", {
        assessment_profile: selectedType.profile,
      });

      setAssessmentId(assessmentResponse.data.id);
      window.sessionStorage.setItem(
        "crypticx_scanner_assessment",
        assessmentResponse.data.id,
      );
      setStatus(assessmentResponse.data.status);
      setProgress(Number(assessmentResponse.data.progress ?? 0));

      try {
        const refreshed = await getAccountEntitlements();
        setEntitlements(refreshed.data);
      } catch {
        // Assessment creation already succeeded.
        // Backend quota enforcement remains authoritative.
      }
    } catch (startError) {
      setBusy(false);
      setStatus("Failed");

      if (
        startError instanceof ApiError &&
        startError.code === "ASSESSMENT_MONTHLY_LIMIT_REACHED"
      ) {
        setQuotaModalOpen(true);

        try {
          const refreshed = await getAccountEntitlements();
          setEntitlements(refreshed.data);
        } catch {
          // The server-side quota response remains authoritative.
        }

        return;
      }

      setError(
        startError instanceof Error
          ? startError.message
          : "Unable to start the assessment.",
      );
    }
  }

  const canStart =
    Boolean(target.trim()) &&
    authorized &&
    !busy &&
    !assessmentId;

  return (
    <main className="min-h-screen bg-[#09090b] text-white">
      <div className="mx-auto max-w-7xl px-5 sm:px-8">
        <header className="flex h-20 items-center justify-between">
          <Link href="/" className="flex items-center gap-3">
            <div className="flex h-11 w-11 items-center justify-center">
              <img
                src="/brand/crypticx2.png"
                alt="CrypticX Lab"
                width={48}
                height={48}
                className="h-11 w-11 object-contain"
              />
            </div>

            <div>
              <div className="text-lg font-bold tracking-tight">
                CrypticX Lab
              </div>
              <div className="text-[10px] font-semibold uppercase tracking-[0.24em] text-white/40">
                Security Intelligence
              </div>
            </div>
          </Link>

          <Link
            href="/"
            className="rounded-xl bg-[#09090b] px-4 py-2.5 text-sm font-semibold text-white/65 shadow-[5px_5px_10px_#000000,-5px_-5px_10px_#171719]"
          >
            ← Back home
          </Link>
        </header>

        <section className="py-14 sm:py-20">
          <div className="max-w-3xl">
            <p className="text-xs font-bold uppercase tracking-[0.22em] text-red-400">
              Security assessment
            </p>

            <h1 className="mt-3 text-4xl font-black tracking-tight sm:text-6xl">
              Analyze an authorized target.
            </h1>

            <p className="mt-5 max-w-2xl text-base leading-7 text-white/45">
              Run a controlled security assessment against infrastructure you
              own or have explicit permission to test.
            </p>
          </div>

          <div className="mt-12 grid gap-7 lg:grid-cols-[1fr_340px]">
            <section className="rounded-[2rem] bg-[#09090b] p-6 shadow-[8px_8px_18px_#000000,-8px_-8px_18px_#171719] sm:p-8">
              <div className="flex items-center justify-between">
                <div>
                  <h2 className="text-xl font-black">Assessment target</h2>
                  <p className="mt-1 text-sm text-red-400">
                    Enter a domain or URL you are authorized to assess.
                  </p>
                </div>

                <div className="hidden rounded-xl bg-[#09090b] px-3 py-2 text-xs font-bold text-emerald-600 shadow-[inset_2px_2px_5px_#020203,inset_-2px_-2px_5px_#171719] sm:block">
                  Scope protected
                </div>
              </div>

              <label className="mt-7 block text-xs font-bold uppercase tracking-widest text-white/45">
                Target URL
              </label>

              <input
                value={target}
                onChange={(event) => {
                  setTarget(event.target.value);
                  setError("");
                }}
                placeholder="https://example.com"
                disabled={busy}
                className="mt-2 h-14 w-full rounded-2xl border-0 bg-[#09090b] px-5 text-sm font-medium text-white shadow-[inset_4px_4px_9px_#020203,inset_-4px_-4px_9px_#171719] outline-none placeholder:text-white/25 focus:ring-2 focus:ring-red-500/30 disabled:opacity-60"
                type="url"
              />

              <div className="mt-8">
                <label className="text-xs font-bold uppercase tracking-widest text-white/45">
                  Assessment type
                </label>

                <div className="mt-3 grid gap-3 sm:grid-cols-2">
                  {scanTypes.map((type) => {
                    const active = selected === type.title;

                    return (
                      <button
                        key={type.title}
                        type="button"
                        disabled={busy}
                        onClick={() => setSelected(type.title)}
                        className={`rounded-2xl p-4 text-left transition disabled:opacity-60 ${
                          active
                            ? "shadow-[inset_4px_4px_9px_#020203,inset_-4px_-4px_9px_#171719]"
                            : "shadow-[6px_6px_12px_#000000,-6px_-6px_12px_#171719] hover:-translate-y-0.5"
                        }`}
                      >
                        <div className="flex items-center justify-between gap-3">
                          <span className="text-sm font-bold">
                            {type.title}
                          </span>

                          <span
                            className={`h-2.5 w-2.5 rounded-full ${
                              active ? "bg-[#526474]" : "bg-[#c1c9d0]"
                            }`}
                          />
                        </div>

                        <p className="mt-2 text-xs leading-5 text-red-400">
                          {type.description}
                        </p>
                      </button>
                    );
                  })}
                </div>
              </div>

              <label className="mt-8 flex cursor-pointer items-start gap-3 rounded-2xl bg-[#09090b] p-4 shadow-[inset_4px_4px_9px_#020203,inset_-4px_-4px_9px_#171719]">
                <input
                  type="checkbox"
                  checked={authorized}
                  disabled={busy}
                  onChange={(event) => setAuthorized(event.target.checked)}
                  className="mt-1 h-4 w-4"
                />

                <span className="text-xs leading-5 text-white/45">
                  I confirm that I own this target or have explicit
                  authorization to perform this security assessment.
                </span>
              </label>

              {error && (
                <div className="mt-5 rounded-2xl bg-red-50 p-4 text-sm font-semibold text-red-300">
                  {error}
                </div>
              )}

              <button
                type="button"
                disabled={!canStart}
                onClick={startAssessment}
                className="mt-7 w-full rounded-2xl bg-[#17212b] px-6 py-4 text-sm font-bold text-white shadow-[8px_8px_18px_#000000,-8px_-8px_18px_#171719] transition hover:-translate-y-0.5 disabled:cursor-not-allowed disabled:opacity-40"
              >
                {busy ? "Assessment running…" : "Start assessment"}
              </button>

              {assessmentId && (
                <div className="mt-7 rounded-2xl bg-[#09090b] p-5 shadow-[inset_4px_4px_9px_#020203,inset_-4px_-4px_9px_#171719]">
                  <div className="flex items-center justify-between">
                    <span className="text-xs font-bold uppercase tracking-widest text-red-400">
                      Assessment progress
                    </span>

                    <span className="text-sm font-black">
                      {progress}%
                    </span>
                  </div>

                  <div className="mt-3 h-3 overflow-hidden rounded-full bg-[#d4d9de]">
                    <div
                      className="h-full rounded-full bg-[#17212b] transition-all duration-500"
                      style={{ width: `${Math.max(0, Math.min(progress, 100))}%` }}
                    />
                  </div>

                  <div className="mt-3 flex items-center justify-between text-xs text-red-400">
                    <span>{status}</span>
                    <span className="truncate pl-4">
                      {assessmentId}
                    </span>
                  </div>
                </div>
              )}

              {assessmentId && status === "completed" && (
                <div className="mt-8">
                  <div className="flex items-end justify-between gap-4">
                    <div>
                      <p className="text-xs font-bold uppercase tracking-widest text-red-400">
                        Real scan results
                      </p>
                      <h2 className="mt-1 text-2xl font-black">
                        {findings.length} findings detected
                      </h2>
                    </div>
                  </div>

                  <div className="mt-5 space-y-3">
                    {findings.map((finding) => (
                      <article
                        key={finding.id}
                        className="rounded-2xl bg-[#09090b] p-5 shadow-[5px_5px_12px_#000000,-5px_-5px_12px_#171719]"
                      >
                        <div className="flex flex-wrap items-center justify-between gap-3">
                          <h3 className="text-sm font-black">
                            {finding.title}
                          </h3>

                          <span
                            className={`rounded-full px-3 py-1 text-[10px] font-black uppercase tracking-wider ${severityClass(
                              finding.severity,
                            )}`}
                          >
                            {finding.severity}
                          </span>
                        </div>

                        <p className="mt-2 text-xs leading-5 text-white/45">
                          {finding.description}
                        </p>

                        <div className="mt-3 text-[10px] font-bold uppercase tracking-wider text-white/25">
                          Confidence: {finding.confidence} · Status:{" "}
                          {finding.status}
                        </div>
                      </article>
                    ))}
                  </div>
                </div>
              )}

              {assessmentId && status === "completed" && findings.length === 0 && (
                <div className="mt-8 rounded-2xl bg-emerald-50 p-5 text-sm font-semibold text-emerald-300">
                  Assessment completed successfully. No findings were returned
                  for this assessment.
                </div>
              )}
            </section>

            <aside className="space-y-6">
              <div className="rounded-[2rem] bg-[#09090b] p-6 shadow-[8px_8px_18px_#000000,-8px_-8px_18px_#171719]">
                <p className="text-xs font-bold uppercase tracking-[0.2em] text-red-400">
                  Assessment
                </p>

                <h3 className="mt-2 text-xl font-black">
                  {busy ? "Analyzing target" : "Ready to analyze"}
                </h3>

                <div className="mt-6 space-y-4">
                  {[
                    ["Target", target || "Not selected"],
                    ["Type", selected],
                    ["Profile", selectedType.profile],
                    ["Status", status],
                    ["Findings", String(findings.length)],
                  ].map(([label, value]) => (
                    <div key={label}>
                      <div className="text-[10px] font-bold uppercase tracking-widest text-[#84909a]">
                        {label}
                      </div>

                      <div className="mt-1 break-all text-sm font-semibold">
                        {value}
                      </div>
                    </div>
                  ))}
                </div>
              </div>

              <div className="rounded-[2rem] bg-[#17212b] p-6 text-white shadow-[10px_10px_22px_#000000,-8px_-8px_18px_#171719]">
                <p className="text-xs font-bold uppercase tracking-[0.2em] text-[#aeb9c2]">
                  Workflow
                </p>

                <div className="mt-5 space-y-4">
                  {[
                    "Discover attack surface",
                    "Analyze security signals",
                    "Prioritize findings",
                    "Verify remediation",
                  ].map((item, index) => (
                    <div key={item} className="flex items-center gap-3">
                      <span className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-white/10 text-[10px] font-bold">
                        {index + 1}
                      </span>

                      <span className="text-sm text-[#c4ccd2]">
                        {item}
                      </span>
                    </div>
                  ))}
                </div>
              </div>
            </aside>
          </div>
        </section>
      </div>

      {quotaModalOpen && (
        <div
          className="fixed inset-0 z-50 flex items-center justify-center bg-black/75 px-5 backdrop-blur-sm"
          role="dialog"
          aria-modal="true"
          aria-labelledby="quota-modal-title"
        >
          <div className="w-full max-w-md rounded-[2rem] bg-[#09090b] p-7 shadow-[12px_12px_30px_#000000,-8px_-8px_24px_#171719]">
            <p className="text-xs font-bold uppercase tracking-[0.2em] text-red-400">
              Plan limit
            </p>

            <h2
              id="quota-modal-title"
              className="mt-3 text-2xl font-black"
            >
              Monthly scan limit reached
            </h2>

            <p className="mt-4 text-sm leading-6 text-white/55">
              You have used{" "}
              <strong className="text-white">
                {entitlements?.usage.assessments_monthly ?? "all"}
              </strong>{" "}
              of{" "}
              <strong className="text-white">
                {entitlements?.limits.assessments_monthly ?? "your"}
              </strong>{" "}
              assessments included in your current plan this month.
              Your monthly quota resets at the start of the next
              calendar month.
            </p>

            <div className="mt-7 grid gap-3 sm:grid-cols-2">
              <button
                type="button"
                onClick={() => setQuotaModalOpen(false)}
                className="rounded-2xl bg-[#09090b] px-5 py-3 text-sm font-bold text-white/65 shadow-[5px_5px_10px_#000000,-5px_-5px_10px_#171719]"
              >
                Close
              </button>

              <Link
                href="/pricing"
                className="rounded-2xl bg-[#17212b] px-5 py-3 text-center text-sm font-bold text-white shadow-[6px_6px_14px_#000000,-5px_-5px_12px_#171719]"
              >
                View plans
              </Link>
            </div>
          </div>
        </div>
      )}
    </main>
  );
}
