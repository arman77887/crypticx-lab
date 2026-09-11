"use client";

import Link from "next/link";
import {
  FormEvent,
  useEffect,
  useState,
} from "react";
import { useParams } from "next/navigation";
import {
  ApiTarget,
  getStoredToken,
  getTargets,
  runReconAnalysis,
} from "@/lib/api";

type Obj = Record<string, unknown>;

function isObj(value: unknown): value is Obj {
  return (
    typeof value === "object" &&
    value !== null
  );
}

function show(value: unknown): string {
  if (
    value === null ||
    value === undefined ||
    value === ""
  ) {
    return "—";
  }

  if (typeof value === "boolean") {
    return value ? "Yes" : "No";
  }

  if (typeof value === "object") {
    return JSON.stringify(
      value,
      null,
      2
    );
  }

  return String(value);
}

function isApiTarget(
  value: unknown
): value is ApiTarget {
  return (
    isObj(value) &&
    typeof value.id === "string" &&
    typeof value.hostname === "string"
  );
}

function extractTargets(
  response: unknown
): ApiTarget[] {
  if (!isObj(response)) {
    return [];
  }

  const data = response.data;

  if (Array.isArray(data)) {
    return data.filter(isApiTarget);
  }

  if (
    isObj(data) &&
    Array.isArray(data.data)
  ) {
    return data.data.filter(isApiTarget);
  }

  return [];
}

function extractLastPage(
  response: unknown,
  currentPage: number
): number {
  if (!isObj(response)) {
    return currentPage;
  }

  const rootMeta = response.meta;

  if (
    isObj(rootMeta) &&
    typeof rootMeta.last_page === "number"
  ) {
    return Math.max(
      currentPage,
      rootMeta.last_page
    );
  }

  const data = response.data;

  if (isObj(data)) {
    if (
      typeof data.last_page === "number"
    ) {
      return Math.max(
        currentPage,
        data.last_page
      );
    }

    if (
      isObj(data.meta) &&
      typeof data.meta.last_page === "number"
    ) {
      return Math.max(
        currentPage,
        data.meta.last_page
      );
    }
  }

  return currentPage;
}

const tools = {
  "asset-discovery": {
    title: "Asset Discovery",
    description:
      "Perform bounded DNS discovery of a fixed set of common public-facing names under an authorized saved target.",
  },
  "technology-detection": {
    title: "Technology Detection",
    description:
      "Inspect bounded HTTP headers and public HTML signals on an authorized saved target.",
  },
  "metadata-inspector": {
    title: "Metadata Inspector",
    description:
      "Review bounded public page metadata and selected HTTP information from an authorized saved target.",
  },
  whois: {
    title: "WHOIS / RDAP",
    description:
      "Retrieve public domain registration information through the dedicated RDAP service for an authorized saved target.",
  },
} as const;

type ReconTool = keyof typeof tools;

