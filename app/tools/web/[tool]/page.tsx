"use client";

import Link from "next/link";
import { FormEvent, useState } from "react";
import { useParams } from "next/navigation";
import {
  getStoredToken,
  runWebSecurityAnalysis,
} from "@/lib/api";

type UnknownRecord = Record<string, unknown>;

function isRecord(value: unknown): value is UnknownRecord {
  return typeof value === "object" && value !== null;
}

function str(value: unknown): string {
  return typeof value === "string" ? value : "—";
}

const tools = {
  "security-headers": {
    title: "Security Headers",
    description:
      "Inspect important HTTP response security headers.",
  },
  "http-analysis": {
    title: "HTTP Analysis",
    description:
      "Inspect HTTP status, response metadata, server information and timing.",
  },
  "cookie-security": {
    title: "Cookie Security",
    description:
      "Review Secure, HttpOnly and SameSite cookie attributes.",
  },
  "cors-review": {
    title: "CORS Review",
    description:
      "Inspect cross-origin resource sharing response configuration.",
  },
} as const;

type ToolSlug = keyof typeof tools;

export default function WebSecurityToolPage() {
  const params = useParams();
  const slug = String(params?.tool ?? "") as ToolSlug;
  const config = tools[slug];

  const [url, setUrl] = useState("");
  const [data, setData] = useState<UnknownRecord | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState("");

  if (!config) {
    return (
      <main className="min-h-screen bg-[#09090b] p-8">
        <p>Unknown tool.</p>
      </main>
    );
  }

  async function submit(event: FormEvent) {
    event.preventDefault();

    if (!getStoredToken()) {
      window.location.href = "/login";
      return;
    }

    if (!url.trim()) {
      setError("Enter a website URL or hostname.");
      return;
    }

    try {
      setLoading(true);
      setError("");
      setData(null);

      const response = await runWebSecurityAnalysis(url.trim());

      if (!isRecord(response) || !isRecord(response.data)) {
        throw new Error("Invalid analysis response.");
      }

      setData(response.data);
    } catch (err) {
      setError(
        err instanceof Error
          ? err.message
          : "Analysis failed."
      );
    } finally {
      setLoading(false);
    }
  }

  const http = data && isRecord(data.http) ? data.http : null;
  const cors = data && isRecord(data.cors) ? data.cors : null;

  const securityHeaders =
    data && Array.isArray(data.security_headers)
      ? data.security_headers.filter(isRecord)
      : [];

  const cookies =
    data && Array.isArray(data.cookies)
      ? data.cookies.filter(isRecord)
      : [];

  return (
    <main className="min-h-screen bg-[#09090b] text-white">
      <div className="mx-auto max-w-5xl px-5 py-10 sm:px-8">
        <div className="flex justify-between gap-4">
          <Link
            href="/tools"
            className="text-sm font-bold text-white/55"
          >
            ← All Tools
          </Link>

          <Link
            href="/dashboard"
            className="text-sm font-bold text-white/55"
          >
            Dashboard
          </Link>
        </div>

        <section className="mt-10">
          <p className="text-xs font-bold uppercase tracking-[0.22em] text-red-400">
            Web Security
          </p>

          <h1 className="mt-3 text-4xl font-black sm:text-5xl">
            {config.title}
          </h1>

          <p className="mt-4 text-white/45">
            {config.description}
          </p>

          <form
            onSubmit={submit}
            className="mt-8 rounded-[2rem] bg-[#09090b] p-6 shadow-[8px_8px_18px_#000000,-8px_-8px_18px_#171719]"
          >
            <label className="text-sm font-bold">
              Target URL
            </label>

            <div className="mt-3 flex flex-col gap-3 sm:flex-row">
              <input
                value={url}
                onChange={(e) => setUrl(e.target.value)}
                placeholder="https://example.com"
                className="cx-input flex-1"
              />

              <button
                disabled={loading}
                className="cx-button cx-button-primary px-6 disabled:opacity-50"
              >
                {loading ? "Analyzing..." : "Run Analysis"}
              </button>
            </div>

            {error && (
              <div className="mt-4 rounded-xl bg-red-500/10 px-4 py-3 text-sm font-semibold text-red-300">
                {error}
              </div>
            )}
          </form>
        </section>

        {data && (
          <section className="mt-8 space-y-5">
            <div className="cx-card p-6">
              <p className="text-xs font-bold uppercase tracking-wider text-red-400">
                Target
              </p>
              <p className="mt-2 break-all font-bold">
                {str(data.url)}
              </p>
            </div>

            {slug === "http-analysis" && http && (
              <div className="grid gap-4 sm:grid-cols-2">
                {[
                  ["Status", http.status],
                  ["Duration", `${String(http.duration_ms ?? "—")} ms`],
                  ["Content Type", http.content_type],
                  ["Content Length", http.content_length],
                  ["Server", http.server],
                  ["X-Powered-By", http.powered_by],
                  ["Redirect", http.location],
                ].map(([label, value]) => (
                  <div key={String(label)} className="cx-card p-5">
                    <p className="text-xs font-bold uppercase text-red-400">
                      {String(label)}
                    </p>
                    <p className="mt-2 break-all font-semibold">
                      {value === null || value === undefined
                        ? "—"
                        : String(value)}
                    </p>
                  </div>
                ))}
              </div>
            )}

            {slug === "security-headers" && (
              <div className="space-y-3">
                {securityHeaders.map((header, index) => (
                  <div
                    key={index}
                    className="cx-card flex flex-col justify-between gap-3 p-5 sm:flex-row"
                  >
                    <div>
                      <p className="font-bold">
                        {str(header.name)}
                      </p>
                      <p className="mt-1 break-all text-sm text-white/45">
                        {header.value === null
                          ? "Header not present"
                          : String(header.value)}
                      </p>
                    </div>

                    <span
                      className={`self-start rounded-full px-3 py-1 text-xs font-bold ${
                        header.present
                          ? "bg-emerald-500/10 text-emerald-300"
                          : "bg-red-500/10 text-red-300"
                      }`}
                    >
                      {header.present ? "Present" : "Missing"}
                    </span>
                  </div>
                ))}
              </div>
            )}

            {slug === "cookie-security" && (
              <div className="space-y-3">
                {cookies.length === 0 ? (
                  <div className="cx-card p-6 text-sm text-white/45">
                    No Set-Cookie headers were returned.
                  </div>
                ) : (
                  cookies.map((cookie, index) => (
                    <div key={index} className="cx-card p-5">
                      <p className="font-black">
                        {str(cookie.name)}
                      </p>

                      <div className="mt-4 grid gap-3 sm:grid-cols-3">
                        <div>
                          <p className="text-xs text-red-400">
                            Secure
                          </p>
                          <p className="font-bold">
                            {cookie.secure ? "Yes" : "No"}
                          </p>
                        </div>

                        <div>
                          <p className="text-xs text-red-400">
                            HttpOnly
                          </p>
                          <p className="font-bold">
                            {cookie.http_only ? "Yes" : "No"}
                          </p>
                        </div>

                        <div>
                          <p className="text-xs text-red-400">
                            SameSite
                          </p>
                          <p className="font-bold">
                            {cookie.same_site
                              ? String(cookie.same_site)
                              : "Not set"}
                          </p>
                        </div>
                      </div>
                    </div>
                  ))
                )}
              </div>
            )}

            {slug === "cors-review" && cors && (
              <div className="grid gap-4 sm:grid-cols-2">
                {[
                  ["Allow Origin", cors.allow_origin],
                  ["Allow Credentials", cors.allow_credentials],
                  ["Allow Methods", cors.allow_methods],
                  ["Allow Headers", cors.allow_headers],
                  [
                    "Wildcard Origin",
                    cors.wildcard_origin ? "Yes" : "No",
                  ],
                  [
                    "Credentialed",
                    cors.credentialed ? "Yes" : "No",
                  ],
                ].map(([label, value]) => (
                  <div key={String(label)} className="cx-card p-5">
                    <p className="text-xs font-bold uppercase text-red-400">
                      {String(label)}
                    </p>
                    <p className="mt-2 break-all font-semibold">
                      {value === null || value === undefined
                        ? "—"
                        : String(value)}
                    </p>
                  </div>
                ))}
              </div>
            )}
          </section>
        )}
      </div>
    </main>
  );
}
