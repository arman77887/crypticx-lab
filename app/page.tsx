"use client";

import Link from "next/link";
import { useEffect, useMemo, useState } from "react";
import {
  DashboardSummaryResponse,
  getDashboardSummary,
  getStoredToken,
} from "@/lib/api";

const tools = [
  {
    icon: "◎",
    title: "Web Security",
    text: "Assess headers, cookies, CORS, HTTP behavior and security posture.",
    href: "/tools",
  },
  {
    icon: "⚠",
    title: "Phishing Link Analyzer",
    text: "Inspect suspicious links for static phishing, deception and URL obfuscation indicators without visiting them.",
    href: "/tools/web/phishing-link-analyzer",
  },
  {
    icon: "⌁",
    title: "Network Analysis",
    text: "Inspect authorized network exposure with bounded, defensive analysis.",
    href: "/tools",
  },
  {
    icon: "◌",
    title: "DNS Intelligence",
    text: "Inspect records, DNS health and authorized subdomain intelligence.",
    href: "/tools",
  },
  {
    icon: "◇",
    title: "SSL / TLS",
    text: "Review certificates, protocol posture, ciphers and chain information.",
    href: "/tools",
  },
  {
    icon: "⌘",
    title: "API Security",
    text: "Analyze safe API security signals without state-changing requests.",
    href: "/tools",
  },
  {
    icon: "▣",
    title: "Data Lab",
    text: "Analyze structured datasets and security-oriented data patterns.",
    href: "/tools",
  },
];

const workflow = [
  ["01", "Discover", "Map authorized assets and collect security signals."],
  ["02", "Analyze", "Prioritize findings using deterministic risk intelligence."],
  ["03", "Fix", "Apply remediation guidance to reduce security exposure."],
  ["04", "Verify", "Reassess and confirm whether the risk was reduced."],
];

function riskClass(level?: string) {
  switch ((level || "").toLowerCase()) {
    case "critical":
      return "border-red-500/40 bg-red-500/10 text-red-300";
    case "high":
      return "border-orange-500/40 bg-orange-500/10 text-orange-300";
    case "medium":
      return "border-amber-500/40 bg-amber-500/10 text-amber-300";
    case "low":
      return "border-sky-500/40 bg-sky-500/10 text-sky-300";
    default:
      return "border-white/10 bg-white/5 text-white/60";
  }
}

function label(value?: string) {
  if (!value) return "Unknown";
  return value.charAt(0).toUpperCase() + value.slice(1);
}

