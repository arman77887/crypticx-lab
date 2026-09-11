"use client";

import Link from "next/link";
import { FormEvent, useEffect, useState } from "react";
import { useParams } from "next/navigation";
import {
  ApiTarget,
  getStoredToken,
  getTargets,
  runApiSecurityAnalysis,
} from "@/lib/api";

type Obj = Record<string, unknown>;

function isObj(value: unknown): value is Obj {
  return typeof value === "object" && value !== null;
}

function show(value: unknown): string {
  if (value === null || value === undefined || value === "") {
    return "—";
  }

  if (typeof value === "boolean") {
    return value ? "Yes" : "No";
  }

  if (Array.isArray(value)) {
    return value.length ? value.join(", ") : "None advertised";
  }

  return String(value);
}

const tools = {
  "endpoint-inspector": {
    title: "Endpoint Inspector",
    description:
      "Inspect response status, media type, timing, redirects and endpoint metadata.",
  },

  "http-method-review": {
    title: "HTTP Method Review",
    description:
      "Review methods advertised through Allow and CORS metadata without sending destructive requests.",
  },

  "security-headers": {
    title: "API Security Headers",
    description:
      "Inspect important HTTP security headers returned by an API endpoint.",
  },

  "api-configuration": {
    title: "API Configuration",
    description:
      "Review transport, caching, authentication hints, CORS and information disclosure.",
  },
} as const;

type ToolSlug = keyof typeof tools;

function Card({
  label,
  value,
}: {
  label: string;
  value: unknown;
}) {
  return (
    <div className="cx-card p-5">
      <p className="text-xs font-bold uppercase tracking-wider text-red-400">
        {label}
      </p>

      <p className="mt-2 break-all font-semibold">
        {show(value)}
      </p>
    </div>
  );
}

