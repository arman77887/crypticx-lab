"use client";

import { FormEvent, useEffect, useMemo, useState } from "react";
import Link from "next/link";
import {
  ApiUser,
  createAdminUser,
  deleteAdminUser,
  getAdminUsers,
  updateAdminUser,
  updateAdminUserRole,
} from "@/lib/api";

const roles = [
  { name: "Owner", slug: "owner" },
  { name: "Administrator", slug: "administrator" },
  { name: "Security Researcher", slug: "security-researcher" },
  { name: "Analyst", slug: "analyst" },
  { name: "Auditor", slug: "auditor" },
] as const;

type RoleFilter =
  | "All"
  | "Owner"
  | "Administrator"
  | "Security Researcher"
  | "Analyst"
  | "Auditor";

type VerificationFilter = "All" | "Verified" | "Unverified";

function primaryRole(user: ApiUser) {
  return user.roles?.[0] ?? null;
}

function verificationState(
  user: ApiUser,
): "Verified" | "Unverified" {
  return user.email_verified_at ? "Verified" : "Unverified";
}

function ErrorBox({ message }: { message: string }) {
  if (!message) {
    return null;
  }

  return (
    <div className="rounded-2xl border border-red-500/20 bg-red-500/[0.05] px-4 py-3 text-sm text-red-200">
      {message}
    </div>
  );
}

