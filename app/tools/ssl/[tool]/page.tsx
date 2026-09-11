"use client";

import Link from "next/link";
import { FormEvent, useState } from "react";
import { useParams } from "next/navigation";
import {
  getStoredToken,
  runSslTlsAnalysis,
} from "@/lib/api";

type Obj = Record<string, unknown>;

function isObj(v: unknown): v is Obj {
  return typeof v === "object" && v !== null;
}

function show(v: unknown): string {
  if (v === null || v === undefined || v === "") return "—";
  if (typeof v === "boolean") return v ? "Yes" : "No";
  if (Array.isArray(v)) return v.join(", ");
  if (isObj(v)) return JSON.stringify(v);
  return String(v);
}

const tools = {
  "certificate-check": "Certificate Check",
  "tls-analysis": "TLS Analysis",
  "cipher-review": "Cipher Review",
  "certificate-chain": "Certificate Chain",
} as const;

type Tool = keyof typeof tools;

function Card({ label, value }: { label: string; value: unknown }) {
  return (
    <div className="cx-card p-5">
      <p className="text-xs font-bold uppercase text-red-400">
        {label}
      </p>
      <p className="mt-2 break-all font-semibold">{show(value)}</p>
    </div>
  );
}

export default function Page() {
  const params = useParams();
  const tool = String(params.tool ?? "") as Tool;

  const [hostname, setHostname] = useState("");
  const [data, setData] = useState<Obj | null>(null);
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(false);

  if (!(tool in tools)) {
    return <main className="p-8">Unknown SSL/TLS tool.</main>;
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

      const response = await runSslTlsAnalysis(
        hostname.trim(),
        tool
      );

      if (!isObj(response) || !isObj(response.data)) {
        throw new Error("Invalid SSL/TLS response.");
      }

      setData(response.data);
    } catch (err) {
      setError(
        err instanceof Error ? err.message : "SSL/TLS analysis failed."
      );
    } finally {
      setLoading(false);
    }
  }

  const chain =
    data && Array.isArray(data.chain)
      ? data.chain.filter(isObj)
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
            <p className="mt-4 font-bold text-red-300">{error}</p>
          )}
        </form>

        {data && tool !== "certificate-chain" && (
          <div className="mt-8 grid gap-4 sm:grid-cols-2">
            {Object.entries(data)
              .filter(([key]) => !["checked_at"].includes(key))
              .map(([key, value]) => (
                <Card
                  key={key}
                  label={key.replaceAll("_", " ")}
                  value={value}
                />
              ))}
          </div>
        )}

        {data && tool === "certificate-chain" && (
          <div className="mt-8 space-y-4">
            <Card label="Chain Length" value={data.chain_length} />

            {chain.map((cert, index) => (
              <div key={index} className="cx-card p-5">
                <h2 className="text-lg font-black">
                  Certificate {show(cert.position)}
                </h2>

                <pre className="mt-4 overflow-x-auto whitespace-pre-wrap text-sm">
                  {JSON.stringify(cert, null, 2)}
                </pre>
              </div>
            ))}
          </div>
        )}
      </div>
    </main>
  );
}
