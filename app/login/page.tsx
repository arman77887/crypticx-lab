"use client";

import Link from "next/link";
import { FormEvent, useState } from "react";
import { useRouter } from "next/navigation";
import { login } from "@/lib/api";

export default function LoginPage() {
  const router = useRouter();

  const [showPassword, setShowPassword] = useState(false);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState("");

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();

    const form = new FormData(event.currentTarget);
    const email = String(form.get("email") ?? "").trim();
    const password = String(form.get("password") ?? "");
    const remember = form.get("remember") === "on";

    setError("");
    setLoading(true);

    try {
      const response = await login(email, password, remember);

      const roles =
        response.data.user.roles?.map((role) =>
          role.name.toLowerCase(),
        ) ?? [];

      const isAdmin =
        roles.includes("owner") ||
        roles.includes("administrator");

      router.push(isAdmin ? "/admin" : "/dashboard");
      router.refresh();
    } catch (err) {
      setError(
        err instanceof Error
          ? err.message
          : "Unable to sign in. Please try again.",
      );
    } finally {
      setLoading(false);
    }
  }

  return (
    <main className="min-h-screen bg-[var(--cx-bg)] text-[var(--cx-text)]">

      <section className="mx-auto flex min-h-[calc(100vh-56px)] max-w-7xl items-center justify-center px-5 py-16 sm:px-8 lg:px-10">
        <div className="w-full max-w-md">
          <div className="mb-8 text-center">
            <Link
              href="/"
              aria-label="CrypticX Lab home"
              className="cx-raised-sm mx-auto flex h-20 w-20 items-center justify-center rounded-2xl p-2"
            >
              <img
                src="/brand/crypticx2.png"
                alt="CrypticX Lab"
                width={72}
                height={72}
                className="h-full w-full object-contain"
              />
            </Link>

            <h1 className="mt-7 text-3xl font-semibold tracking-tight">
              Welcome back
            </h1>

            <p className="mt-3 text-sm leading-6 text-[var(--cx-muted)]">
              Sign in to access your CrypticX Lab workspace.
            </p>
          </div>

          <div className="cx-card rounded-[30px] p-7 sm:p-9">
            <form onSubmit={handleSubmit}>
              <div>
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
                <div className="mb-2 flex items-center justify-between gap-4">
                  <label
                    htmlFor="password"
                    className="block text-sm font-semibold"
                  >
                    Password
                  </label>

                  <Link
                    href="/forgot-password"
                    className="text-sm font-semibold text-zinc-300 transition-colors hover:text-red-400 focus-visible:outline-none focus-visible:text-red-400"
                  >
                    Forgot password?
                  </Link>
                </div>

                <div className="relative">
                  <input
                    id="password"
                    name="password"
                    type={showPassword ? "text" : "password"}
                    autoComplete="current-password"
                    placeholder="Enter your password"
                    className="cx-input w-full pr-16"
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
              </div>

              <label className="mt-5 flex cursor-pointer items-center gap-3 text-sm text-[var(--cx-muted)]">
                <input
                  type="checkbox"
                  name="remember"
                  className="h-4 w-4 rounded border-[var(--cx-border)]"
                />
                Keep me signed in
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
                {loading ? "Signing in..." : "Sign In"}
              </button>
            </form>

            <p className="mt-7 text-center text-sm text-[var(--cx-muted)]">
              Don&apos;t have an account?{" "}
              <Link
                href="/register"
                className="font-semibold text-[var(--cx-text)]"
              >
                Create one
              </Link>
            </p>
          </div>

          <div className="cx-inset-sm mt-6 rounded-2xl p-4 text-center">
            <p className="text-xs leading-5 text-[var(--cx-muted)]">
              CrypticX authentication uses secure credential protection
              and authenticated API sessions.
            </p>
          </div>

          <p className="mt-6 text-center text-xs text-[var(--cx-subtle)]">
            By continuing, you agree to use CrypticX only for authorized
            security research and assessment.
          </p>
        </div>
      </section>
    </main>
  );
}