export default function Home() {
  const [summary, setSummary] =
    useState<DashboardSummaryResponse["data"] | null>(null);

  const [authChecked, setAuthChecked] = useState(false);
  const [hasToken, setHasToken] = useState(false);

  useEffect(() => {
    let mounted = true;
    let refreshTimer: ReturnType<typeof setInterval> | null = null;

    async function loadLiveRisk() {
      const token = getStoredToken();

      if (mounted) {
        setHasToken(Boolean(token));
      }

      if (!token) {
        if (mounted) {
          setSummary(null);
          setAuthChecked(true);
        }
        return;
      }

      try {
        const response = await getDashboardSummary();

        if (mounted) {
          setSummary(response.data);
        }
      } catch {
        if (mounted) {
          setSummary(null);
        }
      } finally {
        if (mounted) {
          setAuthChecked(true);
        }
      }
    }

    loadLiveRisk();

    if (getStoredToken()) {
      refreshTimer = setInterval(() => {
        void loadLiveRisk();
      }, 30000);
    }

    return () => {
      mounted = false;

      if (refreshTimer) {
        clearInterval(refreshTimer);
      }
    };
  }, []);

  const latestAssessment = useMemo(
    () => summary?.recent_assessments?.[0] ?? null,
    [summary],
  );

  const riskScore = summary?.overall_risk?.score ?? 0;
  const currentFindings = summary?.findings?.current ?? 0;
  const criticalHigh = summary?.findings?.critical_or_high ?? 0;

  return (
    <main className="min-h-screen overflow-hidden bg-[#050505] text-white">
      <style jsx global>{`
        html {
          scroll-behavior: smooth;
        }

        body {
          background:
            radial-gradient(circle at 20% 0%, rgba(127, 0, 0, 0.2), transparent 30%),
            radial-gradient(circle at 85% 15%, rgba(255, 0, 0, 0.12), transparent 25%),
            #050505;
        }

        @keyframes cxFloat {
          0%,
          100% {
            transform: translate3d(0, 0, 0) rotateX(0deg) rotateY(0deg);
          }
          50% {
            transform: translate3d(0, -12px, 0) rotateX(2deg) rotateY(-2deg);
          }
        }

        @keyframes cxPulse {
          0%,
          100% {
            opacity: 0.35;
            transform: scale(0.96);
          }
          50% {
            opacity: 0.85;
            transform: scale(1.04);
          }
        }

        @keyframes cxScan {
          0% {
            transform: translateY(-110%);
            opacity: 0;
          }
          10% {
            opacity: 1;
          }
          90% {
            opacity: 1;
          }
          100% {
            transform: translateY(650%);
            opacity: 0;
          }
        }

        @keyframes cxGrid {
          0% {
            background-position: 0 0;
          }
          100% {
            background-position: 0 42px;
          }
        }

        @keyframes cxRotate {
          to {
            transform: rotate(360deg);
          }
        }

        @keyframes cxGlow {
          0%,
          100% {
            box-shadow:
              0 0 0 rgba(255, 0, 0, 0),
              inset 0 0 25px rgba(255, 0, 0, 0.04);
          }
          50% {
            box-shadow:
              0 0 45px rgba(255, 0, 0, 0.12),
              inset 0 0 45px rgba(255, 0, 0, 0.08);
          }
        }

        .cx-grid {
          background-image:
            linear-gradient(rgba(255, 255, 255, 0.022) 1px, transparent 1px),
            linear-gradient(90deg, rgba(255, 255, 255, 0.022) 1px, transparent 1px);
          background-size: 42px 42px;
          animation: cxGrid 8s linear infinite;
        }

        .cx-card-3d {
          background:
            linear-gradient(145deg, rgba(20, 20, 20, 0.96), rgba(7, 7, 7, 0.98));
          border: 1px solid rgba(255, 255, 255, 0.07);
          box-shadow:
            18px 18px 45px rgba(0, 0, 0, 0.6),
            -8px -8px 30px rgba(255, 255, 255, 0.015),
            inset 0 1px 0 rgba(255, 255, 255, 0.04);
        }

        .cx-panel-inset {
          background: #090909;
          box-shadow:
            inset 7px 7px 18px rgba(0, 0, 0, 0.8),
            inset -4px -4px 14px rgba(255, 255, 255, 0.02);
        }

        .cx-float {
          animation: cxFloat 6s ease-in-out infinite;
        }

        .cx-glow {
          animation: cxGlow 4s ease-in-out infinite;
        }
      `}</style>

      <div className="pointer-events-none fixed inset-0 cx-grid opacity-60" />

      <div className="pointer-events-none fixed left-[-120px] top-[-80px] h-[420px] w-[420px] rounded-full bg-red-700/10 blur-[120px]" />
      <div className="pointer-events-none fixed right-[-100px] top-[200px] h-[320px] w-[320px] rounded-full bg-red-600/10 blur-[120px]" />

      <div className="relative z-10 mx-auto max-w-7xl px-5 sm:px-8">

        <section className="relative py-20 sm:py-28 lg:py-32">
          <div className="grid items-center gap-16 lg:grid-cols-[1.05fr_.95fr]">
            <div>
              <div className="inline-flex items-center gap-3 rounded-full border border-red-500/20 bg-red-500/[0.06] px-4 py-2 text-xs font-bold uppercase tracking-[0.18em] text-red-300 shadow-[0_0_30px_rgba(255,0,0,0.06)]">
                <span className="relative flex h-2.5 w-2.5">
                  <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-red-500 opacity-50" />
                  <span className="relative inline-flex h-2.5 w-2.5 rounded-full bg-red-500" />
                </span>
                Authorized Security Intelligence
              </div>

              <h1 className="mt-7 max-w-4xl text-5xl font-black leading-[0.97] tracking-[-0.05em] sm:text-6xl lg:text-8xl">
                SEE THE
                <br />
                <span className="bg-gradient-to-r from-white via-red-200 to-red-600 bg-clip-text text-transparent">
                  RISK.
                </span>
                <br />
                CONTROL IT.
              </h1>

              <p className="mt-7 max-w-2xl text-base leading-7 text-white/55 sm:text-lg">
                CrypticX Lab unifies authorized security assessment,
                reconnaissance, risk intelligence and defensive analysis into
                one focused security workspace.
              </p>

              <div className="mt-9 flex flex-col gap-3 sm:flex-row">
                <Link
                  href={hasToken ? "/scanner" : "/login"}
                  className="rounded-2xl border border-red-500/40 bg-red-600 px-6 py-4 text-center text-sm font-black text-white shadow-[0_15px_50px_rgba(180,0,0,0.25)] transition duration-300 hover:-translate-y-1 hover:bg-red-500"
                >
                  Start Assessment
                </Link>

                <Link
                  href="/tools"
                  className="rounded-2xl border border-white/10 bg-white/[0.035] px-6 py-4 text-center text-sm font-bold text-white/80 backdrop-blur-xl transition duration-300 hover:-translate-y-1 hover:border-red-500/30 hover:text-white"
                >
                  Explore Security Tools
                </Link>
              </div>

              <div className="mt-10 flex flex-wrap gap-x-8 gap-y-4 text-xs uppercase tracking-[0.16em] text-white/35">
                <span>Authorized Scope</span>
                <span>Deterministic Risk</span>
                <span>Evidence Driven</span>
              </div>
            </div>

            <div className="relative perspective-[1400px]">
              <div className="cx-float cx-card-3d relative overflow-hidden rounded-[2rem] p-5 sm:p-7">
                <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_75%_10%,rgba(255,0,0,0.12),transparent_28%)]" />

                <div className="relative">
                  <div className="flex items-center justify-between gap-4">
                    <div>
                      <p className="text-[10px] font-black uppercase tracking-[0.24em] text-red-400">
                        CrypticX Intelligence
                      </p>
                      <h2 className="mt-2 text-lg font-bold">
                        {summary ? "Your Security Posture" : "Security Preview"}
                      </h2>
                    </div>

                    <div
                      className={`rounded-xl border px-3 py-2 text-[10px] font-black uppercase tracking-widest ${
                        summary
                          ? riskClass(summary.overall_risk.level)
                          : "border-red-500/20 bg-red-500/5 text-red-300"
                      }`}
                    >
                      {summary
                        ? label(summary.overall_risk.level)
                        : authChecked
                          ? "Preview"
                          : "Loading"}
                    </div>
                  </div>

                  <div className="mt-8 grid gap-6 sm:grid-cols-[170px_1fr] sm:items-center">
                    <div className="relative mx-auto flex h-40 w-40 items-center justify-center">
                      <div
                        className="absolute inset-0 rounded-full border border-red-500/20"
                        style={{ animation: "cxPulse 3s ease-in-out infinite" }}
                      />

                      <div
                        className="absolute inset-3 rounded-full border border-red-500/10 border-t-red-500"
                        style={{ animation: "cxRotate 6s linear infinite" }}
                      />

                      <div className="cx-panel-inset flex h-28 w-28 flex-col items-center justify-center rounded-full border border-white/5">
                        <span className="text-4xl font-black tracking-tight">
                          {summary ? riskScore : "—"}
                        </span>
                        <span className="mt-1 text-[9px] font-bold uppercase tracking-[0.18em] text-white/35">
                          Risk / 100
                        </span>
                      </div>
                    </div>

                    <div className="space-y-3">
                      {[
                        [
                          "Active Targets",
                          summary?.targets.active ?? "—",
                        ],
                        [
                          "Current Findings",
                          summary ? currentFindings : "—",
                        ],
                        [
                          "Critical / High",
                          summary ? criticalHigh : "—",
                        ],
                        [
                          "Latest Scan Risk",
                          latestAssessment
                            ? `${latestAssessment.risk_score}/100`
                            : "—",
                        ],
                      ].map(([item, value]) => (
                        <div
                          key={String(item)}
                          className="cx-panel-inset flex items-center justify-between rounded-2xl border border-white/[0.04] px-4 py-3"
                        >
                          <span className="text-xs text-white/45">
                            {item}
                          </span>
                          <span className="text-sm font-black text-white">
                            {value}
                          </span>
                        </div>
                      ))}
                    </div>
                  </div>

                  <div className="relative mt-7 overflow-hidden rounded-2xl border border-white/[0.05] bg-black/30 p-4">
                    <div
                      className="pointer-events-none absolute left-0 right-0 h-px bg-gradient-to-r from-transparent via-red-500 to-transparent shadow-[0_0_15px_rgba(255,0,0,.8)]"
                      style={{ animation: "cxScan 4.5s linear infinite" }}
                    />

                    <div className="flex items-center justify-between gap-4">
                      <div>
                        <p className="text-[10px] font-bold uppercase tracking-[0.17em] text-white/30">
                          Signal Source
                        </p>
                        <p className="mt-1 text-xs font-semibold text-white/65">
                          {summary
                            ? "Current finding lifecycle intelligence"
                            : "Authorized assessment pipeline"}
                        </p>
                      </div>

                      <div className="text-right">
                        <p className="text-[10px] text-white/30">
                          Status
                        </p>
                        <p className="mt-1 text-xs font-bold text-red-300">
                          {summary
                            ? "LIVE"
                            : authChecked
                              ? "SIGN IN"
                              : "CONNECTING"}
                        </p>
                      </div>
                    </div>

                    <div className="mt-4 h-1.5 overflow-hidden rounded-full bg-white/[0.05]">
                      <div
                        className="h-full rounded-full bg-gradient-to-r from-red-950 via-red-600 to-red-400 shadow-[0_0_14px_rgba(255,0,0,.5)] transition-all duration-700"
                        style={{
                          width: summary
                            ? `${Math.max(
                                4,
                                Math.min(riskScore, 100),
                              )}%`
                            : "0%",
                        }}
                      />
                    </div>
                  </div>

                  {summary && (
                    <div className="mt-5 flex flex-wrap gap-2">
                      <Link
                        href="/dashboard"
                        className="rounded-xl border border-red-500/20 bg-red-500/[0.06] px-4 py-2 text-xs font-bold text-red-200 transition hover:bg-red-500/10"
                      >
                        Open Dashboard
                      </Link>

                      <Link
                        href="/findings"
                        className="rounded-xl border border-white/10 bg-white/[0.03] px-4 py-2 text-xs font-bold text-white/60 transition hover:text-white"
                      >
                        View Findings
                      </Link>
                    </div>
                  )}
                </div>
              </div>
            </div>
          </div>
        </section>

        <section id="tools" className="py-20">
          <div className="mb-12 flex flex-col justify-between gap-5 md:flex-row md:items-end">
            <div>
              <p className="text-xs font-black uppercase tracking-[0.22em] text-red-400">
                Offensive Visibility. Defensive Control.
              </p>
              <h2 className="mt-3 text-3xl font-black tracking-tight sm:text-5xl">
                Security tools without the clutter.
              </h2>
            </div>

            <p className="max-w-lg text-sm leading-7 text-white/45">
              Purpose-built modules for authorized analysis, evidence
              collection and practical defensive security workflows.
            </p>
          </div>

          <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            {tools.map((tool, index) => (
              <Link
                key={tool.title}
                href={tool.href}
                className="cx-card-3d group relative overflow-hidden rounded-3xl p-6 transition duration-500 hover:-translate-y-2 hover:border-red-500/25"
              >
                <div className="pointer-events-none absolute right-[-50px] top-[-50px] h-32 w-32 rounded-full bg-red-500/0 blur-3xl transition duration-500 group-hover:bg-red-500/10" />

                <div className="flex items-center justify-between">
                  <div className="cx-panel-inset flex h-12 w-12 items-center justify-center rounded-2xl border border-white/[0.05] text-xl font-black text-red-400">
                    {tool.icon}
                  </div>

                  <span className="text-[10px] font-black tracking-[0.18em] text-white/20">
                    0{index + 1}
                  </span>
                </div>

                <h3 className="mt-8 text-xl font-black">
                  {tool.title}
                </h3>

                <p className="mt-3 text-sm leading-6 text-white/45">
                  {tool.text}
                </p>

                <div className="mt-7 text-xs font-black uppercase tracking-[0.17em] text-red-400/70 transition group-hover:text-red-300">
                  Open Module →
                </div>
              </Link>
            ))}
          </div>
        </section>

        <section className="py-20">
          <div className="cx-card-3d relative overflow-hidden rounded-[2rem] p-7 sm:p-10">
            <div className="pointer-events-none absolute inset-0 bg-[linear-gradient(115deg,transparent,rgba(255,0,0,.025),transparent)]" />

            <div className="relative max-w-3xl">
              <p className="text-xs font-black uppercase tracking-[0.22em] text-red-400">
                Security Lifecycle
              </p>

              <h2 className="mt-3 text-3xl font-black tracking-tight sm:text-5xl">
                From signal to verified reduction.
              </h2>
            </div>

            <div className="relative mt-10 grid gap-5 md:grid-cols-4">
              {workflow.map(([number, title, text]) => (
                <div
                  key={number}
                  className="group rounded-3xl border border-white/[0.06] bg-black/20 p-5 transition duration-500 hover:-translate-y-2 hover:border-red-500/20"
                >
                  <div className="text-xs font-black tracking-[0.2em] text-red-500">
                    {number}
                  </div>

                  <div className="mt-10 h-px w-10 bg-red-500/40 transition-all duration-500 group-hover:w-20" />

                  <h3 className="mt-5 text-xl font-black">
                    {title}
                  </h3>

                  <p className="mt-3 text-sm leading-6 text-white/40">
                    {text}
                  </p>
                </div>
              ))}
            </div>
          </div>
        </section>

        <section className="py-20">
          <div className="grid gap-6 lg:grid-cols-[1.2fr_.8fr]">
            <div className="relative overflow-hidden rounded-[2rem] border border-red-500/15 bg-gradient-to-br from-[#180202] via-[#0c0808] to-black p-8 shadow-[0_25px_80px_rgba(100,0,0,.18)] sm:p-10">
              <div className="pointer-events-none absolute right-[-80px] top-[-80px] h-64 w-64 rounded-full bg-red-500/10 blur-[90px]" />

              <p className="text-xs font-black uppercase tracking-[0.22em] text-red-400">
                Security Labs
              </p>

              <h2 className="mt-4 max-w-3xl text-3xl font-black tracking-tight sm:text-5xl">
                Understand the signal before you trust the result.
              </h2>

              <p className="mt-6 max-w-2xl text-sm leading-7 text-white/45">
                Controlled labs, defensive concepts and reproducible workflows
                for understanding security behavior without unsafe testing.
              </p>

              <Link
                href="/labs"
                className="mt-8 inline-flex rounded-2xl border border-red-500/30 bg-red-500/10 px-5 py-3 text-sm font-bold text-red-200 transition hover:bg-red-500/20"
              >
                Enter Security Labs
              </Link>
            </div>

            <div className="cx-card-3d rounded-[2rem] p-8">
              <p className="text-xs font-black uppercase tracking-[0.22em] text-red-400">
                Open Source
              </p>

              <h3 className="mt-4 text-3xl font-black">
                Transparent by design.
              </h3>

              <p className="mt-5 text-sm leading-7 text-white/45">
                Documented workflows, evidence-driven results and a platform
                designed around authorized security research and defensive use.
              </p>

              <Link
                href="/docs"
                className="mt-8 inline-block text-sm font-black text-red-300"
              >
                Read Documentation →
              </Link>
            </div>
          </div>
        </section>

        <section className="py-20">
          <div className="cx-glow relative overflow-hidden rounded-[2rem] border border-red-500/15 bg-[#080808] px-7 py-14 text-center sm:px-12 sm:py-16">
            <div className="pointer-events-none absolute left-1/2 top-0 h-40 w-72 -translate-x-1/2 rounded-full bg-red-500/10 blur-[80px]" />

            <div className="relative">
              <p className="text-xs font-black uppercase tracking-[0.22em] text-red-400">
                Ready To Assess
              </p>

              <h2 className="mx-auto mt-4 max-w-4xl text-3xl font-black tracking-tight sm:text-5xl">
                Turn security data into decisions.
              </h2>

              <p className="mx-auto mt-5 max-w-2xl text-sm leading-7 text-white/45">
                Register an authorized target, run an assessment, prioritize
                findings and verify remediation from one workspace.
              </p>

              <div className="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
                <Link
                  href={hasToken ? "/scanner" : "/login"}
                  className="rounded-2xl bg-red-600 px-7 py-4 text-sm font-black text-white shadow-[0_15px_45px_rgba(180,0,0,.25)] transition hover:-translate-y-1 hover:bg-red-500"
                >
                  Start Assessment
                </Link>

                {summary && (
                  <Link
                    href="/dashboard"
                    className="rounded-2xl border border-white/10 bg-white/[0.03] px-7 py-4 text-sm font-bold text-white/70 transition hover:-translate-y-1 hover:text-white"
                  >
                    Open Dashboard
                  </Link>
                )}
              </div>
            </div>
          </div>
        </section>

        <footer className="border-t border-white/[0.06] py-10">
          <div className="flex flex-col justify-between gap-6 text-sm text-white/35 sm:flex-row sm:items-center">
            <div>
              <div className="font-black tracking-wide text-white">
                CRYPTICX LAB
              </div>
              <div className="mt-1 text-xs">
                Security intelligence for authorized testing.
              </div>
            </div>

            <div className="flex flex-wrap gap-5">
              <Link href="/tools" className="hover:text-red-300">
                Tools
              </Link>
              <Link href="/docs" className="hover:text-red-300">
                Docs
              </Link>
              <Link href="/pricing" className="hover:text-red-300">
                Pricing
              </Link>
              <Link href="/about" className="hover:text-red-300">
                About
              </Link>
              <Link href="/contact" className="hover:text-red-300">
                Contact
              </Link>
            </div>
          </div>
        </footer>
      </div>
    </main>
  );
}
