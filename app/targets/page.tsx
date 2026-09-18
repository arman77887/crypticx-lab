"use client";

import Link from "next/link";
import { useState } from "react";

type Target = {
  name: string;
  value: string;
  type: "Domain" | "API" | "IP / Host";
  project: string;
  status: "Verified" | "Pending";
  lastChecked: string;
};

const initialTargets: Target[] = [
  {
    name: "Main Website",
    value: "example.com",
    type: "Domain",
    project: "Main Website",
    status: "Verified",
    lastChecked: "Today, 10:42 AM",
  },
  {
    name: "Production API",
    value: "api.example.dev",
    type: "API",
    project: "API Platform",
    status: "Verified",
    lastChecked: "Yesterday, 4:18 PM",
  },
  {
    name: "Staging Server",
    value: "staging.example.net",
    type: "Domain",
    project: "Staging Environment",
    status: "Pending",
    lastChecked: "3 days ago",
  },
  {
    name: "Security Lab Host",
    value: "192.0.2.10",
    type: "IP / Host",
    project: "Security Lab",
    status: "Verified",
    lastChecked: "5 days ago",
  },
];

export default function TargetsPage() {
  const [targets, setTargets] = useState(initialTargets);
  const [query, setQuery] = useState("");
  const [type, setType] = useState<"All" | Target["type"]>("All");
  const [showForm, setShowForm] = useState(false);

  const [name, setName] = useState("");
  const [value, setValue] = useState("");
  const [targetType, setTargetType] =
    useState<Target["type"]>("Domain");

  const filteredTargets = targets.filter((target) => {
    const search = query.toLowerCase();

    const matchesQuery =
      target.name.toLowerCase().includes(search) ||
      target.value.toLowerCase().includes(search) ||
      target.project.toLowerCase().includes(search);

    const matchesType =
      type === "All" || target.type === type;

    return matchesQuery && matchesType;
  });

  function addTarget(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();

    if (!name.trim() || !value.trim()) return;

    const newTarget: Target = {
      name: name.trim(),
      value: value.trim(),
      type: targetType,
      project: "Main Website",
      status: "Pending",
      lastChecked: "Not checked yet",
    };

    setTargets((current) => [newTarget, ...current]);
    setName("");
    setValue("");
    setTargetType("Domain");
    setShowForm(false);
  }

  function removeTarget(targetValue: string) {
    setTargets((current) =>
      current.filter((target) => target.value !== targetValue)
    );
  }

  return (
    <main className="min-h-screen bg-[var(--cx-bg)] text-[var(--cx-text)]">

      <section className="mx-auto max-w-7xl px-5 pb-20 pt-28 sm:px-8">
        <div className="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
          <div>
            <div className="mb-3 inline-flex rounded-full px-4 py-2 text-xs font-semibold tracking-wide cx-inset-sm">
              TARGET MANAGEMENT
            </div>

            <h1 className="text-4xl font-bold tracking-tight sm:text-5xl">
              Targets
            </h1>

            <p className="mt-4 max-w-2xl text-base leading-7 text-[var(--cx-muted)]">
              Manage authorized domains, APIs, hosts, and other assessment
              targets before running security checks.
            </p>
          </div>

          <button
            type="button"
            onClick={() => setShowForm((current) => !current)}
            className="cx-button cx-button-primary rounded-2xl px-5 py-3 text-sm font-semibold"
          >
            {showForm ? "Close Form" : "+ Add Target"}
          </button>
        </div>

        <div className="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
          {[
            ["04", "Total Targets"],
            ["03", "Verified"],
            ["01", "Pending"],
            ["04", "Authorized"],
          ].map(([value, label]) => (
            <div key={label} className="cx-card rounded-3xl p-6">
              <div className="text-3xl font-bold">{value}</div>
              <div className="mt-2 text-sm text-[var(--cx-muted)]">
                {label}
              </div>
            </div>
          ))}
        </div>

        {showForm && (
          <section className="mt-8 cx-card rounded-[30px] p-6 sm:p-8">
            <div>
              <h2 className="text-xl font-bold">Add Authorized Target</h2>

              <p className="mt-2 text-sm leading-6 text-[var(--cx-muted)]">
                Add only infrastructure that you own or have explicit
                permission to assess.
              </p>
            </div>

            <form
              onSubmit={addTarget}
              className="mt-6 grid gap-5 lg:grid-cols-3"
            >
              <div>
                <label className="mb-2 block text-sm font-semibold">
                  Target Name
                </label>

                <input
                  type="text"
                  value={name}
                  onChange={(event) => setName(event.target.value)}
                  placeholder="Production Website"
                  className="cx-input w-full rounded-2xl px-4 py-3 text-sm outline-none"
                  required
                />
              </div>

              <div>
                <label className="mb-2 block text-sm font-semibold">
                  Target Value
                </label>

                <input
                  type="text"
                  value={value}
                  onChange={(event) => setValue(event.target.value)}
                  placeholder="example.com"
                  className="cx-input w-full rounded-2xl px-4 py-3 text-sm outline-none"
                  required
                />
              </div>

              <div>
                <label className="mb-2 block text-sm font-semibold">
                  Target Type
                </label>

                <select
                  value={targetType}
                  onChange={(event) =>
                    setTargetType(
                      event.target.value as Target["type"]
                    )
                  }
                  className="cx-input w-full rounded-2xl px-4 py-3 text-sm outline-none"
                >
                  <option>Domain</option>
                  <option>API</option>
                  <option>IP / Host</option>
                </select>
              </div>

              <div className="lg:col-span-3 flex flex-wrap gap-3">
                <button
                  type="submit"
                  className="cx-button cx-button-primary rounded-2xl px-5 py-3 text-sm font-semibold"
                >
                  Add Target
                </button>

                <button
                  type="button"
                  onClick={() => setShowForm(false)}
                  className="cx-button cx-button-secondary rounded-2xl px-5 py-3 text-sm font-semibold"
                >
                  Cancel
                </button>
              </div>
            </form>

            <div className="mt-6 cx-inset-sm rounded-2xl p-4">
              <div className="text-sm font-semibold">
                Authorization required
              </div>

              <p className="mt-1 text-xs leading-5 text-[var(--cx-muted)]">
                In the production backend, target ownership or authorization
                verification will be enforced before active scanning.
              </p>
            </div>
          </section>
        )}

        <div className="mt-8 cx-card rounded-[30px] p-5 sm:p-7">
          <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <input
              type="search"
              value={query}
              onChange={(event) => setQuery(event.target.value)}
              placeholder="Search targets..."
              className="cx-input w-full rounded-2xl px-4 py-3 text-sm outline-none lg:max-w-md"
            />

            <div className="flex flex-wrap gap-2">
              {(["All", "Domain", "API", "IP / Host"] as const).map(
                (item) => (
                  <button
                    key={item}
                    type="button"
                    onClick={() => setType(item)}
                    className={
                      type === item
                        ? "cx-button cx-button-primary rounded-xl px-4 py-2 text-sm font-semibold"
                        : "cx-button cx-button-secondary rounded-xl px-4 py-2 text-sm font-semibold"
                    }
                  >
                    {item}
                  </button>
                )
              )}
            </div>
          </div>
        </div>

        <section className="mt-6 cx-card rounded-[30px] p-5 sm:p-7">
          <div className="mb-5">
            <h2 className="text-xl font-bold">Authorized Targets</h2>

            <p className="mt-1 text-sm text-[var(--cx-muted)]">
              Targets available for controlled security assessments.
            </p>
          </div>

          <div className="space-y-3">
            {filteredTargets.map((target) => (
              <article
                key={target.value}
                className="cx-inset-sm rounded-2xl p-4 sm:p-5"
              >
                <div className="flex flex-col gap-5 xl:flex-row xl:items-center xl:justify-between">
                  <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-3">
                      <h3 className="font-semibold">{target.name}</h3>

                      <span className="rounded-full px-3 py-1 text-xs font-semibold cx-inset-sm">
                        {target.type}
                      </span>

                      <span className="rounded-full px-3 py-1 text-xs font-semibold cx-inset-sm">
                        {target.status}
                      </span>
                    </div>

                    <div className="mt-2 break-all font-mono text-sm text-[var(--cx-muted)]">
                      {target.value}
                    </div>

                    <div className="mt-2 text-xs text-[var(--cx-muted)]">
                      Project: {target.project} · Last checked:{" "}
                      {target.lastChecked}
                    </div>
                  </div>

                  <div className="flex shrink-0 flex-wrap gap-2">
                    <Link
                      href="/scanner"
                      className="cx-button cx-button-primary rounded-xl px-4 py-2 text-sm font-semibold"
                    >
                      Assess
                    </Link>

                    <button
                      type="button"
                      onClick={() => removeTarget(target.value)}
                      className="cx-button cx-button-secondary rounded-xl px-4 py-2 text-sm font-semibold"
                    >
                      Remove
                    </button>
                  </div>
                </div>
              </article>
            ))}
          </div>

          {filteredTargets.length === 0 && (
            <div className="cx-inset-sm mt-4 rounded-2xl p-8 text-center">
              <h3 className="font-semibold">No targets found</h3>

              <p className="mt-2 text-sm text-[var(--cx-muted)]">
                Try a different search term or target type.
              </p>
            </div>
          )}
        </section>

        <section className="mt-8 cx-card rounded-[30px] p-6 sm:p-8">
          <div className="flex gap-4">
            <div className="cx-inset-sm flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl">
              ✓
            </div>

            <div>
              <h2 className="font-bold">Scoped and auditable testing</h2>

              <p className="mt-2 text-sm leading-6 text-[var(--cx-muted)]">
                Production scanning will use explicit target scope, rate
                limits, isolated workers, job timeouts, audit logs, and
                authorization controls. Targets added here are currently
                frontend-only sample data.
              </p>
            </div>
          </div>
        </section>
      </section>
    </main>
  );
}
