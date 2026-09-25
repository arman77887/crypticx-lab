"use client";

import Link from "next/link";
import { FormEvent, useState } from "react";
import { register } from "@/lib/api";
import { trackEvent } from "@/lib/analytics";

export default function RegisterPage() {
  const [showPassword, setShowPassword] = useState(false);
  const [showConfirmPassword, setShowConfirmPassword] = useState(false);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState("");
  const [success, setSuccess] = useState("");

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();

    const form = new FormData(event.currentTarget);

    const firstName = String(form.get("firstName") ?? "").trim();
    const lastName = String(form.get("lastName") ?? "").trim();
    const email = String(form.get("email") ?? "").trim();
    const password = String(form.get("password") ?? "");
    const passwordConfirmation = String(
      form.get("confirmPassword") ?? "",
    );

    if (password.length < 12) {
      setError("Password must contain at least 12 characters.");
      return;
    }

    if (password !== passwordConfirmation) {
      setError("Password confirmation does not match.");
      return;
    }

    const name = `${firstName} ${lastName}`.trim();

    setError("");
    setSuccess("");
    setLoading(true);

    try {
      const response = await register(
        name,
        email,
        password,
        passwordConfirmation,
      );

      trackEvent("sign_up", {
        method: "email",
      });
      setSuccess(response.message);
    } catch (err) {
      setError(
        err instanceof Error
          ? err.message
          : "Unable to create your account.",
      );
    } finally {
      setLoading(false);
    }
  }

  return (
    <main className="min-h-screen bg-[var(--cx-bg)] text-[var(--cx-text)]">

      <section className="mx-auto flex min-h-[calc(100vh-80px)] max-w-7xl items-center justify-center px-5 py-16 sm:px-8 lg:px-10">
        <div className="w-full max-w-lg">
          <div className="mb-8 text-center">
            <div className="cx-raised-sm mx-auto flex h-16 w-16 items-center justify-center rounded-2xl">
              <img
                src="/brand/crypticx2.png"
                alt="CrypticX Lab"
                width={56}
                height={56}
                className="h-14 w-14 object-contain"
              />
            </div>

            <h1 className="mt-7 text-3xl font-semibold tracking-tight">
              Create your account
            </h1>

            <p className="mt-3 text-sm leading-6 text-[var(--cx-muted)]">
              Start exploring CrypticX Lab and build your security workflow.
            </p>
          </div>

          <div className="cx-card rounded-[30px] p-7 sm:p-9">
            {success ? (
              <div className="py-4 text-center">
                <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full border border-emerald-500/20 bg-emerald-500/[0.08] text-2xl text-emerald-300">
                  ✓
                </div>

                <h2 className="mt-6 text-2xl font-semibold tracking-tight text-[var(--cx-text)]">
                  Welcome to CrypticX Lab
                </h2>

                <p className="mt-3 text-sm leading-6 text-[var(--cx-muted)]">
                  Your account has been created successfully.
                </p>

                <div className="cx-inset-sm mt-6 rounded-2xl p-5 text-left">
                  <div className="text-sm font-semibold text-emerald-300">
                    Account Verified
                  </div>

                  <p className="mt-2 text-sm leading-6 text-[var(--cx-muted)]">
                    Your account was verified automatically. This browser
                    has been registered as your authorized login device.
                  </p>
                </div>

                <p className="mt-6 text-sm leading-6 text-[var(--cx-muted)]">
                  You can now sign in with your registered email and
                  password from this authorized device.
                </p>

                <div className="mt-7 border-t border-[var(--cx-border)] pt-6">
                  <Link
                    href="/"
                    className="cx-button cx-button-primary inline-flex"
                  >
                    Return to Homepage
                  </Link>
                </div>

                <p className="mt-5 text-xs leading-5 text-[var(--cx-subtle)]">
                  For your security, sign-in from an unregistered device
                  will be rejected.
                </p>
              </div>
            ) : (
            <form onSubmit={handleSubmit}>
              <div className="grid gap-6 sm:grid-cols-2">
                <div>
                  <label
                    htmlFor="firstName"
                    className="mb-2 block text-sm font-semibold"
                  >
                    First name
                  </label>

                  <input
                    id="firstName"
                    name="firstName"
                    type="text"
                    autoComplete="given-name"
                    placeholder="First name"
                    className="cx-input w-full"
                    required
                  />
                </div>

                <div>
                  <label
                    htmlFor="lastName"
                    className="mb-2 block text-sm font-semibold"
                  >
                    Last name
                  </label>

                  <input
                    id="lastName"
                    name="lastName"
                    type="text"
                    autoComplete="family-name"
                    placeholder="Last name"
                    className="cx-input w-full"
                    required
                  />
                </div>
              </div>

              <div className="mt-6">
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
              </div>

              <div className="mt-6">
                <label
                  htmlFor="password"
                  className="mb-2 block text-sm font-semibold"
                >
                  Password
                </label>

                <div className="relative">
                  <input
                    id="password"
                    name="password"
                    type={showPassword ? "text" : "password"}
                    autoComplete="new-password"
                    placeholder="Create a strong password"
                    className="cx-input w-full pr-16"
                    minLength={12}
                    required
                  />

                  <button
                    type="button"
                    onClick={() => setShowPassword((value) => !value)}
                    className="absolute right-3 top-1/2 -translate-y-1/2 rounded-lg px-2 py-1 text-xs font-semibold text-[var(--cx-subtle)]"
                  >
                    {showPassword ? "Hide" : "Show"}
                  </button>
                </div>

                <p className="mt-2 text-xs text-[var(--cx-subtle)]">
                  Use at least 12 characters with a strong combination of
                  letters, numbers, and symbols.
                </p>
              </div>

              <div className="mt-6">
                <label
                  htmlFor="confirmPassword"
                  className="mb-2 block text-sm font-semibold"
                >
                  Confirm password
                </label>

                <div className="relative">
                  <input
                    id="confirmPassword"
                    name="confirmPassword"
                    type={showConfirmPassword ? "text" : "password"}
                    autoComplete="new-password"
                    placeholder="Repeat your password"
                    className="cx-input w-full pr-16"
                    minLength={12}
                    required
                  />

                  <button
                    type="button"
                    onClick={() =>
                      setShowConfirmPassword((value) => !value)
                    }
                    className="absolute right-3 top-1/2 -translate-y-1/2 rounded-lg px-2 py-1 text-xs font-semibold text-[var(--cx-subtle)]"
                  >
                    {showConfirmPassword ? "Hide" : "Show"}
                  </button>
                </div>
              </div>

              <label className="mt-6 flex cursor-pointer items-start gap-3 text-sm text-[var(--cx-muted)]">
                <input
                  type="checkbox"
                  name="terms"
                  className="mt-0.5 h-4 w-4 shrink-0 rounded border-[var(--cx-border)]"
                  required
                />

                <span>
                  I agree to the CrypticX Lab terms and understand that
                  security testing must only be performed on systems I own or
                  am explicitly authorized to assess.
                </span>
              </label>

              {error && (
                <div
                  role="alert"
                  className="cx-inset-sm mt-5 rounded-2xl p-4 text-sm text-[var(--cx-text)]"
                >
                  {error}
                </div>
              )}

              <button
                type="submit"
                disabled={loading}
                className="cx-button cx-button-primary mt-7 w-full disabled:cursor-not-allowed disabled:opacity-60"
              >
                {loading ? "Creating account..." : "Create Account"}
              </button>
            </form>
            )}

            {!success && (
              <p className="mt-7 text-center text-sm text-[var(--cx-muted)]">
                Already have an account?{" "}
                <Link
                  href="/login"
                  className="font-semibold text-[var(--cx-text)]"
                >
                  Sign in
                </Link>
              </p>
            )}
          </div>

          <div className="cx-inset-sm mt-6 rounded-2xl p-4">
            <p className="text-center text-xs leading-5 text-[var(--cx-muted)]">
              Account registration and authenticated sessions are securely
              connected to the CrypticX API. Use your registered email and
              password to sign in.
            </p>
          </div>
        </div>
      </section>
    </main>
  );
}
