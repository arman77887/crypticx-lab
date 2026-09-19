"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import {
  AccountEntitlements,
  enrollPasskey,
  getAccountEntitlements,
  getCurrentUser,
  getPasskeyStatus,
  getStoredToken,
  getUserDevices,
  removeUserDevice,
  type PasskeyStatusResponse,
  type UserDevice,
} from "@/lib/api";

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
  const [entitlements, setEntitlements] =
    useState<AccountEntitlements | null>(null);
  const [planError, setPlanError] = useState("");
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [passkeyLoading, setPasskeyLoading] = useState(false);
  const [passkeyMessage, setPasskeyMessage] = useState("");
  const [passkeyError, setPasskeyError] = useState("");
  const [passkeyStatus, setPasskeyStatus] =
    useState<PasskeyStatusResponse["data"] | null>(null);

  const [devices, setDevices] = useState<UserDevice[]>([]);
  const [devicesLoading, setDevicesLoading] = useState(false);
  const [deviceError, setDeviceError] = useState("");
  const [deviceMessage, setDeviceMessage] = useState("");
  const [removingDeviceId, setRemovingDeviceId] =
    useState<string | null>(null);

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

        try {
          const entitlementResponse =
            await getAccountEntitlements();

          if (mounted) {
            setEntitlements(entitlementResponse.data);
          }
        } catch (entitlementError) {
          if (mounted) {
            setPlanError(
              entitlementError instanceof Error
                ? entitlementError.message
                : "Unable to load plan information.",
            );
          }
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
        } else {
          if (mounted) {
            setDevicesLoading(true);
          }

          try {
            const deviceResponse = await getUserDevices();

            if (mounted) {
              setDevices(deviceResponse.data.devices);
            }
          } catch (deviceLoadError) {
            if (mounted) {
              setDeviceError(
                deviceLoadError instanceof Error
                  ? deviceLoadError.message
                  : "Unable to load authorized devices.",
              );
            }
          } finally {
            if (mounted) {
              setDevicesLoading(false);
            }
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

  async function handleRemoveDevice(device: UserDevice) {
    const confirmed = window.confirm(
      device.current
        ? "Remove this current device? You will be signed out and will need email verification to authorize it again."
        : `Remove authorization for ${device.browser || "this browser"} on ${device.platform || "this device"}?`,
    );

    if (!confirmed) {
      return;
    }

    setDeviceError("");
    setDeviceMessage("");
    setRemovingDeviceId(device.id);

    try {
      const response = await removeUserDevice(device.id);

      if (response.data.current_device_removed) {
        window.location.href = "/login";
        return;
      }

      setDevices((current) =>
        current.filter((item) => item.id !== device.id),
      );

      setDeviceMessage(
        "Device authorization removed successfully.",
      );
    } catch (err) {
      setDeviceError(
        err instanceof Error
          ? err.message
          : "Unable to remove this device.",
      );
    } finally {
      setRemovingDeviceId(null);
    }
  }

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

            <div className="mt-8 cx-inset-sm rounded-2xl p-5 sm:p-6">
              <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                  <div className="text-xs font-bold uppercase tracking-[0.16em] text-[var(--cx-subtle)]">
                    Current Plan
                  </div>

                  <h3 className="mt-3 text-xl font-bold">
                    {entitlements?.plan.name ?? "Loading..."}
                  </h3>

                  <p className="mt-2 text-sm leading-6 text-[var(--cx-muted)]">
                    Your current usage and account limits.
                  </p>
                </div>

                <Link
                  href="/pricing"
                  className="cx-button cx-button-secondary shrink-0"
                >
                  View Plans
                </Link>
              </div>

              {planError && (
                <div className="mt-5 rounded-2xl border border-red-500/30 p-4 text-sm text-red-300">
                  {planError}
                </div>
              )}

              {entitlements && (
                <div className="mt-6 grid gap-4 sm:grid-cols-2">
                  {[
                    [
                      "Assessments this month",
                      entitlements.usage.assessments_monthly,
                      entitlements.limits.assessments_monthly,
                    ],
                    [
                      "Targets",
                      entitlements.usage.targets_total,
                      entitlements.limits.targets_total,
                    ],
                    [
                      "Reports this month",
                      entitlements.usage.reports_monthly,
                      entitlements.limits.reports_monthly,
                    ],
                    [
                      "Monitoring policies",
                      entitlements.usage.monitoring_policies,
                      entitlements.limits.monitoring_policies,
                    ],
                  ].map(([label, used, limit]) => {
                    const usedNumber = Number(used);
                    const limitNumber =
                      limit === null ? null : Number(limit);

                    const percentage =
                      limitNumber === null || limitNumber <= 0
                        ? 0
                        : Math.min(
                            100,
                            (usedNumber / limitNumber) * 100,
                          );

                    return (
                      <div
                        key={String(label)}
                        className="rounded-2xl border border-[var(--cx-border)] p-4"
                      >
                        <div className="flex items-center justify-between gap-3">
                          <span className="text-xs font-semibold text-[var(--cx-muted)]">
                            {label}
                          </span>

                          <span className="text-sm font-bold">
                            {usedNumber} /{" "}
                            {limitNumber === null
                              ? "Unlimited"
                              : limitNumber}
                          </span>
                        </div>

                        {limitNumber !== null && limitNumber > 0 && (
                          <div className="mt-3 h-2 overflow-hidden rounded-full bg-black/20">
                            <div
                              className="h-full rounded-full bg-[var(--cx-text)] transition-all"
                              style={{
                                width: `${percentage}%`,
                              }}
                            />
                          </div>
                        )}
                      </div>
                    );
                  })}
                </div>
              )}
            </div>

            {!isAdmin && (
              <div
                id="device-security"
                className="mt-8 scroll-mt-24 cx-inset-sm rounded-2xl p-5 sm:p-6"
              >
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                  <div>
                    <div className="text-xs font-bold uppercase tracking-[0.16em] text-[var(--cx-subtle)]">
                      Device Security
                    </div>

                    <h3 className="mt-3 text-lg font-bold">
                      Authorized Devices
                    </h3>

                    <p className="mt-2 max-w-2xl text-sm leading-6 text-[var(--cx-muted)]">
                      Only authorized browsers can sign in to your account.
                      A new browser must be verified using the security code
                      sent to your registered email.
                    </p>
                  </div>

                  <div className="shrink-0 rounded-xl border border-emerald-500/25 px-3 py-2 text-xs font-bold text-emerald-300">
                    DEVICE PROTECTION ACTIVE
                  </div>
                </div>

                {deviceMessage && (
                  <div className="mt-5 rounded-2xl border border-emerald-500/30 p-4 text-sm text-emerald-300">
                    {deviceMessage}
                  </div>
                )}

                {deviceError && (
                  <div className="mt-5 rounded-2xl border border-red-500/30 p-4 text-sm text-red-300">
                    {deviceError}
                  </div>
                )}

                {devicesLoading ? (
                  <div className="mt-6 rounded-2xl border border-[var(--cx-border)] p-5 text-sm text-[var(--cx-muted)]">
                    Loading authorized devices...
                  </div>
                ) : devices.length === 0 ? (
                  <div className="mt-6 rounded-2xl border border-[var(--cx-border)] p-5">
                    <div className="font-semibold">
                      No authorized devices found
                    </div>

                    <p className="mt-2 text-sm leading-6 text-[var(--cx-muted)]">
                      Sign in through the device verification flow to
                      authorize this browser.
                    </p>
                  </div>
                ) : (
                  <div className="mt-6 space-y-4">
                    {devices.map((device) => (
                      <div
                        key={device.id}
                        className="rounded-2xl border border-[var(--cx-border)] p-5"
                      >
                        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                          <div className="min-w-0">
                            <div className="flex flex-wrap items-center gap-2">
                              <div className="font-bold">
                                {device.browser || "Unknown browser"}
                              </div>

                              {device.current && (
                                <span className="rounded-full border border-emerald-500/30 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider text-emerald-300">
                                  Current Device
                                </span>
                              )}
                            </div>

                            <div className="mt-2 text-sm text-[var(--cx-muted)]">
                              {device.platform || "Unknown platform"}
                              {" · "}
                              {device.device_type || "Device"}
                            </div>

                            <div className="mt-3 grid gap-2 text-xs text-[var(--cx-subtle)] sm:grid-cols-2">
                              <div>
                                Registered:{" "}
                                {device.registered_at
                                  ? new Date(
                                      device.registered_at,
                                    ).toLocaleString()
                                  : "Unknown"}
                              </div>

                              <div>
                                Last active:{" "}
                                {device.last_seen_at
                                  ? new Date(
                                      device.last_seen_at,
                                    ).toLocaleString()
                                  : "Never"}
                              </div>
                            </div>
                          </div>

                          <button
                            type="button"
                            onClick={() =>
                              void handleRemoveDevice(device)
                            }
                            disabled={removingDeviceId === device.id}
                            className="cx-button cx-button-secondary shrink-0 disabled:cursor-not-allowed disabled:opacity-60"
                          >
                            {removingDeviceId === device.id
                              ? "Removing..."
                              : device.current
                                ? "Remove This Device"
                                : "Remove Device"}
                          </button>
                        </div>
                      </div>
                    ))}
                  </div>
                )}

                <p className="mt-5 text-xs leading-5 text-[var(--cx-subtle)]">
                  Clearing browser storage may cause this browser to be
                  treated as a new device and require email verification
                  again.
                </p>
              </div>
            )}

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
