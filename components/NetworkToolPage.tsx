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
  runNetworkAnalysis,
} from "@/lib/api";

type Obj = Record<string, unknown>;

function isObj(value: unknown): value is Obj {
  return typeof value === "object" && value !== null;
}

function show(value: unknown): string {
  if (
    value === null ||
    value === undefined ||
    value === ""
  ) {
    return "—";
  }

  return typeof value === "object"
    ? JSON.stringify(value, null, 2)
    : String(value);
}

function isApiTarget(value: unknown): value is ApiTarget {
  if (!isObj(value)) {
    return false;
  }

  return (
    typeof value.id === "string" &&
    typeof value.name === "string" &&
    typeof value.hostname === "string"
  );
}

function extractTargets(response: unknown): ApiTarget[] {
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

  const rootMeta = isObj(response.meta)
    ? response.meta
    : null;

  if (
    rootMeta &&
    typeof rootMeta.last_page === "number"
  ) {
    return Math.max(
      currentPage,
      Math.floor(rootMeta.last_page)
    );
  }

  if (
    isObj(response.data) &&
    isObj(response.data.meta) &&
    typeof response.data.meta.last_page === "number"
  ) {
    return Math.max(
      currentPage,
      Math.floor(response.data.meta.last_page)
    );
  }

  if (
    isObj(response.data) &&
    typeof response.data.last_page === "number"
  ) {
    return Math.max(
      currentPage,
      Math.floor(response.data.last_page)
    );
  }

  return currentPage;
}

const tools = {
  "port-analysis": {
    title: "Port Analysis",
    description:
      "Analyze a bounded set of common TCP ports on an authorized saved target.",
  },
  "service-discovery": {
    title: "Service Discovery",
    description:
      "Identify likely services from reachable standard ports without banner grabbing.",
  },
  "network-inspector": {
    title: "Network Inspector",
    description:
      "Inspect validated public DNS addresses and reachable services.",
  },
  "exposure-review": {
    title: "Exposure Review",
    description:
      "Review externally reachable services and evidence-backed exposure signals.",
  },
} as const;

type NetworkTool = keyof typeof tools;

export default function NetworkToolPage() {
  const params = useParams();
  const tool =
    String(params.tool ?? "") as NetworkTool;
  const config = tools[tool];

  const [targets, setTargets] = useState<ApiTarget[]>(
    []
  );
  const [targetId, setTargetId] = useState("");
  const [targetsLoading, setTargetsLoading] =
    useState(true);
  const [data, setData] = useState<Obj | null>(
    null
  );
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

        const collected = new Map<
          string,
          ApiTarget
        >();

        /*
         * Bounded pagination:
         * at most 100 pages × 20 targets.
         */
        for (
          let page = 1;
          page <= 100;
          page++
        ) {
          const response = await getTargets({
            page,
            per_page: 20,
          });

          const pageTargets =
            extractTargets(response);

          for (const target of pageTargets) {
            if (
              target.authorization_confirmed ===
                true &&
              target.status === "active"
            ) {
              collected.set(target.id, target);
            }
          }

          const lastPage =
            extractLastPage(response, page);

          if (
            page >= lastPage ||
            pageTargets.length === 0
          ) {
            break;
          }
        }

        if (cancelled) {
          return;
        }

        const availableTargets =
          Array.from(collected.values()).sort(
            (a, b) =>
              a.name.localeCompare(b.name)
          );

        setTargets(availableTargets);

        setTargetId((current) => {
          if (
            current &&
            availableTargets.some(
              (target) =>
                target.id === current
            )
          ) {
            return current;
          }

          return availableTargets[0]?.id ?? "";
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
            : "Authorized targets could not be loaded."
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
      <main className="p-8">
        Unknown network tool.
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
        "Select an active authorized target."
      );
      return;
    }

    const selectedTarget = targets.find(
      (target) => target.id === targetId
    );

    if (
      !selectedTarget ||
      selectedTarget.authorization_confirmed !==
        true ||
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
        await runNetworkAnalysis(
          selectedTarget.id,
          tool
        );

      if (
        !isObj(response) ||
        !isObj(response.data)
      ) {
        throw new Error(
          "Invalid network response."
        );
      }

      setData(response.data);
    } catch (err) {
      setError(
        err instanceof Error
          ? err.message
          : "Network analysis failed."
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
          Network Analysis
        </p>

        <h1 className="mt-3 text-4xl font-black">
          {config.title}
        </h1>

        <p className="mt-3 text-sm text-white/45">
          {config.description}
        </p>

        <form
          onSubmit={submit}
          className="cx-card mt-7 p-6"
        >
          <label
            htmlFor="network-target"
            className="mb-2 block text-xs font-bold uppercase tracking-wider text-white/60"
          >
            Authorized Target
          </label>

          <select
            id="network-target"
            className="cx-input w-full"
            value={targetId}
            disabled={
              targetsLoading ||
              loading ||
              targets.length === 0
            }
            onChange={(event) => {
              setTargetId(event.target.value);
              setData(null);
              setError("");
            }}
          >
            {targetsLoading ? (
              <option value="">
                Loading authorized targets...
              </option>
            ) : targets.length === 0 ? (
              <option value="">
                No active authorized targets
              </option>
            ) : (
              targets.map((target) => (
                <option
                  key={target.id}
                  value={target.id}
                >
                  {target.name} — {target.hostname}
                </option>
              ))
            )}
          </select>

          <p className="mt-3 text-xs leading-5 text-white/40">
            Network analysis can only run against
            targets already registered to your
            account with confirmed authorization
            and active status.
          </p>

          {targets.length === 0 &&
            !targetsLoading && (
              <p className="mt-3 text-sm text-white/55">
                Add and authorize a target before
                running network analysis.
              </p>
            )}

          <button
            type="submit"
            disabled={
              loading ||
              targetsLoading ||
              !targetId
            }
            className="cx-button cx-button-primary mt-5 disabled:cursor-not-allowed disabled:opacity-50"
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
                    {key.replaceAll("_", " ")}
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
