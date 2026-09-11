"use client";

import {
  FormEvent,
  useEffect,
  useMemo,
  useState,
} from "react";

import Link from "next/link";

import {
  AdminTarget,
  getAdminTargets,
  updateAdminTarget,
} from "@/lib/api";

type AuthorizationFilter =
  | "All"
  | "Confirmed"
  | "Revoked";

type StatusFilter =
  | "All"
  | "Active"
  | "Paused";

function authorizationState(
  target: AdminTarget,
): "Confirmed" | "Revoked" {
  return target.authorization_confirmed
    ? "Confirmed"
    : "Revoked";
}

function statusState(
  target: AdminTarget,
): "Active" | "Paused" {
  return target.status === "active"
    ? "Active"
    : "Paused";
}

function formatDate(
  value?: string | null,
): string {
  if (!value) {
    return "Never";
  }

  const date = new Date(value);

  if (Number.isNaN(date.getTime())) {
    return value;
  }

  return date.toLocaleString();
}

export default function AdminTargetsPage() {
  const [targets, setTargets] =
    useState<AdminTarget[]>([]);

  const [query, setQuery] =
    useState("");

  const [
    authorizationFilter,
    setAuthorizationFilter,
  ] = useState<AuthorizationFilter>("All");

  const [
    statusFilter,
    setStatusFilter,
  ] = useState<StatusFilter>("All");

  const [loading, setLoading] =
    useState(true);

  const [busyId, setBusyId] =
    useState<string | null>(null);

  const [error, setError] =
    useState("");

  const [editing, setEditing] =
    useState<AdminTarget | null>(null);

  const [editName, setEditName] =
    useState("");

  async function loadTargets() {
    try {
      setLoading(true);
      setError("");

      const response =
        await getAdminTargets({
          per_page: 100,
        });

      setTargets(
        response.data.data ?? [],
      );
    } catch (err) {
      setError(
        err instanceof Error
          ? err.message
          : "Unable to load targets.",
      );
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    void loadTargets();
  }, []);

  const filteredTargets =
    useMemo(() => {
      const search =
        query.trim().toLowerCase();

      return targets.filter(
        (target) => {
          const authorization =
            authorizationState(target);

          const status =
            statusState(target);

          const ownerName =
            target.owner?.name ?? "";

          const ownerEmail =
            target.owner?.email ?? "";

          const matchesQuery =
            !search ||
            target.id
              .toLowerCase()
              .includes(search) ||
            target.name
              .toLowerCase()
              .includes(search) ||
            target.url
              .toLowerCase()
              .includes(search) ||
            target.hostname
              .toLowerCase()
              .includes(search) ||
            ownerName
              .toLowerCase()
              .includes(search) ||
            ownerEmail
              .toLowerCase()
              .includes(search);

          const matchesAuthorization =
            authorizationFilter ===
              "All" ||
            authorization ===
              authorizationFilter;

          const matchesStatus =
            statusFilter === "All" ||
            status === statusFilter;

          return (
            matchesQuery &&
            matchesAuthorization &&
            matchesStatus
          );
        },
      );
    }, [
      targets,
      query,
      authorizationFilter,
      statusFilter,
    ]);

  const confirmedCount =
    targets.filter(
      (target) =>
        target.authorization_confirmed,
    ).length;

  const revokedCount =
    targets.length -
    confirmedCount;

  const activeCount =
    targets.filter(
      (target) =>
        target.status === "active",
    ).length;

  const totalAssessments =
    targets.reduce(
      (sum, target) =>
        sum +
        target.assessments_count,
      0,
    );

  async function patchTarget(
    target: AdminTarget,
    payload: {
      name?: string;
      status?: "active" | "paused";
      authorization_confirmed?: boolean;
    },
  ) {
    try {
      setBusyId(target.id);
      setError("");

      const response =
        await updateAdminTarget(
          target.id,
          payload,
        );

      setTargets((items) =>
        items.map((item) =>
          item.id === target.id
            ? response.data
            : item,
        ),
      );

      if (
        editing?.id === target.id
      ) {
        setEditing(response.data);
        setEditName(
          response.data.name,
        );
      }
    } catch (err) {
      setError(
        err instanceof Error
          ? err.message
          : "Unable to update target.",
      );
    } finally {
      setBusyId(null);
    }
  }

  async function handleSaveName(
    event: FormEvent,
  ) {
    event.preventDefault();

    if (!editing) {
      return;
    }

    await patchTarget(editing, {
      name: editName.trim(),
    });

    setEditing(null);
  }

  function openManage(
    target: AdminTarget,
  ) {
    setError("");
    setEditing(target);
    setEditName(target.name);
  }

  async function revokeAuthorization(
    target: AdminTarget,
  ) {
    const confirmed =
      window.confirm(
        `Revoke authorization for "${target.name}"?\n\nThe target will also be paused and should no longer be eligible for assessment execution.`,
      );

    if (!confirmed) {
      return;
    }

    await patchTarget(target, {
      authorization_confirmed:
        false,
    });
  }

  async function restoreAuthorization(
    target: AdminTarget,
  ) {
    const confirmed =
      window.confirm(
        `Restore authorization for "${target.name}"?\n\nThis confirms the target is within authorized scope. The target will remain paused until explicitly activated.`,
      );

    if (!confirmed) {
      return;
    }

    await patchTarget(target, {
      authorization_confirmed:
        true,
    });
  }

  async function toggleStatus(
    target: AdminTarget,
  ) {
    if (
      !target.authorization_confirmed &&
      target.status !== "active"
    ) {
      setError(
        "Authorization must be confirmed before a target can be activated.",
      );

      return;
    }

    await patchTarget(target, {
      status:
        target.status === "active"
          ? "paused"
          : "active",
    });
  }

  return (
    <main className="min-h-screen bg-[#050505] text-white">

      <section className="mx-auto max-w-7xl px-5 pb-24 pt-28 sm:px-8">
        <div className="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
          <div>
            <div className="mb-3 inline-flex items-center gap-2 rounded-full border border-red-500/15 bg-red-500/[0.04] px-4 py-2 text-[10px] font-bold uppercase tracking-[0.22em] text-red-400">
              <span className="h-1.5 w-1.5 rounded-full bg-red-500 shadow-[0_0_12px_rgba(239,68,68,.7)]" />

              Admin · Asset Governance
            </div>

            <h1 className="text-4xl font-black tracking-tight sm:text-5xl">
              Target Registry
            </h1>

            <p className="mt-4 max-w-3xl text-sm leading-7 text-white/40">
              Platform-wide inventory of registered security
              assessment targets, ownership, authorization
              state and assessment activity.
            </p>
          </div>

          <div className="flex flex-wrap gap-3">
            <Link
              href="/admin"
              className="cx-button cx-button-secondary rounded-xl px-5 py-3 text-sm font-semibold"
            >
              ← Command Center
            </Link>

            <button
              type="button"
              onClick={() =>
                void loadTargets()
              }
              disabled={loading}
              className="cx-button cx-button-primary rounded-xl px-5 py-3 text-sm font-semibold disabled:opacity-40"
            >
              {loading
                ? "Synchronizing..."
                : "↻ Refresh Registry"}
            </button>
          </div>
        </div>

        {error && (
          <div className="mt-6 rounded-2xl border border-red-500/20 bg-red-500/[0.05] px-4 py-3 text-sm text-red-200">
            {error}
          </div>
        )}

        <section className="mt-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
          {[
            [
              targets.length,
              "Total Targets",
              "Platform inventory",
            ],
            [
              confirmedCount,
              "Authorized",
              "Scope confirmed",
            ],
            [
              revokedCount,
              "Revoked",
              "Authorization unavailable",
            ],
            [
              activeCount,
              "Active",
              "Assessment eligible state",
            ],
            [
              totalAssessments,
              "Assessments",
              "Historical jobs",
            ],
          ].map(
            ([
              value,
              label,
              detail,
            ]) => (
              <div
                key={String(label)}
                className="rounded-2xl border border-white/[0.07] bg-[#09090b] p-5 shadow-[0_14px_40px_rgba(0,0,0,.35)]"
              >
                <div className="flex items-start justify-between">
                  <div className="text-3xl font-black">
                    {loading
                      ? "—"
                      : value}
                  </div>

                  <span className="mt-1 h-2 w-2 rounded-full bg-red-500/70 shadow-[0_0_12px_rgba(239,68,68,.45)]" />
                </div>

                <div className="mt-3 text-xs font-bold uppercase tracking-[0.15em] text-white/60">
                  {label}
                </div>

                <div className="mt-1 text-xs text-white/25">
                  {detail}
                </div>
              </div>
            ),
          )}
        </section>

        <section className="mt-5 rounded-2xl border border-white/[0.07] bg-[#09090b] p-5">
          <div className="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
            <input
              type="search"
              value={query}
              onChange={(event) =>
                setQuery(
                  event.target.value,
                )
              }
              placeholder="Search target, hostname, URL, owner or UUID..."
              className="cx-input w-full rounded-xl px-4 py-3 text-sm xl:max-w-lg"
            />

            <div className="text-xs text-white/25">
              {loading
                ? "Loading real inventory..."
                : `${filteredTargets.length} of ${targets.length} targets displayed`}
            </div>
          </div>

          <div className="mt-4 flex flex-wrap gap-2">
            {(
              [
                "All",
                "Confirmed",
                "Revoked",
              ] as const
            ).map((item) => (
              <button
                key={item}
                type="button"
                onClick={() =>
                  setAuthorizationFilter(
                    item,
                  )
                }
                className={
                  authorizationFilter ===
                  item
                    ? "cx-button cx-button-primary rounded-xl px-3 py-2 text-xs font-semibold"
                    : "cx-button cx-button-secondary rounded-xl px-3 py-2 text-xs font-semibold"
                }
              >
                Authorization: {item}
              </button>
            ))}
          </div>

          <div className="mt-3 flex flex-wrap gap-2">
            {(
              [
                "All",
                "Active",
                "Paused",
              ] as const
            ).map((item) => (
              <button
                key={item}
                type="button"
                onClick={() =>
                  setStatusFilter(item)
                }
                className={
                  statusFilter === item
                    ? "cx-button cx-button-primary rounded-xl px-3 py-2 text-xs font-semibold"
                    : "cx-button cx-button-secondary rounded-xl px-3 py-2 text-xs font-semibold"
                }
              >
                Status: {item}
              </button>
            ))}
          </div>
        </section>

        <section className="mt-5 overflow-hidden rounded-2xl border border-white/[0.07] bg-[#09090b]">
          <div className="flex flex-col gap-3 border-b border-white/[0.06] px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <div className="text-[10px] font-bold uppercase tracking-[0.22em] text-red-400/75">
                Authorized Scope Registry
              </div>

              <h2 className="mt-1 text-base font-bold">
                Security Targets
              </h2>
            </div>

            <div className="text-xs text-white/25">
              Real backend state
            </div>
          </div>

          {loading ? (
            <div className="space-y-3 p-5">
              {[1, 2, 3].map(
                (item) => (
                  <div
                    key={item}
                    className="animate-pulse rounded-xl border border-white/[0.06] bg-black/20 p-5"
                  >
                    <div className="h-4 w-56 rounded bg-white/[0.05]" />
                    <div className="mt-3 h-3 w-72 rounded bg-white/[0.04]" />
                  </div>
                ),
              )}
            </div>
          ) : filteredTargets.length ? (
            <div className="divide-y divide-white/[0.05]">
              {filteredTargets.map(
                (target) => {
                  const authorized =
                    target.authorization_confirmed;

                  const active =
                    target.status ===
                    "active";

                  const busy =
                    busyId === target.id;

                  return (
                    <article
                      key={target.id}
                      className="p-5 transition hover:bg-red-500/[0.025]"
                    >
                      <div className="flex flex-col gap-5 xl:flex-row xl:items-center xl:justify-between">
                        <div className="min-w-0">
                          <div className="flex flex-wrap items-center gap-2">
                            <h3 className="break-all font-bold text-white/90">
                              {target.name}
                            </h3>

                            <span
                              className={
                                authorized
                                  ? "rounded-full border border-emerald-500/15 bg-emerald-500/[0.05] px-3 py-1 text-[10px] font-bold uppercase tracking-wider text-emerald-300"
                                  : "rounded-full border border-red-500/20 bg-red-500/[0.06] px-3 py-1 text-[10px] font-bold uppercase tracking-wider text-red-300"
                              }
                            >
                              {authorized
                                ? "Authorized"
                                : "Revoked"}
                            </span>

                            <span
                              className={
                                active
                                  ? "rounded-full border border-red-500/15 bg-red-500/[0.05] px-3 py-1 text-[10px] font-bold uppercase tracking-wider text-red-300"
                                  : "rounded-full border border-white/[0.08] bg-white/[0.03] px-3 py-1 text-[10px] font-bold uppercase tracking-wider text-white/45"
                              }
                            >
                              {active
                                ? "Active"
                                : "Paused"}
                            </span>
                          </div>

                          <div className="mt-2 break-all text-sm text-white/45">
                            {target.url}
                          </div>

                          <div className="mt-3 flex flex-wrap gap-x-5 gap-y-2 text-[11px] text-white/30">
                            <span>
                              Host:{" "}
                              {target.hostname}
                            </span>

                            <span>
                              Scheme:{" "}
                              {target.scheme}
                            </span>

                            {target.port && (
                              <span>
                                Port:{" "}
                                {target.port}
                              </span>
                            )}

                            <span>
                              Assessments:{" "}
                              {
                                target.assessments_count
                              }
                            </span>

                            <span>
                              Findings:{" "}
                              {
                                target.findings_count
                              }
                            </span>
                          </div>

                          <div className="mt-3 rounded-xl border border-white/[0.05] bg-black/20 px-4 py-3">
                            <div className="flex flex-wrap gap-x-6 gap-y-2 text-[11px]">
                              <span className="text-white/30">
                                Owner:{" "}
                                <span className="text-white/60">
                                  {target.owner?.name ??
                                    "Unknown"}
                                </span>
                              </span>

                              <span className="text-white/30">
                                Email:{" "}
                                <span className="text-white/60">
                                  {target.owner?.email ??
                                    "Unknown"}
                                </span>
                              </span>

                              <span className="text-white/30">
                                Last assessment:{" "}
                                <span className="text-white/60">
                                  {formatDate(
                                    target.last_assessment_at,
                                  )}
                                </span>
                              </span>
                            </div>
                          </div>

                          <div className="mt-3 text-[10px] text-white/20">
                            ID: {target.id}
                          </div>
                        </div>

                        <div className="flex shrink-0 flex-wrap gap-2">
                          <button
                            type="button"
                            disabled={busy}
                            onClick={() =>
                              openManage(
                                target,
                              )
                            }
                            className="cx-button cx-button-secondary rounded-xl px-4 py-2 text-xs font-semibold disabled:opacity-40"
                          >
                            Manage
                          </button>

                          <button
                            type="button"
                            disabled={
                              busy ||
                              (!authorized &&
                                !active)
                            }
                            onClick={() =>
                              void toggleStatus(
                                target,
                              )
                            }
                            className="cx-button cx-button-secondary rounded-xl px-4 py-2 text-xs font-semibold disabled:opacity-35"
                          >
                            {active
                              ? "Pause"
                              : "Activate"}
                          </button>

                          {authorized ? (
                            <button
                              type="button"
                              disabled={
                                busy
                              }
                              onClick={() =>
                                void revokeAuthorization(
                                  target,
                                )
                              }
                              className="rounded-xl border border-red-500/20 bg-red-500/[0.05] px-4 py-2 text-xs font-semibold text-red-300 transition hover:bg-red-500/[0.10] disabled:opacity-40"
                            >
                              Revoke Authorization
                            </button>
                          ) : (
                            <button
                              type="button"
                              disabled={
                                busy
                              }
                              onClick={() =>
                                void restoreAuthorization(
                                  target,
                                )
                              }
                              className="cx-button cx-button-primary rounded-xl px-4 py-2 text-xs font-semibold disabled:opacity-40"
                            >
                              Restore Authorization
                            </button>
                          )}
                        </div>
                      </div>
                    </article>
                  );
                },
              )}
            </div>
          ) : (
            <div className="px-5 py-16 text-center">
              <div className="text-sm font-semibold text-white/50">
                No targets match these filters.
              </div>

              <div className="mt-2 text-xs text-white/25">
                Change the search,
                authorization or status
                filter.
              </div>
            </div>
          )}
        </section>

        <section className="mt-6 grid gap-5 lg:grid-cols-2">
          <div className="rounded-2xl border border-white/[0.07] bg-[#09090b] p-6">
            <div className="text-[10px] font-bold uppercase tracking-[0.22em] text-red-400">
              Authorization Model
            </div>

            <h2 className="mt-2 text-xl font-black">
              Explicit Scope Confirmation
            </h2>

            <p className="mt-3 text-sm leading-7 text-white/35">
              CrypticX currently records
              explicit user confirmation when
              a target is registered. Admin
              revocation immediately pauses the
              target. A separate pending/rejected
              review workflow is not represented
              by the current database schema and
              is therefore not simulated here.
            </p>
          </div>

          <div className="rounded-2xl border border-white/[0.07] bg-[#09090b] p-6">
            <div className="text-[10px] font-bold uppercase tracking-[0.22em] text-red-400">
              Evidence Retention
            </div>

            <h2 className="mt-2 text-xl font-black">
              Destructive Delete Disabled
            </h2>

            <p className="mt-3 text-sm leading-7 text-white/35">
              Targets may have assessment,
              finding and lifecycle history.
              Admin Targets V1 therefore uses
              pause and authorization revocation
              instead of destructive deletion.
            </p>
          </div>
        </section>
      </section>

      {editing && (
        <div className="fixed inset-0 z-[100] flex items-center justify-center bg-black/80 p-4 backdrop-blur-sm">
          <form
            onSubmit={
              handleSaveName
            }
            className="w-full max-w-xl rounded-3xl border border-red-500/15 bg-[#09090b] p-6 shadow-[0_30px_100px_rgba(0,0,0,.8)]"
          >
            <div className="flex items-start justify-between gap-4">
              <div>
                <div className="text-[10px] font-bold uppercase tracking-[0.22em] text-red-400">
                  Asset Control
                </div>

                <h2 className="mt-2 text-2xl font-black">
                  Manage Target
                </h2>

                <div className="mt-1 break-all text-xs text-white/30">
                  {editing.url}
                </div>
              </div>

              <button
                type="button"
                disabled={
                  busyId ===
                  editing.id
                }
                onClick={() =>
                  setEditing(null)
                }
                className="text-xl text-white/35 hover:text-white disabled:opacity-40"
              >
                ×
              </button>
            </div>

            <div className="mt-6">
              <label className="mb-2 block text-[10px] font-bold uppercase tracking-wider text-white/35">
                Display Name
              </label>

              <input
                required
                maxLength={160}
                value={editName}
                onChange={(event) =>
                  setEditName(
                    event.target.value,
                  )
                }
                className="cx-input w-full rounded-xl px-4 py-3"
              />
            </div>

            <div className="mt-5 rounded-xl border border-white/[0.06] bg-black/20 p-4 text-xs leading-6 text-white/30">
              Target URL, hostname,
              scheme, port and ownership are
              intentionally immutable in this
              Admin V1 editor. Changing scan
              identity requires a separately
              validated workflow rather than
              silently rewriting scope.
            </div>

            <div className="mt-6 flex justify-end gap-3">
              <button
                type="button"
                disabled={
                  busyId ===
                  editing.id
                }
                onClick={() =>
                  setEditing(null)
                }
                className="cx-button cx-button-secondary rounded-xl px-5 py-3 text-sm font-semibold"
              >
                Cancel
              </button>

              <button
                type="submit"
                disabled={
                  busyId ===
                  editing.id
                }
                className="cx-button cx-button-primary rounded-xl px-5 py-3 text-sm font-semibold disabled:opacity-40"
              >
                {busyId ===
                editing.id
                  ? "Saving..."
                  : "Save Changes"}
              </button>
            </div>
          </form>
        </div>
      )}
    </main>
  );
}
