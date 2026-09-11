"use client";

import {
  useCallback,
  useEffect,
  useMemo,
  useState,
} from "react";

import Link from "next/link";

import {
  type AdminRoleRecord,
  type AdminRolesResponse,
  getAdminRoles,
} from "@/lib/api";

type RoleData = AdminRolesResponse["data"];

export default function AdminRolesPage() {
  const [data, setData] =
    useState<RoleData | null>(null);

  const [query, setQuery] =
    useState("");

  const [loading, setLoading] =
    useState(true);

  const [error, setError] =
    useState("");

  const load = useCallback(async () => {
    try {
      setLoading(true);
      setError("");

      const response =
        await getAdminRoles();

      setData(response.data);
    } catch (err) {
      setError(
        err instanceof Error
          ? err.message
          : "Unable to load RBAC registry.",
      );
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  const roles =
    useMemo(
      () => data?.roles ?? [],
      [data?.roles],
    );

  const filteredRoles =
    useMemo(() => {
      const search =
        query.trim().toLowerCase();

      if (!search) {
        return roles;
      }

      return roles.filter(
        (role) =>
          role.name
            .toLowerCase()
            .includes(search) ||
          role.slug
            .toLowerCase()
            .includes(search) ||
          role.permissions.some(
            (permission) =>
              permission.slug
                .toLowerCase()
                .includes(search) ||
              permission.name
                .toLowerCase()
                .includes(search),
          ),
      );
    }, [roles, query]);

  return (
    <main className="min-h-screen bg-[#050505] text-white">

      <section className="mx-auto max-w-7xl px-5 pb-24 pt-28 sm:px-8">
        <div className="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
          <div>
            <div className="mb-3 inline-flex items-center gap-2 rounded-full border border-red-500/15 bg-red-500/[0.04] px-4 py-2 text-[10px] font-bold uppercase tracking-[0.22em] text-red-400">
              <span className="h-1.5 w-1.5 rounded-full bg-red-500 shadow-[0_0_12px_rgba(239,68,68,.8)]" />
              Admin · Access Control
            </div>

            <h1 className="text-4xl font-black tracking-tight sm:text-5xl">
              Roles & Permissions
            </h1>

            <p className="mt-4 max-w-3xl text-sm leading-7 text-white/40">
              Live read-only representation
              of the backend RBAC registry.
              Permission names and role
              assignments shown here come
              directly from PostgreSQL.
            </p>
          </div>

          <div className="flex flex-wrap gap-3">
            <button
              type="button"
              onClick={() =>
                void load()
              }
              disabled={loading}
              className="cx-button cx-button-secondary rounded-xl px-5 py-3 text-sm font-semibold"
            >
              {loading
                ? "Refreshing..."
                : "Refresh"}
            </button>

            <Link
              href="/admin"
              className="cx-button cx-button-secondary rounded-xl px-5 py-3 text-sm font-semibold"
            >
              ← Command Center
            </Link>
          </div>
        </div>

        {error && (
          <div className="mt-6 rounded-2xl border border-red-500/20 bg-red-500/[0.06] px-4 py-3 text-sm text-red-200">
            {error}
          </div>
        )}

        <section className="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          {[
            [
              data?.summary.roles ?? "—",
              "Roles",
            ],
            [
              data?.summary.system_roles ??
                "—",
              "System Roles",
            ],
            [
              data?.summary.permissions ??
                "—",
              "Permissions",
            ],
            [
              data?.summary
                .permission_groups ?? "—",
              "Permission Groups",
            ],
          ].map(([value, label]) => (
            <div
              key={String(label)}
              className="rounded-3xl border border-white/[0.07] bg-[#09090b] p-6 shadow-[0_18px_60px_rgba(0,0,0,.35)]"
            >
              <div className="text-3xl font-black">
                {value}
              </div>

              <div className="mt-2 text-xs font-semibold uppercase tracking-wider text-white/30">
                {label}
              </div>
            </div>
          ))}
        </section>

        <section className="mt-5 rounded-3xl border border-red-500/12 bg-[#09090b] p-6 sm:p-8">
          <div className="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
            <div>
              <div className="text-[10px] font-bold uppercase tracking-[0.2em] text-red-400">
                Enforcement Boundary
              </div>

              <h2 className="mt-2 text-xl font-black">
                Registry is live. Mutation
                is not exposed.
              </h2>

              <p className="mt-2 max-w-3xl text-sm leading-7 text-white/35">
                CrypticX currently uses these
                permissions for backend
                authorization. This Admin V1
                view intentionally does not
                provide create, edit, delete,
                or permission-assignment
                controls because no safe role
                mutation API has been
                implemented.
              </p>
            </div>

            <div className="shrink-0 rounded-2xl border border-white/[0.07] bg-black/25 px-5 py-4">
              <div className="text-[10px] font-bold uppercase tracking-wider text-white/30">
                Mutation API
              </div>

              <div className="mt-1 text-sm font-bold text-white/60">
                Not Implemented
              </div>
            </div>
          </div>
        </section>

        <div className="mt-6">
          <input
            type="search"
            value={query}
            onChange={(event) =>
              setQuery(event.target.value)
            }
            placeholder="Search roles or permissions..."
            className="cx-input w-full rounded-2xl px-4 py-3 text-sm outline-none lg:max-w-xl"
          />
        </div>

        <section className="mt-6 space-y-5">
          {filteredRoles.map(
            (role: AdminRoleRecord) => {
              const groups =
                role.permissions.reduce<
                  Record<
                    string,
                    typeof role.permissions
                  >
                >(
                  (
                    result,
                    permission,
                  ) => {
                    (
                      result[
                        permission.group
                      ] ??= []
                    ).push(permission);

                    return result;
                  },
                  {},
                );

              return (
                <article
                  key={role.id}
                  className="rounded-3xl border border-white/[0.07] bg-[#09090b] p-6 shadow-[0_18px_60px_rgba(0,0,0,.3)] sm:p-8"
                >
                  <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                      <div className="flex flex-wrap items-center gap-3">
                        <h2 className="text-xl font-black">
                          {role.name}
                        </h2>

                        <span className="rounded-full border border-red-500/15 bg-red-500/[0.04] px-3 py-1 text-[10px] font-bold uppercase tracking-wider text-red-300">
                          {role.is_system
                            ? "System"
                            : "Custom"}
                        </span>
                      </div>

                      <div className="mt-2 font-mono text-xs text-white/25">
                        {role.slug}
                      </div>

                      {role.description && (
                        <p className="mt-3 max-w-3xl text-sm leading-6 text-white/35">
                          {role.description}
                        </p>
                      )}
                    </div>

                    <div className="rounded-xl border border-white/[0.06] bg-black/25 px-4 py-2 text-xs font-bold text-white/45">
                      {
                        role.permissions
                          .length
                      }{" "}
                      permissions
                    </div>
                  </div>

                  <div className="mt-7 grid gap-4 lg:grid-cols-2">
                    {Object.entries(
                      groups,
                    ).map(
                      ([
                        group,
                        permissions,
                      ]) => (
                        <div
                          key={group}
                          className="rounded-2xl border border-white/[0.06] bg-black/25 p-5"
                        >
                          <div className="text-[10px] font-bold uppercase tracking-[0.18em] text-red-400">
                            {group}
                          </div>

                          <div className="mt-4 flex flex-wrap gap-2">
                            {permissions.map(
                              (
                                permission,
                              ) => (
                                <span
                                  key={
                                    permission.id
                                  }
                                  title={
                                    permission.name
                                  }
                                  className="rounded-xl border border-white/[0.07] bg-white/[0.025] px-3 py-2 font-mono text-[11px] text-white/50"
                                >
                                  {
                                    permission.slug
                                  }
                                </span>
                              ),
                            )}
                          </div>
                        </div>
                      ),
                    )}
                  </div>

                  {role.permissions.length ===
                    0 && (
                    <div className="mt-6 rounded-2xl border border-white/[0.06] bg-black/20 p-5 text-sm text-white/30">
                      No permissions assigned.
                    </div>
                  )}
                </article>
              );
            },
          )}

          {!loading &&
            filteredRoles.length === 0 && (
              <div className="rounded-3xl border border-white/[0.07] bg-[#09090b] p-10 text-center">
                <h2 className="font-bold">
                  No roles found
                </h2>

                <p className="mt-2 text-sm text-white/30">
                  Try a different role or
                  permission search.
                </p>
              </div>
            )}
        </section>
      </section>
    </main>
  );
}