export default function AdminUsersPage() {
  const [users, setUsers] = useState<ApiUser[]>([]);
  const [query, setQuery] = useState("");
  const [roleFilter, setRoleFilter] =
    useState<RoleFilter>("All");
  const [verificationFilter, setVerificationFilter] =
    useState<VerificationFilter>("All");

  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");

  const [showCreate, setShowCreate] = useState(false);
  const [editing, setEditing] = useState<ApiUser | null>(null);

  const [createName, setCreateName] = useState("");
  const [createEmail, setCreateEmail] = useState("");
  const [createRole, setCreateRole] =
    useState("security-researcher");
  const [createPassword, setCreatePassword] = useState("");
  const [createPasswordConfirmation, setCreatePasswordConfirmation] =
    useState("");

  const [editName, setEditName] = useState("");
  const [editEmail, setEditEmail] = useState("");
  const [editRole, setEditRole] = useState("");
  const [editPassword, setEditPassword] = useState("");
  const [editPasswordConfirmation, setEditPasswordConfirmation] =
    useState("");

  async function loadUsers() {
    try {
      setLoading(true);
      setError("");

      const response = await getAdminUsers();
      setUsers(response.data);
    } catch (err) {
      setError(
        err instanceof Error
          ? err.message
          : "Unable to load users.",
      );
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    void loadUsers();
  }, []);

  const filteredUsers = useMemo(() => {
    const search = query.trim().toLowerCase();

    return users.filter((user) => {
      const roleName = primaryRole(user)?.name ?? "No Role";
      const verification = verificationState(user);

      const matchesQuery =
        !search ||
        user.id.toLowerCase().includes(search) ||
        user.name.toLowerCase().includes(search) ||
        user.email.toLowerCase().includes(search);

      const matchesRole =
        roleFilter === "All" || roleName === roleFilter;

      const matchesVerification =
        verificationFilter === "All" ||
        verification === verificationFilter;

      return (
        matchesQuery &&
        matchesRole &&
        matchesVerification
      );
    });
  }, [users, query, roleFilter, verificationFilter]);

  const verifiedCount = users.filter(
    (user) => Boolean(user.email_verified_at),
  ).length;

  const unverifiedCount = users.length - verifiedCount;

  const ownerCount = users.filter(
    (user) => primaryRole(user)?.slug === "owner",
  ).length;

  function openEdit(user: ApiUser) {
    setError("");
    setEditing(user);
    setEditName(user.name);
    setEditEmail(user.email);
    setEditRole(primaryRole(user)?.slug ?? "");
    setEditPassword("");
    setEditPasswordConfirmation("");
  }

  function closeEdit() {
    if (busy) return;
    setEditing(null);
  }

  async function handleCreate(event: FormEvent) {
    event.preventDefault();

    try {
      setBusy(true);
      setError("");

      await createAdminUser({
        name: createName.trim(),
        email: createEmail.trim(),
        role: createRole,
        password: createPassword,
        password_confirmation: createPasswordConfirmation,
      });

      setCreateName("");
      setCreateEmail("");
      setCreateRole("security-researcher");
      setCreatePassword("");
      setCreatePasswordConfirmation("");
      setShowCreate(false);

      await loadUsers();
    } catch (err) {
      setError(
        err instanceof Error
          ? err.message
          : "Unable to create user.",
      );
    } finally {
      setBusy(false);
    }
  }

  async function handleUpdate(event: FormEvent) {
    event.preventDefault();

    if (!editing) return;

    try {
      setBusy(true);
      setError("");

      await updateAdminUser(editing.id, {
        name: editName.trim(),
        email: editEmail.trim(),
        ...(editPassword
          ? {
              password: editPassword,
              password_confirmation: editPasswordConfirmation,
            }
          : {}),
      });

      if (
        editRole &&
        editRole !== primaryRole(editing)?.slug
      ) {
        await updateAdminUserRole(editing.id, editRole);
      }

      setEditing(null);
      await loadUsers();
    } catch (err) {
      setError(
        err instanceof Error
          ? err.message
          : "Unable to update user.",
      );
    } finally {
      setBusy(false);
    }
  }

  async function handleDelete(user: ApiUser) {
    const confirmed = window.confirm(
      `Delete ${user.name} (${user.email})?\n\nThis revokes their API tokens and removes the account. This action cannot be undone.`,
    );

    if (!confirmed) return;

    try {
      setBusy(true);
      setError("");

      await deleteAdminUser(user.id);

      if (editing?.id === user.id) {
        setEditing(null);
      }

      await loadUsers();
    } catch (err) {
      setError(
        err instanceof Error
          ? err.message
          : "Unable to delete user.",
      );
    } finally {
      setBusy(false);
    }
  }

  return (
    <main className="min-h-screen bg-[#050505] text-white">

      <section className="mx-auto max-w-7xl px-5 pb-24 pt-28 sm:px-8">
        <div className="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
          <div>
            <div className="mb-3 inline-flex items-center gap-2 rounded-full border border-red-500/15 bg-red-500/[0.04] px-4 py-2 text-[10px] font-bold uppercase tracking-[0.22em] text-red-400">
              <span className="h-1.5 w-1.5 rounded-full bg-red-500 shadow-[0_0_12px_rgba(239,68,68,.7)]" />
              Admin · Identity Operations
            </div>

            <h1 className="text-4xl font-black tracking-tight sm:text-5xl">
              User Management
            </h1>

            <p className="mt-4 max-w-2xl text-sm leading-7 text-white/40">
              Manage CrypticX Lab identities, role assignments,
              verification visibility, credentials and account removal.
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
              onClick={() => {
                setError("");
                setShowCreate(true);
              }}
              className="cx-button cx-button-primary rounded-xl px-5 py-3 text-sm font-semibold"
            >
              + Create User
            </button>
          </div>
        </div>

        <ErrorBox message={error} />

        <section className="mt-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          {[
            [users.length, "Total Users", "All platform identities"],
            [verifiedCount, "Verified", "Email verified identities"],
            [unverifiedCount, "Unverified", "Awaiting email verification"],
            [ownerCount, "Owners", "Protected platform owners"],
          ].map(([value, label, detail]) => (
            <div
              key={String(label)}
              className="rounded-2xl border border-white/[0.07] bg-[#09090b] p-5 shadow-[0_14px_40px_rgba(0,0,0,.35)]"
            >
              <div className="flex items-start justify-between">
                <div className="text-3xl font-black">
                  {loading ? "—" : value}
                </div>
                <span className="mt-1 h-2 w-2 rounded-full bg-red-500/70 shadow-[0_0_12px_rgba(239,68,68,.45)]" />
              </div>

              <div className="mt-3 text-xs font-bold uppercase tracking-[0.16em] text-white/60">
                {label}
              </div>

              <div className="mt-1 text-xs text-white/25">
                {detail}
              </div>
            </div>
          ))}
        </section>

        <section className="mt-5 rounded-2xl border border-white/[0.07] bg-[#09090b] p-5">
          <div className="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
            <input
              type="search"
              value={query}
              onChange={(event) => setQuery(event.target.value)}
              placeholder="Search identity, email or UUID..."
              className="cx-input w-full rounded-xl px-4 py-3 text-sm xl:max-w-md"
            />

            <button
              type="button"
              onClick={() => void loadUsers()}
              disabled={loading}
              className="cx-button cx-button-secondary rounded-xl px-4 py-3 text-xs font-semibold disabled:opacity-40"
            >
              {loading ? "Synchronizing..." : "↻ Refresh Directory"}
            </button>
          </div>

          <div className="mt-4 flex flex-wrap gap-2">
            {[
              "All",
              "Owner",
              "Administrator",
              "Security Researcher",
              "Analyst",
              "Auditor",
            ].map((item) => (
              <button
                key={item}
                type="button"
                onClick={() =>
                  setRoleFilter(item as RoleFilter)
                }
                className={
                  roleFilter === item
                    ? "cx-button cx-button-primary rounded-xl px-3 py-2 text-xs font-semibold"
                    : "cx-button cx-button-secondary rounded-xl px-3 py-2 text-xs font-semibold"
                }
              >
                {item}
              </button>
            ))}
          </div>

          <div className="mt-3 flex flex-wrap gap-2">
            {(["All", "Verified", "Unverified"] as const).map(
              (item) => (
                <button
                  key={item}
                  type="button"
                  onClick={() =>
                    setVerificationFilter(item)
                  }
                  className={
                    verificationFilter === item
                      ? "cx-button cx-button-primary rounded-xl px-3 py-2 text-xs font-semibold"
                      : "cx-button cx-button-secondary rounded-xl px-3 py-2 text-xs font-semibold"
                  }
                >
                  Verification: {item}
                </button>
              ),
            )}
          </div>
        </section>

        <section className="mt-5 overflow-hidden rounded-2xl border border-white/[0.07] bg-[#09090b]">
          <div className="flex flex-col gap-3 border-b border-white/[0.06] px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <div className="text-[10px] font-bold uppercase tracking-[0.22em] text-red-400/75">
                Identity Directory
              </div>

              <h2 className="mt-1 text-base font-bold">
                Platform Users
              </h2>
            </div>

            <div className="text-xs text-white/30">
              {loading
                ? "Loading real identities..."
                : `${filteredUsers.length} displayed`}
            </div>
          </div>

          {loading ? (
            <div className="space-y-3 p-5">
              {[1, 2, 3].map((item) => (
                <div
                  key={item}
                  className="animate-pulse rounded-xl border border-white/[0.06] bg-black/20 p-5"
                >
                  <div className="h-4 w-48 rounded bg-white/[0.05]" />
                  <div className="mt-3 h-3 w-64 rounded bg-white/[0.04]" />
                </div>
              ))}
            </div>
          ) : filteredUsers.length ? (
            <div className="divide-y divide-white/[0.05]">
              {filteredUsers.map((user) => {
                const role = primaryRole(user);
                const verified = Boolean(
                  user.email_verified_at,
                );

                return (
                  <article
                    key={user.id}
                    className="p-5 transition hover:bg-red-500/[0.025]"
                  >
                    <div className="flex flex-col gap-5 xl:flex-row xl:items-center xl:justify-between">
                      <div className="min-w-0">
                        <div className="flex flex-wrap items-center gap-2">
                          <h3 className="truncate font-bold text-white/90">
                            {user.name}
                          </h3>

                          <span className="rounded-full border border-red-500/15 bg-red-500/[0.05] px-3 py-1 text-[10px] font-bold uppercase tracking-wider text-red-300">
                            {role?.name ?? "No Role"}
                          </span>

                          <span
                            className={
                              verified
                                ? "rounded-full border border-emerald-500/15 bg-emerald-500/[0.05] px-3 py-1 text-[10px] font-bold uppercase tracking-wider text-emerald-300"
                                : "rounded-full border border-amber-500/15 bg-amber-500/[0.05] px-3 py-1 text-[10px] font-bold uppercase tracking-wider text-amber-300"
                            }
                          >
                            {verified
                              ? "Verified"
                              : "Unverified"}
                          </span>
                        </div>

                        <div className="mt-2 truncate text-sm text-white/40">
                          {user.email}
                        </div>

                        <div className="mt-2 flex flex-wrap gap-x-5 gap-y-1 text-[10px] text-white/25">
                          <span>ID: {user.id}</span>
                          <span>
                            Created:{" "}
                            {new Date(
                              user.created_at,
                            ).toLocaleString()}
                          </span>
                        </div>
                      </div>

                      <div className="flex shrink-0 flex-wrap gap-2">
                        <Link
                          href={`/admin/users/${user.id}`}
                          className="cx-button cx-button-secondary rounded-xl px-4 py-2 text-xs font-semibold"
                        >
                          View
                        </Link>

                        <button
                          type="button"
                          onClick={() => openEdit(user)}
                          className="cx-button cx-button-secondary rounded-xl px-4 py-2 text-xs font-semibold"
                        >
                          Manage
                        </button>

                        <button
                          type="button"
                          disabled={busy}
                          onClick={() =>
                            void handleDelete(user)
                          }
                          className="rounded-xl border border-red-500/20 bg-red-500/[0.05] px-4 py-2 text-xs font-semibold text-red-300 transition hover:bg-red-500/[0.10] disabled:opacity-40"
                        >
                          Delete
                        </button>
                      </div>
                    </div>
                  </article>
                );
              })}
            </div>
          ) : (
            <div className="px-5 py-16 text-center">
              <div className="text-sm font-semibold text-white/50">
                No users match these filters.
              </div>

              <div className="mt-2 text-xs text-white/25">
                Change the search, role or verification filter.
              </div>
            </div>
          )}
        </section>

        <div className="mt-5 rounded-2xl border border-white/[0.06] bg-[#09090b] p-4 text-xs leading-6 text-white/30">
          Account suspension is intentionally unavailable until a
          real backend suspension state and authentication enforcement
          are implemented. Email verification is displayed separately
          and is not treated as account activity status.
        </div>
      </section>

      {showCreate && (
        <div className="fixed inset-0 z-[100] flex items-center justify-center bg-black/80 p-4 backdrop-blur-sm">
          <form
            onSubmit={handleCreate}
            className="w-full max-w-xl rounded-3xl border border-red-500/15 bg-[#09090b] p-6 shadow-[0_30px_100px_rgba(0,0,0,.8)]"
          >
            <div className="flex items-start justify-between gap-4">
              <div>
                <div className="text-[10px] font-bold uppercase tracking-[0.22em] text-red-400">
                  Identity Provisioning
                </div>
                <h2 className="mt-2 text-2xl font-black">
                  Create User
                </h2>
              </div>

              <button
                type="button"
                onClick={() => setShowCreate(false)}
                disabled={busy}
                className="text-xl text-white/35 hover:text-white"
              >
                ×
              </button>
            </div>

            <div className="mt-6 space-y-4">
              <input
                required
                value={createName}
                onChange={(e) =>
                  setCreateName(e.target.value)
                }
                placeholder="Full name"
                className="cx-input w-full rounded-xl px-4 py-3"
              />

              <input
                required
                type="email"
                value={createEmail}
                onChange={(e) =>
                  setCreateEmail(e.target.value)
                }
                placeholder="Email address"
                className="cx-input w-full rounded-xl px-4 py-3"
              />

              <select
                value={createRole}
                onChange={(e) =>
                  setCreateRole(e.target.value)
                }
                className="cx-input w-full rounded-xl px-4 py-3"
              >
                {roles.map((role) => (
                  <option
                    key={role.slug}
                    value={role.slug}
                  >
                    {role.name}
                  </option>
                ))}
              </select>

              <input
                required
                minLength={12}
                type="password"
                value={createPassword}
                onChange={(e) =>
                  setCreatePassword(e.target.value)
                }
                placeholder="Password · minimum 12 characters"
                className="cx-input w-full rounded-xl px-4 py-3"
              />

              <input
                required
                minLength={12}
                type="password"
                value={createPasswordConfirmation}
                onChange={(e) =>
                  setCreatePasswordConfirmation(
                    e.target.value,
                  )
                }
                placeholder="Confirm password"
                className="cx-input w-full rounded-xl px-4 py-3"
              />
            </div>

            <div className="mt-6 flex justify-end gap-3">
              <button
                type="button"
                disabled={busy}
                onClick={() => setShowCreate(false)}
                className="cx-button cx-button-secondary rounded-xl px-5 py-3 text-sm font-semibold"
              >
                Cancel
              </button>

              <button
                type="submit"
                disabled={busy}
                className="cx-button cx-button-primary rounded-xl px-5 py-3 text-sm font-semibold disabled:opacity-40"
              >
                {busy ? "Creating..." : "Create Identity"}
              </button>
            </div>
          </form>
        </div>
      )}

      {editing && (
        <div className="fixed inset-0 z-[100] flex items-center justify-center bg-black/80 p-4 backdrop-blur-sm">
          <form
            onSubmit={handleUpdate}
            className="max-h-[92vh] w-full max-w-xl overflow-y-auto rounded-3xl border border-red-500/15 bg-[#09090b] p-6 shadow-[0_30px_100px_rgba(0,0,0,.8)]"
          >
            <div className="flex items-start justify-between gap-4">
              <div>
                <div className="text-[10px] font-bold uppercase tracking-[0.22em] text-red-400">
                  Identity Control
                </div>
                <h2 className="mt-2 text-2xl font-black">
                  Manage User
                </h2>
                <div className="mt-1 text-xs text-white/30">
                  {editing.email}
                </div>
              </div>

              <button
                type="button"
                onClick={closeEdit}
                disabled={busy}
                className="text-xl text-white/35 hover:text-white"
              >
                ×
              </button>
            </div>

            <div className="mt-6 space-y-4">
              <div>
                <label className="mb-2 block text-[10px] font-bold uppercase tracking-wider text-white/35">
                  Name
                </label>
                <input
                  required
                  value={editName}
                  onChange={(e) =>
                    setEditName(e.target.value)
                  }
                  className="cx-input w-full rounded-xl px-4 py-3"
                />
              </div>

              <div>
                <label className="mb-2 block text-[10px] font-bold uppercase tracking-wider text-white/35">
                  Email
                </label>
                <input
                  required
                  type="email"
                  value={editEmail}
                  onChange={(e) =>
                    setEditEmail(e.target.value)
                  }
                  className="cx-input w-full rounded-xl px-4 py-3"
                />
              </div>

              <div>
                <label className="mb-2 block text-[10px] font-bold uppercase tracking-wider text-white/35">
                  Platform Role
                </label>
                <select
                  value={editRole}
                  onChange={(e) =>
                    setEditRole(e.target.value)
                  }
                  className="cx-input w-full rounded-xl px-4 py-3"
                >
                  {roles.map((role) => (
                    <option
                      key={role.slug}
                      value={role.slug}
                    >
                      {role.name}
                    </option>
                  ))}
                </select>
              </div>

              <div className="border-t border-white/[0.06] pt-5">
                <div className="text-[10px] font-bold uppercase tracking-[0.18em] text-white/35">
                  Optional Password Reset
                </div>

                <input
                  minLength={12}
                  type="password"
                  value={editPassword}
                  onChange={(e) =>
                    setEditPassword(e.target.value)
                  }
                  placeholder="New password · leave blank to keep current"
                  className="cx-input mt-3 w-full rounded-xl px-4 py-3"
                />

                {editPassword && (
                  <input
                    required
                    minLength={12}
                    type="password"
                    value={editPasswordConfirmation}
                    onChange={(e) =>
                      setEditPasswordConfirmation(
                        e.target.value,
                      )
                    }
                    placeholder="Confirm new password"
                    className="cx-input mt-3 w-full rounded-xl px-4 py-3"
                  />
                )}
              </div>

              <div className="rounded-xl border border-white/[0.06] bg-black/20 p-4 text-xs leading-6 text-white/30">
                Owner role changes are protected by the backend.
                The last Owner cannot be demoted or deleted, and
                non-Owners cannot modify Owner accounts.
              </div>
            </div>

            <div className="mt-6 flex justify-end gap-3">
              <button
                type="button"
                disabled={busy}
                onClick={closeEdit}
                className="cx-button cx-button-secondary rounded-xl px-5 py-3 text-sm font-semibold"
              >
                Cancel
              </button>

              <button
                type="submit"
                disabled={busy}
                className="cx-button cx-button-primary rounded-xl px-5 py-3 text-sm font-semibold disabled:opacity-40"
              >
                {busy ? "Saving..." : "Save Changes"}
              </button>
            </div>
          </form>
        </div>
      )}
    </main>
  );
}