export default function ReconToolPage() {
  const params = useParams();

  const tool =
    String(params.tool ?? "") as ReconTool;

  const config = tools[tool];

  const [targets, setTargets] =
    useState<ApiTarget[]>([]);

  const [targetId, setTargetId] =
    useState("");

  const [
    targetsLoading,
    setTargetsLoading,
  ] = useState(true);

  const [data, setData] =
    useState<Obj | null>(null);

  const [error, setError] =
    useState("");

  const [loading, setLoading] =
    useState(false);

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

        const collected =
          new Map<string, ApiTarget>();

        let page = 1;

        /*
         * Bounded client pagination.
         * Backend ownership/authorization remains
         * the actual security boundary.
         */
        while (page <= 100) {
          const response =
            await getTargets({
              page,
              per_page: 20,
            });

          const pageTargets =
            extractTargets(response);

          for (
            const target of pageTargets
          ) {
            if (
              target.authorization_confirmed ===
                true &&
              target.status === "active"
            ) {
              collected.set(
                target.id,
                target
              );
            }
          }

          const lastPage =
            extractLastPage(
              response,
              page
            );

          if (page >= lastPage) {
            break;
          }

          page++;
        }

        if (cancelled) {
          return;
        }

        const available =
          Array.from(
            collected.values()
          ).sort((a, b) =>
            (
              a.name ||
              a.hostname
            ).localeCompare(
              b.name ||
              b.hostname
            )
          );

        setTargets(available);

        setTargetId((current) => {
          if (
            current &&
            available.some(
              (target) =>
                target.id === current
            )
          ) {
            return current;
          }

          return available[0]?.id ?? "";
        });
      } catch (err) {
        if (cancelled) {
          return;
        }

        setTargets([]);
        setTargetId("");

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
      <main className="min-h-screen p-8">
        Unknown reconnaissance tool.
      </main>
    );
  }

  async function submit(
    event: FormEvent
  ) {
    event.preventDefault();

    if (!getStoredToken()) {
      window.location.href = "/login";
      return;
    }

    if (!targetId) {
      setError(
        "Select an authorized target."
      );
      return;
    }

    const selectedTarget =
      targets.find(
        (target) =>
          target.id === targetId
      );

    if (
      !selectedTarget ||
      selectedTarget
        .authorization_confirmed !== true ||
      selectedTarget.status !== "active"
    ) {
      setError(
        "The selected target is not active and authorized."
      );
      return;
    }

    try {
      setLoading(true);
      setError("");
      setData(null);

      const response =
        await runReconAnalysis(
          selectedTarget.id,
          tool
        );

      if (
        !isObj(response) ||
        !isObj(response.data)
      ) {
        throw new Error(
          "Invalid reconnaissance response."
        );
      }

      setData(response.data);
    } catch (err) {
      setError(
        err instanceof Error
          ? err.message
          : "Reconnaissance analysis failed."
      );
    } finally {
      setLoading(false);
    }
  }

  return (
    <main className="min-h-screen bg-[#09090b] px-5 py-10 text-white">
      <div className="mx-auto max-w-5xl">
        <Link
          href="/tools"
          className="text-sm font-bold"
        >
          ← All Tools
        </Link>

        <p className="mt-8 text-xs font-bold uppercase tracking-[0.2em] text-red-400">
          Reconnaissance
        </p>

        <h1 className="mt-3 text-4xl font-black">
          {config.title}
        </h1>

        <p className="mt-3 max-w-3xl text-sm text-white/45">
          {config.description}
        </p>

        <form
          onSubmit={submit}
          className="cx-card mt-7 p-6"
        >
          <label
            htmlFor="recon-target"
            className="mb-2 block text-xs font-bold uppercase tracking-wider text-white/55"
          >
            Authorized target
          </label>

          <select
            id="recon-target"
            className="cx-input w-full"
            value={targetId}
            onChange={(event) => {
              setTargetId(
                event.target.value
              );
              setData(null);
              setError("");
            }}
            disabled={
              targetsLoading ||
              loading ||
              targets.length === 0
            }
          >
            {targetsLoading ? (
              <option value="">
                Loading targets...
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
                  {target.name ||
                    target.hostname}{" "}
                  — {target.hostname}
                </option>
              ))
            )}
          </select>

          <p className="mt-3 text-xs leading-5 text-white/40">
            Reconnaissance is restricted to
            saved targets that are active and
            authorization-confirmed. The
            backend independently verifies
            ownership and authorization.
          </p>

          {!targetsLoading &&
            targets.length === 0 && (
              <p className="mt-4 text-sm font-bold text-red-300">
                Add and authorize a target
                before running this tool.
              </p>
            )}

          <button
            type="submit"
            disabled={
              loading ||
              targetsLoading ||
              !targetId
            }
            className="cx-button cx-button-primary mt-5 disabled:opacity-50"
          >
            {loading
              ? "Analyzing..."
              : "Run Analysis"}
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
              .filter(
                ([key]) =>
                  key !== "checked_at"
              )
              .map(([key, value]) => (
                <div
                  key={key}
                  className="cx-card p-5"
                >
                  <p className="text-xs font-bold uppercase tracking-wider text-red-400">
                    {key.replaceAll(
                      "_",
                      " "
                    )}
                  </p>

                  <pre className="mt-3 overflow-x-auto whitespace-pre-wrap break-words text-sm">
                    {show(value)}
                  </pre>
                </div>
              ))}
          </section>
        )}
      </div>
    </main>
  );
}
