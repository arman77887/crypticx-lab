"use client";

import Link from "next/link";
import { FormEvent, useState } from "react";
import { getStoredToken, runDnsLookup } from "@/lib/api";

type RecordItem = {
  value:
    | string
    | {
        host?: string;
        priority?: number;
        flags?: number;
        tag?: string;
        value?: string;
      };
  ttl?: number | null;
};

type DnsData = {
  hostname: string;
  records: Record<string, RecordItem[]>;
  record_count: number;
  duration_ms: number;
  lookup_id: string;
  checked_at: string;
};

type UnknownRecord = Record<string, unknown>;

function isRecord(value: unknown): value is UnknownRecord {
  return typeof value === "object" && value !== null;
}

function extractData(response: unknown): DnsData | null {
  if (!isRecord(response)) return null;
  const data = response.data;
  return isRecord(data) ? (data as unknown as DnsData) : null;
}

function renderValue(item: RecordItem) {
  if (typeof item.value === "string") {
    return item.value;
  }

  if ("host" in item.value) {
    return `${item.value.host ?? "—"}${
      typeof item.value.priority === "number"
        ? ` · priority ${item.value.priority}`
        : ""
    }`;
  }

  if ("tag" in item.value) {
    return `${item.value.tag ?? ""} ${item.value.value ?? ""}`.trim();
  }

  return JSON.stringify(item.value);
}

export default function DnsToolPage() {
  const [hostname, setHostname] = useState("");
  const [result, setResult] = useState<DnsData | null>(null);
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(false);

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();

    if (!getStoredToken()) {
      window.location.href = "/login";
      return;
    }

    const cleanHostname = hostname.trim();

    if (!cleanHostname) {
      setError("Enter a hostname.");
      return;
    }

    try {
      setLoading(true);
      setError("");
      setResult(null);

      const response = await runDnsLookup(cleanHostname);
      const data = extractData(response);

      if (!data) {
        throw new Error("DNS lookup returned an invalid response.");
      }

      setResult(data);
    } catch (err) {
      setError(
        err instanceof Error
          ? err.message
          : "DNS lookup failed."
      );
    } finally {
      setLoading(false);
    }
  }

  const recordTypes = [
    "A",
    "AAAA",
    "MX",
    "NS",
    "TXT",
    "CNAME",
    "CAA",
  ];

  return (
    <main className="min-h-screen bg-[#09090b] text-white">
      <div className="mx-auto max-w-5xl px-5 py-10 sm:px-8">
        <div className="flex flex-wrap items-center justify-between gap-3">
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
            DNS Intelligence
          </p>

          <h1 className="mt-3 text-4xl font-black tracking-tight sm:text-5xl">
            Live DNS Lookup
          </h1>

          <p className="mt-4 max-w-2xl text-sm leading-6 text-white/45">
            Query public DNS records for an authorized or publicly
            observable hostname.
          </p>

          <form
            onSubmit={handleSubmit}
            className="mt-8 rounded-[2rem] bg-[#09090b] p-6 shadow-[8px_8px_18px_#000000,-8px_-8px_18px_#171719]"
          >
            <label
              htmlFor="hostname"
              className="text-sm font-bold"
            >
              Hostname
            </label>

            <div className="mt-3 flex flex-col gap-3 sm:flex-row">
              <input
                id="hostname"
                type="text"
                value={hostname}
                onChange={(event) => setHostname(event.target.value)}
                placeholder="example.com"
                autoComplete="off"
                className="cx-input flex-1"
              />

              <button
                type="submit"
                disabled={loading}
                className="cx-button cx-button-primary px-6 disabled:opacity-50"
              >
                {loading ? "Looking up..." : "Run DNS Lookup"}
              </button>
            </div>

            {error && (
              <div className="mt-4 rounded-xl bg-red-500/10 px-4 py-3 text-sm font-semibold text-red-300">
                {error}
              </div>
            )}
          </form>
        </section>

        {result && (
          <section className="mt-8 space-y-5">
            <div className="grid gap-4 sm:grid-cols-3">
              <div className="cx-card p-5">
                <p className="text-xs uppercase tracking-wider text-red-400">
                  Hostname
                </p>
                <p className="mt-2 break-all font-bold">
                  {result.hostname}
                </p>
              </div>

              <div className="cx-card p-5">
                <p className="text-xs uppercase tracking-wider text-red-400">
                  Records
                </p>
                <p className="mt-2 text-2xl font-black">
                  {result.record_count}
                </p>
              </div>

              <div className="cx-card p-5">
                <p className="text-xs uppercase tracking-wider text-red-400">
                  Lookup Time
                </p>
                <p className="mt-2 text-2xl font-black">
                  {result.duration_ms} ms
                </p>
              </div>
            </div>

            {recordTypes.map((type) => {
              const items = result.records?.[type] ?? [];

              return (
                <section
                  key={type}
                  className="cx-card p-5 sm:p-6"
                >
                  <div className="flex items-center justify-between gap-4">
                    <h2 className="text-xl font-black">
                      {type}
                    </h2>

                    <span className="rounded-full bg-white/[0.04] px-3 py-1 text-xs font-bold text-white/50">
                      {items.length}
                    </span>
                  </div>

                  {items.length === 0 ? (
                    <p className="mt-4 text-sm text-red-400">
                      No {type} records returned.
                    </p>
                  ) : (
                    <div className="mt-4 divide-y divide-[#d2d8dd]">
                      {items.map((item, index) => (
                        <div
                          key={`${type}-${index}`}
                          className="py-3"
                        >
                          <p className="break-all text-sm font-semibold">
                            {renderValue(item)}
                          </p>

                          <p className="mt-1 text-xs text-red-400">
                            TTL: {item.ttl ?? "—"}
                          </p>
                        </div>
                      ))}
                    </div>
                  )}
                </section>
              );
            })}
          </section>
        )}
      </div>
    </main>
  );
}
