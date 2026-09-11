"use client";

import Link from "next/link";
import { FormEvent, useState } from "react";
import { useParams } from "next/navigation";
import {
  getStoredToken,
  runDnsIntelligence,
} from "@/lib/api";

type Obj = Record<string, unknown>;

function obj(v: unknown): v is Obj {
  return typeof v === "object" && v !== null;
}

function text(v: unknown): string {
  if (v === null || v === undefined || v === "") return "—";
  if (Array.isArray(v)) return v.length ? v.join(", ") : "None";
  if (typeof v === "boolean") return v ? "Yes" : "No";
  if (obj(v)) return JSON.stringify(v);
  return String(v);
}

const tools = {
  "record-inspector": "Record Inspector",
  "subdomain-discovery": "Subdomain Discovery",
  "dns-health": "DNS Health",
} as const;

type Tool = keyof typeof tools;

export default function Page() {
  const params = useParams();
  const tool = String(params.tool ?? "") as Tool;

  const [hostname, setHostname] = useState("");
  const [data, setData] = useState<Obj | null>(null);
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(false);

  if (!(tool in tools)) {
    return <main className="p-8">Unknown DNS tool.</main>;
  }

  async function submit(e: FormEvent) {
    e.preventDefault();

    if (!getStoredToken()) {
      window.location.href = "/login";
      return;
    }

    try {
      setLoading(true);
      setError("");
      setData(null);

      const response = await runDnsIntelligence(
        hostname.trim(),
        tool
      );

      if (!obj(response) || !obj(response.data)) {
        throw new Error("Invalid DNS response.");
      }

      setData(response.data);
    } catch (err) {
      setError(
        err instanceof Error ? err.message : "DNS analysis failed."
      );
    } finally {
      setLoading(false);
    }
  }

  const records = data && obj(data.records) ? data.records : {};
  const subdomains =
    data && Array.isArray(data.subdomains)
      ? data.subdomains.filter(obj)
      : [];
  const checks =
    data && Array.isArray(data.checks)
      ? data.checks.filter(obj)
      : [];

  return (
    <main className="min-h-screen bg-[#09090b] px-5 py-10 text-white">
      <div className="mx-auto max-w-5xl">
        <Link href="/tools" className="text-sm font-bold">
          ← All Tools
        </Link>

        <h1 className="mt-8 text-4xl font-black">{tools[tool]}</h1>

        <form onSubmit={submit} className="cx-card mt-7 p-6">
          <input
            className="cx-input w-full"
            placeholder="example.com"
            value={hostname}
            onChange={(e) => setHostname(e.target.value)}
          />

          <button
            className="cx-button cx-button-primary mt-4"
            disabled={loading}
          >
            {loading ? "Analyzing..." : "Run Analysis"}
          </button>

          {error && (
            <p className="mt-4 text-sm font-bold text-red-300">
              {error}
            </p>
          )}
        </form>

        {data && tool === "record-inspector" && (
          <div className="mt-8 space-y-4">
            {Object.entries(records).map(([type, value]) => (
              <div key={type} className="cx-card p-5">
                <h2 className="font-black">{type}</h2>
                <pre className="mt-3 overflow-x-auto whitespace-pre-wrap text-sm">
                  {JSON.stringify(value, null, 2)}
                </pre>
              </div>
            ))}
          </div>
        )}

        {data && tool === "subdomain-discovery" && (
          <div className="mt-8 space-y-4">
            <div className="cx-card p-5">
              Found: {text(data.found_count)}
            </div>

            {subdomains.map((item, index) => (
              <div key={index} className="cx-card p-5">
                <p className="font-black">{text(item.hostname)}</p>
                <p className="mt-2 text-sm">
                  Addresses: {text(item.addresses)}
                </p>
                <p className="mt-1 text-sm">
                  CNAME: {text(item.cnames)}
                </p>
              </div>
            ))}
          </div>
        )}

        {data && tool === "dns-health" && (
          <div className="mt-8 space-y-4">
            {checks.map((check, index) => (
              <div key={index} className="cx-card p-5">
                <div className="flex justify-between gap-4">
                  <p className="font-black">{text(check.name)}</p>
                  <span className="text-xs font-bold uppercase">
                    {text(check.status)}
                  </span>
                </div>
                <p className="mt-2 text-sm text-white/45">
                  {text(check.detail)}
                </p>
              </div>
            ))}
          </div>
        )}
      </div>
    </main>
  );
}
