"use client";

import { useState } from "react";
import Link from "next/link";
import Navbar from "@/components/Navbar";
import TrustedDevicesPanel from "@/components/TrustedDevicesPanel";

export default function SettingsPage() {
  const [notifications, setNotifications] = useState(true);

  return (
    <main className="min-h-screen bg-[var(--cx-bg)] text-[var(--cx-text)]">
      <Navbar />

      <section className="mx-auto max-w-6xl px-5 pb-20 pt-28 sm:px-8">
        <div>
          <div className="mb-3 inline-flex rounded-full px-4 py-2 text-xs font-semibold tracking-wide cx-inset-sm">
            ACCOUNT SECURITY
          </div>

          <h1 className="text-4xl font-bold tracking-tight sm:text-5xl">
            Settings
          </h1>

          <p className="mt-4 max-w-2xl text-base leading-7 text-[var(--cx-muted)]">
            Manage your profile, authentication methods, trusted devices,
            sessions, and security preferences.
          </p>
        </div>

        <section className="mt-10 cx-card rounded-[30px] p-6 sm:p-8">
          <h2 className="text-xl font-bold">Profile</h2>

          <div className="mt-6 grid gap-5 sm:grid-cols-2">
            <div>
              <label className="mb-2 block text-sm font-semibold">
                First Name
              </label>

              <input
                type="text"
                defaultValue="CrypticX"
                className="cx-input w-full rounded-2xl px-4 py-3 text-sm outline-none"
              />
            </div>

            <div>
              <label className="mb-2 block text-sm font-semibold">
                Last Name
              </label>

              <input
                type="text"
                defaultValue="User"
                className="cx-input w-full rounded-2xl px-4 py-3 text-sm outline-none"
              />
            </div>

            <div className="sm:col-span-2">
              <label className="mb-2 block text-sm font-semibold">
                Email Address
              </label>

              <input
                type="email"
                defaultValue="user@example.com"
                className="cx-input w-full rounded-2xl px-4 py-3 text-sm outline-none"
              />
            </div>
          </div>

          <button
            type="button"
            className="cx-button cx-button-primary mt-6 rounded-2xl px-5 py-3 text-sm font-semibold"
          >
            Save Profile
          </button>
        </section>

        <section className="mt-6 cx-card rounded-[30px] p-6 sm:p-8">
          <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <h2 className="text-xl font-bold">Authentication</h2>

              <p className="mt-1 text-sm text-[var(--cx-muted)]">
                Review the authentication capabilities currently available
                for your CrypticX Lab account.
              </p>
            </div>

            <span className="rounded-full px-3 py-1 text-xs font-semibold cx-inset-sm">
              Password Protected
            </span>
          </div>

          <div className="mt-6 space-y-3">
            <div className="cx-inset-sm rounded-2xl p-5">
              <div className="flex items-center justify-between gap-4">
                <div>
                  <div className="font-semibold">
                    Two-Factor Authentication
                  </div>

                  <div className="mt-1 text-xs text-[var(--cx-muted)]">
                    Additional login-factor enforcement is not implemented yet.
                  </div>
                </div>

                <span className="rounded-xl px-4 py-2 text-xs font-semibold cx-inset-sm">
                  Not Available
                </span>
              </div>
            </div>

            <div className="cx-inset-sm rounded-2xl p-5">
              <div className="flex items-center justify-between gap-4">
                <div>
                  <div className="font-semibold">
                    Passkeys / WebAuthn
                  </div>

                  <div className="mt-1 text-xs text-[var(--cx-muted)]">
                    Phishing-resistant WebAuthn or passkey authentication is
                    not implemented yet.
                  </div>
                </div>

                <span className="rounded-xl px-4 py-2 text-xs font-semibold cx-inset-sm">
                  Not Available
                </span>
              </div>
            </div>

            <div className="cx-inset-sm rounded-2xl p-5">
              <div className="flex items-center justify-between gap-4">
                <div>
                  <div className="font-semibold">Password</div>

                  <div className="mt-1 text-xs text-[var(--cx-muted)]">
                    CrypticX Lab currently uses password authentication with
                    short-lived API sessions.
                  </div>
                </div>

                <Link
                  href="/forgot-password"
                  className="cx-button cx-button-secondary rounded-xl px-4 py-2 text-sm font-semibold"
                >
                  Change
                </Link>
              </div>
            </div>
          </div>
        </section>

        <TrustedDevicesPanel />

        <section className="mt-6 cx-card rounded-[30px] p-6 sm:p-8">
          <h2 className="text-xl font-bold">Sessions</h2>

          <p className="mt-1 text-sm text-[var(--cx-muted)]">
            CrypticX Lab issues API access tokens with a 24-hour lifetime.
            Device-bound sessions are terminated when that trusted device is
            revoked.
          </p>

          <div className="mt-6 cx-inset-sm rounded-2xl p-5">
            <div className="flex flex-col gap-2">
              <div className="font-semibold">
                Session inventory
              </div>

              <div className="text-xs leading-5 text-[var(--cx-muted)]">
                A complete user-facing list of active sessions and a
                &quot;sign out all other sessions&quot; action are not
                implemented yet.
              </div>
            </div>
          </div>
        </section>

        <section className="mt-6 cx-card rounded-[30px] p-6 sm:p-8">
          <h2 className="text-xl font-bold">Notifications</h2>

          <div className="mt-5 cx-inset-sm rounded-2xl p-5">
            <div className="flex items-center justify-between gap-4">
              <div>
                <div className="font-semibold">
                  Security Notifications
                </div>

                <div className="mt-1 text-xs text-[var(--cx-muted)]">
                  Receive alerts for important account and security events.
                </div>
              </div>

              <button
                type="button"
                onClick={() => setNotifications((value) => !value)}
                className="cx-button cx-button-secondary rounded-xl px-4 py-2 text-sm font-semibold"
              >
                {notifications ? "Enabled" : "Disabled"}
              </button>
            </div>
          </div>
        </section>

        <section className="mt-6 cx-card rounded-[30px] p-6 sm:p-8">
          <div className="flex gap-4">
            <div className="cx-inset-sm flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl">
              ✓
            </div>

            <div>
              <h2 className="font-bold">Security architecture</h2>

              <p className="mt-2 text-sm leading-6 text-[var(--cx-muted)]">
                CrypticX Lab currently provides password authentication, 24-hour API access tokens, trusted-device verification for administrator access, device-bound administrator sessions, device revocation, and security audit metadata redaction. MFA, passkeys, and full user-facing session management are not implemented yet.
              </p>
            </div>
          </div>
        </section>
      </section>
    </main>
  );
}
