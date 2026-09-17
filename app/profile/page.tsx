"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { enrollPasskey, getCurrentUser, getPasskeyStatus, getStoredToken, type PasskeyStatusResponse } from "@/lib/api";

type User = {
  id: string;
  name: string;
  email: string;
  roles?: Array<{
    id?: string;
    name: string;
  }>;
};

export default function ProfilePage() {
  const [user, setUser] = useState<User | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [passkeyLoading, setPasskeyLoading] = useState(false);
  const [passkeyMessage, setPasskeyMessage] = useState("");
  const [passkeyError, setPasskeyError] = useState("");
  const [passkeyStatus, setPasskeyStatus] =
    useState<PasskeyStatusResponse["data"] | null>(null);

  useEffect(() => {
    let mounted = true;

    async function loadProfile() {
      if (!getStoredToken()) {
        window.location.href = "/login";
        return;
      }

      try {
        const response = await getCurrentUser();

        const currentUser =
          response?.data?.user ??
          response?.data ??
          null;

        if (mounted) {
          setUser(currentUser);
        }

        const adminRole =
          currentUser?.roles?.some((role: { name: string }) => {
            const name = role.name.toLowerCase();

            return name === "owner" || name === "administrator";
          }) ?? false;

        if (adminRole) {
          try {
            const statusResponse = await getPasskeyStatus();

            if (mounted) {
              setPasskeyStatus(statusResponse.data);
            }
          } catch {
            // Keep profile usable even if passkey status cannot be loaded.
          }
        }
      } catch (err) {
        if (mounted) {
          setError(
            err instanceof Error
              ? err.message
              : "Unable to load your profile.",
          );
        }
      } finally {
        if (mounted) {
          setLoading(false);
        }
      }
    }

    loadProfile();

    return () => {
      mounted = false;
    };
  }, []);

  const roles =
    user?.roles?.map((role) => role.name).join(", ") ||
    "Security Researcher";

  const isAdmin =
    user?.roles?.some((role) => {
      const name = role.name.toLowerCase();

      return name === "owner" || name === "administrator";
    }) ?? false;

  async function handlePasskeyEnrollment() {
    setPasskeyError("");
    setPasskeyMessage("");
    setPasskeyLoading(true);

    try {
      await enrollPasskey();

      const statusResponse = await getPasskeyStatus();
      setPasskeyStatus(statusResponse.data);

      setPasskeyMessage(
        "Passkey registered successfully. This device can now be used for secure admin verification.",
      );
    } catch (err) {
      setPasskeyError(
        err instanceof Error
          ? err.message
          : "Unable to register this passkey.",
      );
    } finally {
      setPasskeyLoading(false);
    }
  }

  return (
    <main className="min-h-screen bg-[var(--cx-bg)] text-[var(--cx-text)]">

      <section className="mx-auto max-w-4xl px-5 py-14 sm:px-8">
        <div className="mb-8">
          <p className="text-xs font-bold uppercase tracking-[0.22em] text-[var(--cx-subtle)]">
            Account
          </p>

          <h1 className="mt-3 text-3xl font-bold tracking-tight">
            Profile
          </h1>

          <p className="mt-3 text-sm text-[var(--cx-muted)]">
            Your CrypticX Lab account information.
          </p>
        </div>

        {loading && (
          <div className="cx-card rounded-[30px] p-8">
            Loading profile...
          </div>
        )}

        {!loading && error && (
          <div className="cx-card rounded-[30px] p-8 text-red-300">
            {error}
          </div>
        )}

        {!loading && user && (
          <div className="cx-card rounded-[30px] p-7 sm:p-9">
            <div className="flex items-center gap-5">
              <div className="flex h-16 w-16 items-center justify-center rounded-2xl bg-[var(--cx-dark)] text-xl font-black text-white">
                {user.name?.charAt(0)?.toUpperCase() || "U"}
              </div>

              <div>
                <h2 className="text-2xl font-bold">
                  {user.name}
                </h2>

                <p className="mt-1 text-sm text-[var(--cx-muted)]">
                  {user.email}
                </p>
              </div>
            </div>

            <div className="mt-8 grid gap-4 sm:grid-cols-2">
              <div className="cx-inset-sm rounded-2xl p-5">
                <div className="text-xs uppercase tracking-wider text-[var(--cx-subtle)]">
                  Name
                </div>

                <div className="mt-2 font-semibold">
                  {user.name}
                </div>
              </div>

              <div className="cx-inset-sm rounded-2xl p-5">
                <div className="text-xs uppercase tracking-wider text-[var(--cx-subtle)]">
                  Email
                </div>

                <div className="mt-2 break-all font-semibold">
                  {user.email}
                </div>
              </div>

              <div className="cx-inset-sm rounded-2xl p-5 sm:col-span-2">
                <div className="text-xs uppercase tracking-wider text-[var(--cx-subtle)]">
                  Role
                </div>

                <div className="mt-2 font-semibold">
                  {roles}
                </div>
              </div>
            </div>

            {isAdmin && (
              <div className="mt-8 cx-inset-sm rounded-2xl p-5 sm:p-6">
                <div className="text-xs font-bold uppercase tracking-[0.16em] text-[var(--cx-subtle)]">
                  Admin Security
                </div>

                <h3 className="mt-3 text-lg font-bold">
                  Fingerprint / Passkey
                </h3>

                <p className="mt-2 text-sm leading-6 text-[var(--cx-muted)]">
                  Register this device as a passkey for secure administrator verification.
                  Your fingerprint itself is never sent to CrypticX Lab.
                </p>

                <div className="mt-5 grid gap-3 sm:grid-cols-3">
                  <div className="rounded-2xl border border-[var(--cx-border)] p-4">
                    <div className="text-xs uppercase tracking-wider text-[var(--cx-subtle)]">
                      Status
                    </div>
                    <div className="mt-2 font-bold">
                      {passkeyStatus?.configured ? "ACTIVE" : "NOT CONFIGURED"}
                    </div>
                  </div>

                  <div className="rounded-2xl border border-[var(--cx-border)] p-4">
                    <div className="text-xs uppercase tracking-wider text-[var(--cx-subtle)]">
                      Active passkeys
                    </div>
                    <div className="mt-2 font-bold">
                      {passkeyStatus?.active_count ?? 0}
                    </div>
                  </div>

                  <div className="rounded-2xl border border-[var(--cx-border)] p-4">
                    <div className="text-xs uppercase tracking-wider text-[var(--cx-subtle)]">
                      Last used
                    </div>
                    <div className="mt-2 font-bold">
                      {passkeyStatus?.credentials?.[0]?.last_used_at
                        ? new Date(
                            passkeyStatus.credentials[0].last_used_at,
                          ).toLocaleString()
                        : "Never"}
                    </div>
                  </div>
                </div>

                {passkeyMessage && (
                  <div className="mt-4 rounded-2xl border border-emerald-500/30 p-4 text-sm text-emerald-300">
                    {passkeyMessage}
                  </div>
                )}

                {passkeyError && (
                  <div className="mt-4 rounded-2xl border border-red-500/30 p-4 text-sm text-red-300">
                    {passkeyError}
                  </div>
                )}

                <button
                  type="button"
                  onClick={handlePasskeyEnrollment}
                  disabled={passkeyLoading}
                  className="cx-button cx-button-primary mt-5 disabled:cursor-not-allowed disabled:opacity-60"
                >
                  {passkeyLoading
                    ? "Waiting for device verification..."
                    : "Set up fingerprint / passkey"}
                </button>
              </div>
            )}

            <div className="mt-8">
              <Link
                href="/dashboard"
                className="cx-button cx-button-primary"
              >
                Back to Dashboard
              </Link>
            </div>
          </div>
        )}
      </section>
    </main>
  );
}
