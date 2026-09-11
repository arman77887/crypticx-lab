"use client";

import Link from "next/link";
import { FormEvent, useState } from "react";
import Navbar from "@/components/Navbar";

export default function ResetPasswordPage() {
  const [showPassword, setShowPassword] = useState(false);
  const [showConfirmation, setShowConfirmation] = useState(false);

  function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
  }

  return (
    <main className="min-h-screen bg-[var(--cx-bg)] text-[var(--cx-text)]">
      <Navbar />

      <section className="mx-auto flex min-h-[calc(100vh-80px)] max-w-7xl items-center justify-center px-5 py-16 sm:px-8 lg:px-10">
        <div className="w-full max-w-md">
          <div className="mb-8 text-center">
            <div className="cx-raised-sm mx-auto flex h-16 w-16 items-center justify-center rounded-2xl">
              <span className="text-xl font-bold tracking-tight">CX</span>
            </div>

            <h1 className="mt-7 text-3xl font-semibold tracking-tight">
              Create a new password
            </h1>

            <p className="mt-3 text-sm leading-6 text-[var(--cx-muted)]">
              Choose a strong password to secure your CrypticX Lab account.
            </p>
          </div>

          <div className="cx-card rounded-[30px] p-7 sm:p-9">
            <form onSubmit={handleSubmit}>
              <label
                htmlFor="password"
                className="mb-2 block text-sm font-semibold"
              >
                New password
              </label>

              <div className="relative">
                <input
                  id="password"
                  name="password"
                  type={showPassword ? "text" : "password"}
                  autoComplete="new-password"
                  placeholder="Enter your new password"
                  minLength={8}
                  className="cx-input w-full pr-20"
                  required
                />

                <button
                  type="button"
                  onClick={() => setShowPassword(!showPassword)}
                  className="absolute right-3 top-1/2 -translate-y-1/2 rounded-lg px-2 py-1 text-xs font-semibold text-[var(--cx-muted)] hover:text-[var(--cx-text)]"
                >
                  {showPassword ? "Hide" : "Show"}
                </button>
              </div>

              <p className="mt-2 text-xs text-[var(--cx-subtle)]">
                Use at least 8 characters. A longer passphrase is recommended.
              </p>

              <label
                htmlFor="password_confirmation"
                className="mb-2 mt-6 block text-sm font-semibold"
              >
                Confirm new password
              </label>

              <div className="relative">
                <input
                  id="password_confirmation"
                  name="password_confirmation"
                  type={showConfirmation ? "text" : "password"}
                  autoComplete="new-password"
                  placeholder="Repeat your new password"
                  minLength={8}
                  className="cx-input w-full pr-20"
                  required
                />

                <button
                  type="button"
                  onClick={() => setShowConfirmation(!showConfirmation)}
                  className="absolute right-3 top-1/2 -translate-y-1/2 rounded-lg px-2 py-1 text-xs font-semibold text-[var(--cx-muted)] hover:text-[var(--cx-text)]"
                >
                  {showConfirmation ? "Hide" : "Show"}
                </button>
              </div>

              <button
                type="submit"
                className="cx-button cx-button-primary mt-7 w-full"
              >
                Update Password
              </button>
            </form>

            <div className="cx-inset-sm mt-7 rounded-2xl p-4">
              <div className="flex gap-3">
                <span className="cx-raised-sm flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-xs font-bold">
                  ✓
                </span>

                <div>
                  <p className="text-sm font-semibold">
                    Secure password recovery
                  </p>

                  <p className="mt-1 text-xs leading-5 text-[var(--cx-muted)]">
                    Reset tokens should be short-lived, single-use, securely
                    generated, and invalidated after a successful reset.
                  </p>
                </div>
              </div>
            </div>

            <div className="cx-divider my-7" />

            <div className="text-center">
              <Link
                href="/login"
                className="text-sm font-semibold text-[var(--cx-text)]"
              >
                ← Back to Sign In
              </Link>
            </div>
          </div>

          <p className="mt-6 text-center text-xs leading-5 text-[var(--cx-subtle)]">
            This interface is prepared for secure backend authentication
            integration.
          </p>
        </div>
      </section>
    </main>
  );
}
