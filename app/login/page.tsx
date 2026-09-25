"use client";

import Link from "next/link";
import {
  FormEvent,
  useEffect,
  useState,
} from "react";
import { useRouter } from "next/navigation";
import { trackEvent } from "@/lib/analytics";
import {
  login,
  verifyUserDevice,
} from "@/lib/api";

export default function LoginPage() {
  const router = useRouter();

  const [showPassword, setShowPassword] = useState(false);
  const [loading, setLoading] = useState(false);
  const [verifying, setVerifying] = useState(false);
  const [error, setError] = useState("");

  const [otpMode, setOtpMode] = useState(false);
  const [otp, setOtp] = useState("");
  const [requestToken, setRequestToken] = useState("");
  const [maskedEmail, setMaskedEmail] = useState("");
  const [countdown, setCountdown] = useState(60);

  const [loginEmail, setLoginEmail] = useState("");
  const [loginPassword, setLoginPassword] = useState("");
  const [rememberLogin, setRememberLogin] = useState(false);

  useEffect(() => {
    if (!otpMode) {
      return;
    }

    const timer = window.setInterval(() => {
      setCountdown((value) => {
        if (value <= 1) {
          window.clearInterval(timer);
          return 0;
        }

        return value - 1;
      });
    }, 1000);

    return () => window.clearInterval(timer);
  }, [otpMode]);

  function redirectAuthenticatedUser(
    roles: Array<{ name: string }> | undefined,
  ) {
    const roleNames =
      roles?.map((role) => role.name.toLowerCase()) ?? [];

    const isAdmin =
      roleNames.includes("owner") ||
      roleNames.includes("administrator");

    router.push(isAdmin ? "/admin" : "/dashboard");
    router.refresh();
  }

  async function performLogin(
    email: string,
    password: string,
    remember: boolean,
  ) {
    const response = await login(email, password, remember);

    if ("verification_required" in response) {
      setRequestToken(response.data.request_token);
      setMaskedEmail(response.data.email);
      setCountdown(response.data.expires_in || 60);
      setOtp("");
      setOtpMode(true);
      return;
    }

    trackEvent("login", {
      method: "email",
    });
    redirectAuthenticatedUser(response.data.user.roles);
  }

  async function handleSubmit(
    event: FormEvent<HTMLFormElement>,
  ) {
    event.preventDefault();

    const form = new FormData(event.currentTarget);
    const email = String(form.get("email") ?? "").trim();
    const password = String(form.get("password") ?? "");
    const remember = form.get("remember") === "on";

    setError("");
    setLoading(true);

    setLoginEmail(email);
    setLoginPassword(password);
    setRememberLogin(remember);

    try {
      await performLogin(email, password, remember);
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

  async function handleVerify(
    event: FormEvent<HTMLFormElement>,
  ) {
    event.preventDefault();

    if (!/^\d{6}$/.test(otp)) {
      setError("Enter the 6-digit verification code.");
      return;
    }

    setError("");
    setVerifying(true);

    try {
      const response = await verifyUserDevice(
        requestToken,
        otp,
        rememberLogin,
      );

      trackEvent("login", {
        method: "email_otp",
      });
      redirectAuthenticatedUser(response.data.user.roles);
    } catch (err) {
      setError(
        err instanceof Error
          ? err.message
          : "Unable to verify this device.",
      );
    } finally {
      setVerifying(false);
    }
  }

  async function handleResend() {
    if (countdown > 0 || loading) {
      return;
    }

    setError("");
    setLoading(true);

    try {
      await performLogin(
        loginEmail,
        loginPassword,
        rememberLogin,
      );
    } catch (err) {
      setError(
        err instanceof Error
          ? err.message
          : "Unable to send a new verification code.",
      );
    } finally {
      setLoading(false);
    }
  }

  function cancelVerification() {
    setOtpMode(false);
    setOtp("");
    setRequestToken("");
    setMaskedEmail("");
    setCountdown(60);
    setError("");
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
              {otpMode ? "Verify this device" : "Welcome back"}
            </h1>

            <p className="mt-3 text-sm leading-6 text-[var(--cx-muted)]">
              {otpMode
                ? "This browser has not been authorized for your account yet."
                : "Sign in to access your CrypticX Lab workspace."}
            </p>
          </div>

          <div className="cx-card rounded-[30px] p-7 sm:p-9">
            {otpMode ? (
              <form onSubmit={handleVerify}>
                <div className="text-center">
                  <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl border border-emerald-500/20 bg-emerald-500/[0.06] text-2xl">
                    ✉
                  </div>

                  <h2 className="mt-4 text-xl font-bold">
                    Enter your verification code
                  </h2>

                  <p className="mt-2 text-sm leading-6 text-[var(--cx-muted)]">
                    A 6-digit security code was sent to
                  </p>

                  <div className="mx-auto mt-3 inline-flex rounded-xl border border-white/10 bg-black/20 px-4 py-2 font-mono text-sm font-semibold tracking-wide text-[var(--cx-text)]">
                    {maskedEmail || "your registered email"}
                  </div>

                  <p className="mt-3 text-xs leading-5 text-[var(--cx-muted)]">
                    Check your Inbox and Spam/Junk folder.
                  </p>
                </div>

                <div className="mt-7">
                  <label
                    htmlFor="device-code"
                    className="mb-3 block text-center text-sm font-semibold"
                  >
                    6-digit verification code
                  </label>

                  <input
                    id="device-code"
                    type="text"
                    inputMode="numeric"
                    autoComplete="one-time-code"
                    maxLength={6}
                    value={otp}
                    onChange={(event) =>
                      setOtp(
                        event.target.value
                          .replace(/\D/g, "")
                          .slice(0, 6),
                      )
                    }
                    placeholder="000000"
                    aria-describedby="device-code-expiry"
                    className="cx-input w-full py-4 text-center font-mono text-3xl font-bold tracking-[0.45em]"
                    autoFocus
                    required
                  />

                  <div className="mt-2 text-center text-xs text-[var(--cx-muted)]">
                    Type the code from your email in the box above.
                  </div>
                </div>

                <div
                  id="device-code-expiry"
                  className="mt-6 rounded-2xl border border-white/[0.07] bg-black/20 p-4"
                >
                  <div className="flex items-center justify-between gap-4">
                    <span className="text-xs font-semibold uppercase tracking-wider text-[var(--cx-muted)]">
                      {countdown > 0
                        ? "Code expires in"
                        : "Code expired"}
                    </span>

                    <span
                      className={
                        countdown > 0
                          ? "font-mono text-xl font-bold tabular-nums text-[var(--cx-text)]"
                          : "text-sm font-bold text-red-400"
                      }
                    >
                      {countdown > 0
                        ? `00:${String(countdown).padStart(2, "0")}`
                        : "EXPIRED"}
                    </span>
                  </div>

                  <div className="mt-3 h-1.5 overflow-hidden rounded-full bg-white/[0.06]">
                    <div
                      className="h-full rounded-full bg-emerald-500 transition-[width] duration-1000"
                      style={{
                        width: `${Math.max(
                          0,
                          Math.min(100, (countdown / 60) * 100),
                        )}%`,
                      }}
                    />
                  </div>
                </div>

                {error && (
                  <div
                    role="alert"
                    className="mt-5 rounded-2xl border border-red-500/20 bg-red-500/[0.06] p-4 text-sm text-red-300"
                  >
                    {error}
                  </div>
                )}

                <button
                  type="submit"
                  disabled={
                    verifying ||
                    otp.length !== 6 ||
                    countdown <= 0
                  }
                  className="cx-button cx-button-primary mt-6 w-full py-3.5 disabled:cursor-not-allowed disabled:opacity-50"
                >
                  {verifying
                    ? "Verifying device..."
                    : "Verify & Sign In"}
                </button>

                <div className="mt-4 text-center">
                  {countdown > 0 ? (
                    <p className="text-xs text-[var(--cx-muted)]">
                      You can request another code after the timer expires.
                    </p>
                  ) : (
                    <button
                      type="button"
                      onClick={handleResend}
                      disabled={loading}
                      className="cx-button cx-button-secondary w-full py-3 text-sm font-semibold disabled:opacity-50"
                    >
                      {loading
                        ? "Sending new code..."
                        : "Resend verification code"}
                    </button>
                  )}
                </div>

                <button
                  type="button"
                  onClick={cancelVerification}
                  disabled={verifying}
                  className="mt-5 w-full text-center text-sm font-semibold text-[var(--cx-muted)] transition-colors hover:text-[var(--cx-text)] disabled:opacity-50"
                >
                  ← Use another account
                </button>
              </form>
            ) : (
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
                    defaultValue={loginEmail}
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
                      onClick={() =>
                        setShowPassword((value) => !value)
                      }
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
                    defaultChecked={rememberLogin}
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
            )}

            {!otpMode && (
              <p className="mt-7 text-center text-sm text-[var(--cx-muted)]">
                Don&apos;t have an account?{" "}
                <Link
                  href="/register"
                  className="font-semibold text-[var(--cx-text)]"
                >
                  Create one
                </Link>
              </p>
            )}
          </div>

          <div className="cx-inset-sm mt-6 rounded-2xl p-4 text-center">
            <p className="text-xs leading-5 text-[var(--cx-muted)]">
              {otpMode
                ? "Successful verification will authorize this browser for future sign-ins."
                : "CrypticX authentication uses secure credential protection and authenticated API sessions."}
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
