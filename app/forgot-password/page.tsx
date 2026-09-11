"use client";

import Link from "next/link";
import { FormEvent } from "react";
import Navbar from "@/components/Navbar";

export default function ForgotPasswordPage() {
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
              Reset your password
            </h1>

            <p className="mt-3 text-sm leading-6 text-[var(--cx-muted)]">
              Enter your account email and we&apos;ll send instructions to
              create a new password.
            </p>
          </div>

          <div className="cx-card rounded-[30px] p-7 sm:p-9">
            <form onSubmit={handleSubmit}>
              <label
                htmlFor="email"
                className="mb-2 block text-sm font-semibold"
              >
                Email address
              </label>

              <input
                id="email"
                name="email"
                type="email"
                autoComplete="email"
                placeholder="you@example.com"
                className="cx-input w-full"
                required
              />

              <button
                type="submit"
                className="cx-button cx-button-primary mt-7 w-full"
              >
                Send Reset Link
              </button>
            </form>

            <div className="cx-inset-sm mt-7 rounded-2xl p-4">
              <div className="flex gap-3">
                <span className="cx-raised-sm flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-xs font-bold">
                  i
                </span>

                <p className="text-xs leading-5 text-[var(--cx-muted)]">
                  For security, the system should use a generic response so
                  an attacker cannot determine whether an email address is
                  registered.
                </p>
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
            Password reset links will be short-lived, single-use, securely
            generated, and invalidated after successful password recovery when
            the backend is connected.
          </p>
        </div>
      </section>
    </main>
  );
}