export default function ApiSecurityPage() {
  const params = useParams();
  const slug = String(params?.tool ?? "") as ToolSlug;
  const config = tools[slug];

  const [targets, setTargets] = useState<ApiTarget[]>([]);
  const [targetId, setTargetId] = useState("");
  const [targetsLoading, setTargetsLoading] = useState(true);
  const [data, setData] = useState<Obj | null>(null);
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    let cancelled = false;

    async function loadTargets() {
      if (!getStoredToken()) {
        window.location.href = "/login";
        return;
      }

      try {
        setTargetsLoading(true);
        setError("");

        const collected: ApiTarget[] = [];

        let page = 1;
        let lastPage = 1;

        /*
         * TargetController uses Laravel pagination.
         * Traverse all available pages so older authorized targets
         * are not silently omitted from API Security.
         *
         * Defensive ceiling prevents an unexpected paginator response
         * from causing an unbounded client-side request loop.
         */
        const maxPages = 100;

        do {
          const response = await getTargets({
            page,
            per_page: 20,
          });

          if (
            !isObj(response) ||
            !isObj(response.data) ||
            !Array.isArray(response.data.data)
          ) {
            throw new Error(
              "Invalid target list response."
            );
          }

          const pageTargets = response.data.data
            .filter(isObj)
            .filter(
              (target) =>
                target.authorization_confirmed === true &&
                target.status === "active"
            )
            .map(
              (target) =>
                target as unknown as ApiTarget
            );

          collected.push(...pageTargets);

          const reportedLastPage =
            response.data.last_page;

          lastPage =
            typeof reportedLastPage === "number" &&
            Number.isInteger(reportedLastPage) &&
            reportedLastPage >= page
              ? reportedLastPage
              : page;

          page += 1;
        } while (
          page <= lastPage &&
          page <= maxPages
        );

        const available = Array.from(
          new Map(
            collected.map((target) => [
              target.id,
              target,
            ])
          ).values()
        );

        if (cancelled) {
          return;
        }

        setTargets(available);

        if (available.length > 0) {
          setTargetId((current) =>
            current || available[0].id
          );
        }
      } catch (err) {
        if (cancelled) {
          return;
        }

        setError(
          err instanceof Error
            ? err.message
            : "Could not load authorized targets."
        );
      } finally {
        if (!cancelled) {
          setTargetsLoading(false);
        }
      }
    }

    void loadTargets();

    return () => {
      cancelled = true;
    };
  }, []);

  if (!config) {
    return (
      <main className="min-h-screen bg-[#09090b] p-8">
        Unknown API Security tool.
      </main>
    );
  }

  async function submit(event: FormEvent) {
    event.preventDefault();

    if (!getStoredToken()) {
      window.location.href = "/login";
      return;
    }

    if (!targetId) {
      setError(
        "Select an authorized active target."
      );
      return;
    }

    try {
      setLoading(true);
      setError("");
      setData(null);

      const response = await runApiSecurityAnalysis(
        targetId
      );

      if (!isObj(response) || !isObj(response.data)) {
        throw new Error("Invalid API analysis response.");
      }

      setData(response.data);
    } catch (err) {
      setError(
        err instanceof Error
          ? err.message
          : "API analysis failed."
      );
    } finally {
      setLoading(false);
    }
  }

  const endpoint =
    data && isObj(data.endpoint)
      ? data.endpoint
      : null;

  const methods =
    data && isObj(data.methods)
      ? data.methods
      : null;

  const cors =
    data && isObj(data.cors)
      ? data.cors
      : null;

  const configuration =
    data && isObj(data.configuration)
      ? data.configuration
      : null;

  const headers =
    data && Array.isArray(data.security_headers)
      ? data.security_headers.filter(isObj)
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
            API Security
          </p>

          <h1 className="mt-3 text-4xl font-black sm:text-5xl">
            {config.title}
          </h1>

          <p className="mt-4 max-w-2xl text-white/45">
            {config.description}
          </p>

          <form
            onSubmit={submit}
            className="mt-8 rounded-[2rem] bg-[#09090b] p-6 shadow-[8px_8px_18px_#000000,-8px_-8px_18px_#171719]"
          >
            <label className="text-sm font-bold">
              Authorized Target
            </label>

            <p className="mt-2 text-sm text-white/40">
              API inspection is limited to saved targets that
              you have explicitly authorized and that are
              currently active.
            </p>

            <div className="mt-3 flex flex-col gap-3 sm:flex-row">
              <select
                value={targetId}
                onChange={(e) => {
                  setTargetId(e.target.value);
                  setData(null);
                  setError("");
                }}
                disabled={
                  loading ||
                  targetsLoading ||
                  targets.length === 0
                }
                className="cx-input flex-1"
              >
                {targetsLoading ? (
                  <option value="">
                    Loading authorized targets...
                  </option>
                ) : targets.length === 0 ? (
                  <option value="">
                    No authorized active targets
                  </option>
                ) : (
                  targets.map((target) => (
                    <option
                      key={target.id}
                      value={target.id}
                    >
                      {target.name} — {target.url}
                    </option>
                  ))
                )}
              </select>

              <button
                type="submit"
                disabled={
                  loading ||
                  targetsLoading ||
                  !targetId
                }
                className="cx-button cx-button-primary px-6 disabled:opacity-50"
              >
                {loading ? "Inspecting..." : "Run Inspection"}
              </button>
            </div>

            {!targetsLoading && targets.length === 0 && (
              <p className="mt-3 text-sm text-white/45">
                Register and authorize a target from the
                Targets page before running API inspection.
              </p>
            )}

            {error && (
              <div className="mt-4 rounded-xl bg-red-500/10 px-4 py-3 text-sm font-semibold text-red-300">
                {error}
              </div>
            )}
          </form>
        </section>

        {data && (
          <section className="mt-8 space-y-5">
            <Card label="Endpoint" value={data.url} />

            {slug === "endpoint-inspector" && endpoint && (
              <div className="grid gap-4 sm:grid-cols-2">
                <Card label="HTTP Status" value={endpoint.status} />
                <Card
                  label="Response Time"
                  value={
                    endpoint.duration_ms === undefined
                      ? null
                      : `${String(endpoint.duration_ms)} ms`
                  }
                />
                <Card
                  label="Content Type"
                  value={endpoint.content_type}
                />
                <Card
                  label="Content Length"
                  value={endpoint.content_length}
                />
                <Card
                  label="JSON Response"
                  value={endpoint.json_response}
                />
                <Card
                  label="Redirect"
                  value={endpoint.location}
                />
                <Card
                  label="Resolved IPs"
                  value={data.resolved_ips}
                />
              </div>
            )}

            {slug === "http-method-review" && methods && (
              <>
                <div className="grid gap-4 sm:grid-cols-2">
                  <Card
                    label="Allow Header"
                    value={methods.allow_header}
                  />
                  <Card
                    label="Advertised Methods"
                    value={methods.advertised}
                  />
                  <Card
                    label="Risky Methods"
                    value={methods.risky}
                  />
                </div>

                <div className="cx-card p-5 text-sm leading-6 text-white/45">
                  CrypticX performs this inspection with a
                  bounded GET request only. OPTIONS, POST, PUT,
                  PATCH, DELETE, TRACE and CONNECT are not
                  executed.
                </div>
              </>
            )}

            {slug === "security-headers" && (
              <div className="space-y-3">
                {headers.map((header, index) => (
                  <div
                    key={index}
                    className="cx-card flex flex-col justify-between gap-4 p-5 sm:flex-row"
                  >
                    <div>
                      <p className="font-black">
                        {show(header.name)}
                      </p>

                      <p className="mt-1 break-all text-sm text-white/45">
                        {header.value === null
                          ? "Header not present"
                          : show(header.value)}
                      </p>
                    </div>

                    <span
                      className={`self-start rounded-full px-3 py-1 text-xs font-bold ${
                        header.present
                          ? "bg-emerald-500/10 text-emerald-300"
                          : "bg-red-500/10 text-red-300"
                      }`}
                    >
                      {header.present
                        ? "Present"
                        : "Missing"}
                    </span>
                  </div>
                ))}
              </div>
            )}

            {slug === "api-configuration" &&
              configuration &&
              cors && (
                <>
                  <div className="grid gap-4 sm:grid-cols-2">
                    <Card
                      label="HTTPS"
                      value={configuration.https}
                    />
                    <Card
                      label="JSON Response"
                      value={configuration.json_response}
                    />
                    <Card
                      label="Cache-Control"
                      value={configuration.cache_control}
                    />
                    <Card
                      label="Authentication Challenge"
                      value={configuration.www_authenticate}
                    />
                    <Card
                      label="Server"
                      value={configuration.server}
                    />
                    <Card
                      label="X-Powered-By"
                      value={configuration.powered_by}
                    />
                    <Card
                      label="API Version"
                      value={configuration.api_version}
                    />
                    <Card
                      label="HSTS"
                      value={configuration.hsts}
                    />
                    <Card
                      label="X-Content-Type-Options"
                      value={configuration.content_type_options}
                    />
                  </div>

                  <h2 className="pt-4 text-2xl font-black">
                    CORS
                  </h2>

                  <div className="grid gap-4 sm:grid-cols-2">
                    <Card
                      label="Allow Origin"
                      value={cors.allow_origin}
                    />
                    <Card
                      label="Allow Credentials"
                      value={cors.allow_credentials}
                    />
                    <Card
                      label="Allow Methods"
                      value={cors.allow_methods}
                    />
                    <Card
                      label="Allow Headers"
                      value={cors.allow_headers}
                    />
                  </div>
                </>
              )}
          </section>
        )}
      </div>
    </main>
  );
}
