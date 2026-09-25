"use client";

import Link from "next/link";
import ToolIntro from "@/components/ToolIntro";
import { getToolSeo } from "@/lib/tool-seo";
import { FormEvent, useState } from "react";
import { useParams } from "next/navigation";
import {
  getStoredToken,
  runSqlLab,
  runDataLab,
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

  if (typeof value === "object") {
    return JSON.stringify(value, null, 2);
  }

  return String(value);
}

const sqlTools = {
  "query-analyzer": {
    title: "Query Analyzer",
    placeholder: "SELECT id, email FROM users WHERE active = true;",
  },
  "schema-inspector": {
    title: "Schema Inspector",
    placeholder:
      "CREATE TABLE users (id UUID PRIMARY KEY, email TEXT NOT NULL UNIQUE);",
  },
  "sql-formatter": {
    title: "SQL Formatter",
    placeholder:
      "select id,email from users where active=true order by email;",
  },
  "data-profiler": {
    title: "Data Profiler",
    placeholder:
      "name,age,country\nAlice,28,US\nBob,31,UK",
  },
  "security-analyzer": {
    title: "SQL Security Analyzer",
    placeholder:
      "SELECT id, email FROM users WHERE email = 'demo@example.com';",
  },
  "parameterization-coach": {
    title: "Parameterization Coach",
    placeholder:
      "SELECT id FROM users WHERE email = 'demo@example.com' AND age = 25;",
  },
  "query-risk-report": {
    title: "Query Risk Report",
    placeholder:
      "UPDATE users SET active = false;",
  },
} as const;

const dataTools = {
  "csv-analyzer": {
    title: "CSV Analyzer",
    placeholder:
      "name,email,role\nAlice,alice@example.com,admin",
  },
  "json-inspector": {
    title: "JSON Inspector",
    placeholder:
      '{"name":"CrypticX","active":true,"tools":["dns","tls"]}',
  },
  "data-cleaner": {
    title: "Data Cleaner",
    placeholder:
      "alpha\nalpha\nbeta   value\n\nbeta value",
  },
  "pattern-analysis": {
    title: "Pattern & IOC Analysis",
    placeholder:
      "Contact admin@example.com or visit https://example.com CVE-2026-12345",
  },
  "log-analyzer": {
    title: "Security Log Analyzer",
    placeholder:
      '192.0.2.10 - GET /login HTTP/1.1 401\nAuthentication failed for demo user',
  },
  "http-inspector": {
    title: "HTTP Request / Response Inspector",
    placeholder:
      "HTTP/1.1 200 OK\nServer: nginx\nContent-Type: application/json\n\n{}",
  },
  "encoding-studio": {
    title: "Encoding Studio",
    placeholder: "CrypticX Lab",
  },
  "hash-inspector": {
    title: "Hash Inspector",
    placeholder:
      "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855",
  },
  "jwt-inspector": {
    title: "JWT Inspector",
    placeholder: "Paste a JWT token for offline inspection",
  },
  "regex-lab": {
    title: "Regex Lab",
    placeholder:
      '{"pattern":"\\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\\.[A-Z]{2,}\\b","text":"Contact admin@example.com"}',
  },
  "data-diff": {
    title: "Structured Data Diff",
    placeholder:
      '{"left":{"role":"user"},"right":{"role":"admin"}}',
  },
  "sensitive-data-redactor": {
    title: "Sensitive Data Redactor",
    placeholder:
      "User email admin@example.com",
  },
} as const;

export default function LocalLabToolPage({
  category,
}: {
  category: "sql" | "data";
}) {
  const params = useParams();
  const tool = String(params.tool ?? "");

  const definitions = (
    category === "sql" ? sqlTools : dataTools
  ) as Record<
    string,
    {
      title: string;
      placeholder: string;
    }
  >;

  const config = definitions[tool];
  const seo = getToolSeo(category, tool);

  const [input, setInput] = useState("");
  const [data, setData] = useState<Obj | null>(null);
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(false);

  if (!config) {
    return <main className="p-8">Unknown lab tool.</main>;
  }

  async function submit(event: FormEvent) {
    event.preventDefault();

    if (!getStoredToken()) {
      window.location.href = "/login";
      return;
    }

    if (!input.trim()) {
      setError("Enter input data.");
      return;
    }

    try {
      setLoading(true);
      setError("");
      setData(null);

      const response =
        category === "sql"
          ? await runSqlLab(
              input,
              tool as
                | "query-analyzer"
                | "schema-inspector"
                | "sql-formatter"
                | "data-profiler"
                | "security-analyzer"
                | "parameterization-coach"
                | "query-risk-report"
            )
          : await runDataLab(
              input,
              tool as
                | "csv-analyzer"
                | "json-inspector"
                | "data-cleaner"
                | "pattern-analysis"
                | "log-analyzer"
                | "http-inspector"
                | "encoding-studio"
                | "hash-inspector"
                | "jwt-inspector"
                | "regex-lab"
                | "data-diff"
                | "sensitive-data-redactor"
            );

      if (!isObj(response) || !isObj(response.data)) {
        throw new Error("Invalid tool response.");
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

  return (
    <main className="min-h-screen bg-[#09090b] px-5 py-10 text-white">
      <div className="mx-auto max-w-5xl">
        <Link href="/tools" className="text-sm font-bold">
          ← All Tools
        </Link>

        <p className="mt-8 text-xs font-bold uppercase tracking-[0.2em] text-red-400">
          {category === "sql" ? "SQL Lab" : "Data Lab"}
        </p>

        <h1 className="mt-3 text-4xl font-black">
          {config.title}
        </h1>

        {seo && (
          <ToolIntro
            description={seo.description}
            context={
              category === "sql"
                ? "Analyze supplied SQL text in the controlled CrypticX Lab environment. Do not submit secrets or production credentials."
                : "Analyze only the data you intentionally provide. Avoid submitting secrets, credentials, or unnecessary personal information."
            }
          />
        )}

        {category === "sql" && (
          <p className="mt-3 text-sm text-white/45">
            Static analysis only. CrypticX does not execute this SQL
            against a database.
          </p>
        )}

        <form onSubmit={submit} className="cx-card mt-7 p-6">
          <textarea
            className="cx-input min-h-[260px] w-full resize-y font-mono text-sm"
            value={input}
            onChange={(e) => setInput(e.target.value)}
            placeholder={config.placeholder}
            spellCheck={false}
          />

          <button
            type="submit"
            disabled={loading}
            className="cx-button cx-button-primary mt-5 disabled:opacity-50"
          >
            {loading ? "Processing..." : "Run Tool"}
          </button>

          {error && (
            <p className="mt-4 font-bold text-red-300">
              {error}
            </p>
          )}
        </form>

        {data && (
          <section className="mt-8 space-y-4">
            {Object.entries(data)
              .filter(([key]) => key !== "checked_at")
              .map(([key, value]) => (
                <div key={key} className="cx-card p-5">
                  <p className="text-xs font-bold uppercase tracking-wider text-red-400">
                    {key.replaceAll("_", " ")}
                  </p>

                  {typeof value === "object" &&
                  value !== null ? (
                    <pre className="mt-3 overflow-x-auto whitespace-pre-wrap break-words text-sm">
                      {show(value)}
                    </pre>
                  ) : (
                    <pre className="mt-3 overflow-x-auto whitespace-pre-wrap break-words text-sm">
                      {show(value)}
                    </pre>
                  )}
                </div>
              ))}
          </section>
        )}
      </div>
    </main>
  );
}
