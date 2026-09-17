"use client";

import Link from "next/link";
import { FormEvent, useState } from "react";
import {
  requestPasswordReset,
  resetPassword,
  verifyPasswordResetCode,
} from "@/lib/api";

type Step = "email" | "verify" | "password" | "success";

export default function ForgotPasswordPage() {
  const [step, setStep] = useState<Step>("email");

  const [email, setEmail] = useState("");
  const [code, setCode] = useState("");
  const [resetToken, setResetToken] = useState("");

  const [password, setPassword] = useState("");
  const [passwordConfirmation, setPasswordConfirmation] =
    useState("");

  const [showPassword, setShowPassword] = useState(false);
  const [showConfirmation, setShowConfirmation] =
    useState(false);

  const [loading, setLoading] = useState(false);
  const [resending, setResending] = useState(false);

  const [message, setMessage] = useState("");
  const [error, setError] = useState("");

  function clearFeedback() {
    setMessage("");
    setError("");
  }

  async function handleEmailSubmit(
    event: FormEvent<HTMLFormElement>,
  ) {
    event.preventDefault();
    clearFeedback();

    setLoading(true);

    try {
      const response = await requestPasswordReset(email.trim());

      setEmail(email.trim().toLowerCase());
      setMessage(response.message);
      setStep("verify");
    } catch (err) {
      setError(
        err instanceof Error
          ? err.message
          : "Unable to send the verification code.",
      );
    } finally {
      setLoading(false);
    }
  }

  async function handleVerifySubmit(
    event: FormEvent<HTMLFormElement>,
  ) {
    event.preventDefault();
    clearFeedback();

    if (!/^\d{6}$/.test(code)) {
      setError("Enter the 6-digit verification code.");
      return;
    }

    setLoading(true);

    try {
      const response = await verifyPasswordResetCode(
        email,
        code,
      );

      setResetToken(response.data.reset_token);
      setCode("");
      setStep("password");
    } catch (err) {
      setError(
        err instanceof Error
          ? err.message
          : "The verification code is invalid or expired.",
      );
    } finally {
      setLoading(false);
    }
  }

  async function handleResend() {
    clearFeedback();
    setResending(true);

    try {
      const response = await requestPasswordReset(email);
      setCode("");
      setMessage(
        `${response.message} Check your Spam or Junk folder if it is not in your inbox.`,
      );
    } catch (err) {
      setError(
        err instanceof Error
          ? err.message
          : "Unable to resend the verification code.",
      );
    } finally {
      setResending(false);
    }
  }

  async function handlePasswordSubmit(
    event: FormEvent<HTMLFormElement>,
  ) {
    event.preventDefault();
    clearFeedback();

    if (password.length < 12) {
      setError("Password must contain at least 12 characters.");
      return;
    }

    if (password !== passwordConfirmation) {
      setError("Password confirmation does not match.");
      return;
    }

    setLoading(true);

    try {
      const response = await resetPassword(
        email,
        resetToken,
        password,
        passwordConfirmation,
      );

      setPassword("");
      setPasswordConfirmation("");
      setResetToken("");
      setMessage(response.message);
      setStep("success");
    } catch (err) {
      setError(
        err instanceof Error
          ? err.message
          : "Unable to reset your password.",
      );
    } finally {
      setLoading(false);
    }
  }

  return (
    <main className="min-h-screen bg-[var(--cx-bg)] text-[var(--cx-text)]">
      <section className="mx-auto flex min-h-[calc(100vh-56px)] max-w-7xl items-center justify-center px-5 py-12 sm:px-8 lg:px-10">
        <div className="w-full max-w-md">
          <div className="mb-8 text-center">
            <div className="cx-raised-sm mx-auto flex h-20 w-20 items-center justify-center rounded-2xl p-2">
              <img
                src="/brand/crypticx2.png"
                alt="CrypticX Lab"
                width={72}
                height={72}
                className="h-full w-full object-contain"
              />
            </div>

            <h1 className="mt-7 text-3xl font-semibold tracking-tight">
              {step === "email" && "Reset your password"}
              {step === "verify" && "Check your email"}
              {step === "password" && "Create a new password"}
              {step === "success" && "Password updated"}
            </h1>

            <p className="mt-3 text-sm leading-6 text-[var(--cx-muted)]">
              {step === "email" &&
                "Enter your account email to receive a secure verification code."}

              {step === "verify" &&
                "Enter the 6-digit verification code we sent to your email."}

              {step === "password" &&
                "Choose a new strong password for your CrypticX Lab account."}

              {step === "success" &&
                "Your password has been changed successfully."}
            </p>
          </div>

          <div className="cx-card rounded-[30px] p-7 sm:p-9">
            {error && (
              <div
                role="alert"
                className="mb-6 rounded-2xl border border-red-500/20 bg-red-500/10 p-4 text-sm leading-6 text-red-300"
              >
                {error}
              </div>
            )}

            {message && step !== "verify" && (
              <div
                role="status"
                className="mb-6 rounded-2xl border border-emerald-500/20 bg-emerald-500/10 p-4 text-sm leading-6 text-emerald-300"
              >
                {message}
              </div>
            )}

            {step === "email" && (
              <form onSubmit={handleEmailSubmit}>
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
                  onChange={(event) =>
                    setEmail(event.target.value)
                  }
                  className="cx-input w-full"
                  disabled={loading}
                  required
                />

                <button
                  type="submit"
                  disabled={loading}
                  className="cx-button cx-button-primary mt-7 w-full disabled:cursor-not-allowed disabled:opacity-60"
                >
                  {loading
                    ? "Sending code..."
                    : "Send Verification Code"}
                </button>
              </form>
            )}

            {step === "verify" && (
              <>
                <div className="mb-6 rounded-2xl border border-white/[0.08] bg-white/[0.03] p-4">
                  <p className="text-sm font-semibold">
                    Verification code sent
                  </p>

                  <p className="mt-2 break-all text-xs leading-5 text-[var(--cx-muted)]">
                    Check the inbox for{" "}
                    <span className="font-semibold text-[var(--cx-text)]">
                      {email}
                    </span>
                    .
                  </p>

                  <p className="mt-2 text-xs leading-5 text-[var(--cx-muted)]">
                    If you don&apos;t see the email in your inbox,
                    check your{" "}
                    <strong className="text-[var(--cx-text)]">
                      Spam or Junk folder
                    </strong>
                    .
                  </p>
                </div>

                <form onSubmit={handleVerifySubmit}>
                  <label
                    htmlFor="code"
                    className="mb-2 block text-sm font-semibold"
                  >
                    6-digit verification code
                  </label>

                  <input
                    id="code"
                    name="code"
                    type="text"
                    inputMode="numeric"
                    autoComplete="one-time-code"
                    pattern="[0-9]{6}"
                    maxLength={6}
                    placeholder="000000"
                    value={code}
                    onChange={(event) =>
                      setCode(
                        event.target.value
                          .replace(/\D/g, "")
                          .slice(0, 6),
                      )
                    }
                    className="cx-input w-full text-center text-xl tracking-[0.35em]"
                    disabled={loading}
                    required
                  />

                  <button
                    type="submit"
                    disabled={loading || code.length !== 6}
                    className="cx-button cx-button-primary mt-7 w-full disabled:cursor-not-allowed disabled:opacity-60"
                  >
                    {loading
                      ? "Verifying..."
                      : "Verify Code"}
                  </button>
                </form>

                <div className="mt-5 flex items-center justify-between gap-4">
                  <button
                    type="button"
                    onClick={() => {
                      clearFeedback();
                      setCode("");
                      setStep("email");
                    }}
                    className="text-xs font-semibold text-[var(--cx-muted)] hover:text-[var(--cx-text)]"
                  >
                    Change email
                  </button>

                  <button
                    type="button"
                    onClick={handleResend}
                    disabled={resending || loading}
                    className="text-xs font-semibold text-[var(--cx-text)] disabled:opacity-50"
                  >
                    {resending ? "Sending..." : "Resend code"}
                  </button>
                </div>

                {message && (
                  <p
                    role="status"
                    className="mt-5 text-center text-xs leading-5 text-[var(--cx-muted)]"
                  >
                    {message}
                  </p>
                )}
              </>
            )}

            {step === "password" && (
              <form onSubmit={handlePasswordSubmit}>
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
                    minLength={12}
                    placeholder="Enter your new password"
                    value={password}
                    onChange={(event) =>
                      setPassword(event.target.value)
                    }
                    className="cx-input w-full pr-20"
                    disabled={loading}
                    required
                  />

                  <button
                    type="button"
                    onClick={() =>
                      setShowPassword((current) => !current)
                    }
                    className="absolute right-3 top-1/2 -translate-y-1/2 rounded-lg px-2 py-1 text-xs font-semibold text-[var(--cx-muted)] hover:text-[var(--cx-text)]"
                  >
                    {showPassword ? "Hide" : "Show"}
                  </button>
                </div>

                <p className="mt-2 text-xs text-[var(--cx-subtle)]">
                  Use at least 12 characters.
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
                    type={
                      showConfirmation ? "text" : "password"
                    }
                    autoComplete="new-password"
                    minLength={12}
                    placeholder="Repeat your new password"
                    value={passwordConfirmation}
                    onChange={(event) =>
                      setPasswordConfirmation(event.target.value)
                    }
                    className="cx-input w-full pr-20"
                    disabled={loading}
                    required
                  />

                  <button
                    type="button"
                    onClick={() =>
                      setShowConfirmation(
                        (current) => !current,
                      )
                    }
                    className="absolute right-3 top-1/2 -translate-y-1/2 rounded-lg px-2 py-1 text-xs font-semibold text-[var(--cx-muted)] hover:text-[var(--cx-text)]"
                  >
                    {showConfirmation ? "Hide" : "Show"}
                  </button>
                </div>

                <button
                  type="submit"
                  disabled={loading}
                  className="cx-button cx-button-primary mt-7 w-full disabled:cursor-not-allowed disabled:opacity-60"
                >
                  {loading
                    ? "Updating password..."
                    : "Update Password"}
                </button>
              </form>
            )}

            {step === "success" && (
              <div className="text-center">
                <div className="cx-inset-sm mx-auto flex h-14 w-14 items-center justify-center rounded-2xl text-xl font-bold">
                  ✓
                </div>

                <p className="mt-5 text-sm leading-6 text-[var(--cx-muted)]">
                  Your previous authenticated sessions have been
                  revoked. Sign in again with your new password.
                </p>

                <Link
                  href="/login"
                  className="cx-button cx-button-primary mt-7 flex w-full items-center justify-center"
                >
                  Continue to Sign In
                </Link>
              </div>
            )}

            {step !== "success" && (
              <>
                <div className="cx-divider my-7" />

                <div className="text-center">
                  <Link
                    href="/login"
                    className="text-sm font-semibold text-[var(--cx-text)]"
                  >
                    ← Back to Sign In
                  </Link>
                </div>
              </>
            )}
          </div>

          <p className="mt-6 text-center text-xs leading-5 text-[var(--cx-subtle)]">
            Verification codes and password-reset sessions are
            short-lived and single-use.
          </p>
        </div>
      </section>
    </main>
  );
}
