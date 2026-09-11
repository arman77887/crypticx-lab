"use client";

import Link from "next/link";
import { FormEvent, useState } from "react";
import Navbar from "@/components/Navbar";

export default function VerifyEmailPage() {
  const [email, setEmail] = useState("");

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
              Verify your email
            </h1>

            <p className="mt-3 text-sm leading-6 text-[var(--cx-muted)]">
              Check your inbox for a verification link to activate your
              CrypticX Lab account.
            </p>
          </div>

          <div className="cx-card rounded-[30px] p-7 sm:p-9">
            <div className="cx-inset rounded-3xl p-6 text-center">
              <div className="cx-raised-sm mx-auto flex h-14 w-14 items-center justify-center rounded-2xl">
                <span className="text-lg">✉</span>
              </div>

              <h2 className="mt-5 text-lg font-semibold">
                Check your inbox
              </h2>

              <p className="mx-auto mt-2 max-w-sm text-sm leading-6 text-[var(--cx-muted)]">
                We&apos;ll send a secure verification link to your registered
                email address.
              </p>
            </div>

            <form onSubmit={handleSubmit} className="mt-7">
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
                value={email}
                onChange={(event) => setEmail(event.target.value)}
                className="cx-input w-full"
                required
              />

              <button
                type="submit"
                className="cx-button cx-button-primary mt-5 w-full"
              >
                Resend Verification Email
              </button>
            </form>

            <div className="cx-divider my-7" />

            <div className="flex items-start gap-3">
              <span className="cx-raised-sm flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-xs font-bold">
                ✓
              </span>

              <p className="text-xs leading-5 text-[var(--cx-muted)]">
                Verification links should be short-lived, securely generated,
                single-use, and invalidated after successful verification.
              </p>
            </div>

            <div className="mt-6 text-center">
              <Link
                href="/login"
                className="text-sm font-semibold text-[var(--cx-text)]"
              >
                ← Back to Sign In
              </Link>
            </div>
          </div>

          <p className="mt-6 text-center text-xs leading-5 text-[var(--cx-subtle)]">
            Didn&apos;t receive the email? Check your spam folder or request a
            new verification link.
          </p>
        </div>
      </section>
    </main>
  );
}
