"use client";

import Link from "next/link";
import { useCallback, useEffect, useMemo, useState } from "react";
import {
  disableMonitoringPolicy,
  getMonitoringNotificationPreferences,
  getMonitoringPolicies,
  getStoredToken,
  MonitoringNotificationEventType,
  MonitoringNotificationPreference,
  MonitoringPolicy,
  MonitoringProfile,
  updateMonitoringNotificationPreferences,
  upsertMonitoringPolicy,
} from "@/lib/api";

const EVENT_OPTIONS: Array<{
  value: MonitoringNotificationEventType;
  label: string;
  description: string;
}> = [
  {
    value: "finding_new",
    label: "New findings",
    description: "A security finding appears for the first time.",
  },
  {
    value: "finding_reappeared",
    label: "Reappeared findings",
    description: "A previously observed finding appears again.",
  },
  {
    value: "finding_no_longer_detected",
    label: "No longer detected",
    description: "A previous finding is absent from the latest assessment.",
  },
  {
    value: "finding_reopened",
    label: "Reopened findings",
    description: "An explicitly resolved finding is detected again.",
  },
  {
    value: "risk_changed",
    label: "Risk changes",
    description: "The target aggregate risk score changes.",
  },
];

function formatDate(value?: string | null) {
  if (!value) return "Not available";

  const date = new Date(value);

  if (Number.isNaN(date.getTime())) return value;

  return date.toLocaleString();
}

function formatInterval(minutes: number) {
  if (minutes < 60) {
    return `Every ${minutes} minute${minutes === 1 ? "" : "s"}`;
  }

  if (minutes % 1440 === 0) {
    const days = minutes / 1440;
    return `Every ${days} day${days === 1 ? "" : "s"}`;
  }

  if (minutes % 60 === 0) {
    const hours = minutes / 60;
    return `Every ${hours} hour${hours === 1 ? "" : "s"}`;
  }

  return `Every ${minutes} minutes`;
}

function formatProfile(profile: string) {
  return profile.charAt(0).toUpperCase() + profile.slice(1);
}

function statusClass(status?: string) {
  switch ((status || "").toLowerCase()) {
    case "completed":
    case "active":
    case "sent":
      return "bg-emerald-500/10 text-emerald-300";

    case "queued":
    case "running":
    case "pending":
      return "bg-amber-500/10 text-amber-300";

    case "failed":
      return "bg-red-500/10 text-red-300";

    default:
      return "bg-white/[0.04] text-[var(--cx-muted)]";
  }
}

export default function MonitoringPage() {
  const [policies, setPolicies] = useState<MonitoringPolicy[]>([]);
  const [preference, setPreference] =
    useState<MonitoringNotificationPreference | null>(null);

  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [message, setMessage] = useState("");
  const [busyTarget, setBusyTarget] = useState<string | null>(null);
  const [savingPreferences, setSavingPreferences] = useState(false);

  const load = useCallback(async () => {
    if (!getStoredToken()) {
      window.location.href = "/login";
      return;
    }

    try {
      setError("");

      const [policyResponse, preferenceResponse] = await Promise.all([
        getMonitoringPolicies(),
        getMonitoringNotificationPreferences(),
      ]);

      setPolicies(policyResponse.data);
      setPreference(preferenceResponse.data);
    } catch (err) {
      setError(
        err instanceof Error
          ? err.message
          : "Unable to load monitoring configuration.",
      );
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  const activePolicies = useMemo(
    () => policies.filter((policy) => policy.enabled).length,
    [policies],
  );

  const nextRun = useMemo(() => {
    const dates = policies
      .filter((policy) => policy.enabled && policy.next_run_at)
      .map((policy) => new Date(policy.next_run_at as string))
      .filter((date) => !Number.isNaN(date.getTime()))
      .sort((a, b) => a.getTime() - b.getTime());

    return dates[0] ?? null;
  }, [policies]);

  async function togglePolicy(policy: MonitoringPolicy) {
    try {
      setBusyTarget(policy.target_id);
      setError("");
      setMessage("");

      if (policy.enabled) {
        await disableMonitoringPolicy(policy.target_id);
        setMessage("Monitoring disabled.");
      } else {
        await upsertMonitoringPolicy(policy.target_id, {
          enabled: true,
          profile: policy.profile,
          interval_minutes: policy.interval_minutes,
          configuration: {},
        });
        setMessage("Monitoring enabled.");
      }

      await load();
    } catch (err) {
      setError(
        err instanceof Error
          ? err.message
          : "Unable to update monitoring policy.",
      );
    } finally {
      setBusyTarget(null);
    }
  }

  async function updatePolicyProfile(
    policy: MonitoringPolicy,
    profile: MonitoringProfile,
  ) {
    try {
      setBusyTarget(policy.target_id);
      setError("");
      setMessage("");

      await upsertMonitoringPolicy(policy.target_id, {
        enabled: policy.enabled,
        profile,
        interval_minutes: policy.interval_minutes,
        configuration: {},
      });

      setMessage("Monitoring profile updated.");
      await load();
    } catch (err) {
      setError(
        err instanceof Error
          ? err.message
          : "Unable to update monitoring profile.",
      );
    } finally {
      setBusyTarget(null);
    }
  }

  async function updatePolicyInterval(
    policy: MonitoringPolicy,
    intervalMinutes: number,
  ) {
    try {
      setBusyTarget(policy.target_id);
      setError("");
      setMessage("");

      await upsertMonitoringPolicy(policy.target_id, {
        enabled: policy.enabled,
        profile: policy.profile,
        interval_minutes: intervalMinutes,
        configuration: {},
      });

      setMessage("Monitoring interval updated.");
      await load();
    } catch (err) {
      setError(
        err instanceof Error
          ? err.message
          : "Unable to update monitoring interval.",
      );
    } finally {
      setBusyTarget(null);
    }
  }

  function toggleEvent(eventType: MonitoringNotificationEventType) {
    if (!preference) return;

    const enabled = preference.event_types.includes(eventType);

    setPreference({
      ...preference,
      event_types: enabled
        ? preference.event_types.filter((item) => item !== eventType)
        : [...preference.event_types, eventType],
    });
  }

  async function savePreferences() {
    if (!preference) return;

    try {
      setSavingPreferences(true);
      setError("");
      setMessage("");

      const response = await updateMonitoringNotificationPreferences({
        email_enabled: preference.email_enabled,
        event_types: preference.event_types,
        minimum_risk_delta: preference.minimum_risk_delta,
      });

      setPreference(response.data);
      setMessage("Notification preferences saved.");
    } catch (err) {
      setError(
        err instanceof Error
          ? err.message
          : "Unable to save notification preferences.",
      );
    } finally {
      setSavingPreferences(false);
    }
  }

  if (loading) {
    return (
      <main className="min-h-screen bg-[var(--cx-bg)] text-[var(--cx-text)]">
        <div className="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
          <div className="cx-card rounded-[28px] p-8">
            <p className="text-sm text-[var(--cx-muted)]">
              Loading monitoring intelligence...
            </p>
          </div>
        </div>
      </main>
    );
  }

  return (
    <main className="min-h-screen bg-[var(--cx-bg)] text-[var(--cx-text)]">
      <section className="border-b border-[var(--cx-border)]">
        <div className="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
          <div className="flex flex-col gap-6 md:flex-row md:items-end md:justify-between">
            <div>
              <p className="text-xs font-semibold uppercase tracking-[0.2em] text-[var(--cx-subtle)]">
                Continuous Security
              </p>

              <h1 className="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">
                Monitoring
              </h1>

              <p className="mt-3 max-w-2xl text-sm leading-6 text-[var(--cx-muted)]">
                Schedule recurring assessments for authorized targets and
                receive alerts when meaningful security changes are detected.
              </p>
            </div>

            <div className="flex flex-wrap gap-3">
              <Link href="/dashboard" className="cx-button cx-button-secondary">
                Dashboard
              </Link>

              <Link href="/scanner" className="cx-button cx-button-primary">
                New Assessment
              </Link>
            </div>
          </div>
        </div>
      </section>

      <div className="mx-auto max-w-7xl space-y-8 px-4 py-8 sm:px-6 lg:px-8">
        {error ? (
          <div className="rounded-2xl border border-red-500/20 bg-red-500/10 p-4 text-sm text-red-300">
            {error}
          </div>
        ) : null}

        {message ? (
          <div className="rounded-2xl border border-emerald-500/20 bg-emerald-500/10 p-4 text-sm text-emerald-300">
            {message}
          </div>
        ) : null}

        <section className="grid gap-4 sm:grid-cols-3">
          <div className="cx-card rounded-[24px] p-6">
            <p className="text-xs font-semibold uppercase tracking-[0.16em] text-[var(--cx-muted)]">
              Policies
            </p>
            <p className="mt-3 text-3xl font-bold">{policies.length}</p>
            <p className="mt-2 text-xs text-[var(--cx-muted)]">
              Configured monitoring targets
            </p>
          </div>

          <div className="cx-card rounded-[24px] p-6">
            <p className="text-xs font-semibold uppercase tracking-[0.16em] text-[var(--cx-muted)]">
              Active
            </p>
            <p className="mt-3 text-3xl font-bold">{activePolicies}</p>
            <p className="mt-2 text-xs text-[var(--cx-muted)]">
              Autonomous policies enabled
            </p>
          </div>

          <div className="cx-card rounded-[24px] p-6">
            <p className="text-xs font-semibold uppercase tracking-[0.16em] text-[var(--cx-muted)]">
              Next Run
            </p>
            <p className="mt-3 text-sm font-semibold">
              {nextRun ? nextRun.toLocaleString() : "None scheduled"}
            </p>
            <p className="mt-2 text-xs text-[var(--cx-muted)]">
              Earliest scheduled assessment
            </p>
          </div>
        </section>

        <section>
          <div className="mb-5">
            <p className="text-xs font-semibold uppercase tracking-[0.18em] text-[var(--cx-subtle)]">
              Monitor Policies
            </p>
            <h2 className="mt-2 text-2xl font-bold">Authorized Targets</h2>
          </div>

          {policies.length === 0 ? (
            <div className="cx-card rounded-[28px] p-8">
              <h3 className="text-lg font-semibold">
                No monitoring policies configured
              </h3>
              <p className="mt-2 text-sm text-[var(--cx-muted)]">
                Monitoring can only run against targets that you own and have
                explicitly authorized.
              </p>
              <Link
                href="/scanner"
                className="cx-button cx-button-primary mt-6"
              >
                Open Scanner
              </Link>
            </div>
          ) : (
            <div className="space-y-5">
              {policies.map((policy) => {
                const target = policy.target;
                const assessment = policy.last_assessment;
                const busy = busyTarget === policy.target_id;

                return (
                  <article
                    key={policy.id}
                    className="cx-card rounded-[28px] p-6 sm:p-7"
                  >
                    <div className="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                      <div>
                        <div className="flex flex-wrap items-center gap-3">
                          <h3 className="text-lg font-bold">
                            {target?.name || target?.hostname || policy.target_id}
                          </h3>

                          <span
                            className={`rounded-full px-3 py-1 text-xs font-semibold ${
                              policy.enabled
                                ? "bg-emerald-500/10 text-emerald-300"
                                : "bg-white/[0.04] text-[var(--cx-muted)]"
                            }`}
                          >
                            {policy.enabled ? "Monitoring Active" : "Disabled"}
                          </span>
                        </div>

                        <p className="mt-2 break-all text-sm text-[var(--cx-muted)]">
                          {target?.url || "Target details unavailable"}
                        </p>
                      </div>

                      <button
                        type="button"
                        disabled={busy}
                        onClick={() => void togglePolicy(policy)}
                        className={
                          policy.enabled
                            ? "cx-button cx-button-secondary"
                            : "cx-button cx-button-primary"
                        }
                      >
                        {busy
                          ? "Updating..."
                          : policy.enabled
                            ? "Disable Monitoring"
                            : "Enable Monitoring"}
                      </button>
                    </div>

                    <div className="cx-divider my-6" />

                    <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                      <div>
                        <p className="text-xs uppercase tracking-[0.14em] text-[var(--cx-muted)]">
                          Profile
                        </p>
                        <select
                          value={policy.profile}
                          disabled={busy}
                          onChange={(event) =>
                            void updatePolicyProfile(
                              policy,
                              event.target.value as MonitoringProfile,
                            )
                          }
                          className="cx-input mt-2 w-full"
                        >
                          <option value="discovery">Discovery</option>
                          <option value="standard">Standard</option>
                          <option value="deep">Deep</option>
                        </select>
                      </div>

                      <div>
                        <p className="text-xs uppercase tracking-[0.14em] text-[var(--cx-muted)]">
                          Interval
                        </p>
                        <select
                          value={policy.interval_minutes}
                          disabled={busy}
                          onChange={(event) =>
                            void updatePolicyInterval(
                              policy,
                              Number(event.target.value),
                            )
                          }
                          className="cx-input mt-2 w-full"
                        >
                          <option value={15}>Every 15 minutes</option>
                          <option value={30}>Every 30 minutes</option>
                          <option value={60}>Every hour</option>
                          <option value={360}>Every 6 hours</option>
                          <option value={720}>Every 12 hours</option>
                          <option value={1440}>Every day</option>
                          <option value={10080}>Every 7 days</option>
                        </select>
                      </div>

                      <div>
                        <p className="text-xs uppercase tracking-[0.14em] text-[var(--cx-muted)]">
                          Next Run
                        </p>
                        <p className="mt-3 text-sm font-semibold">
                          {policy.enabled
                            ? formatDate(policy.next_run_at)
                            : "Disabled"}
                        </p>
                        <p className="mt-1 text-xs text-[var(--cx-muted)]">
                          {formatInterval(policy.interval_minutes)}
                        </p>
                      </div>

                      <div>
                        <p className="text-xs uppercase tracking-[0.14em] text-[var(--cx-muted)]">
                          Last Assessment
                        </p>

                        {assessment ? (
                          <>
                            <span
                              className={`mt-2 inline-flex rounded-full px-3 py-1 text-xs font-semibold ${statusClass(
                                assessment.status,
                              )}`}
                            >
                              {assessment.status}
                            </span>
                            <p className="mt-2 text-xs text-[var(--cx-muted)]">
                              {formatDate(
                                assessment.completed_at ||
                                  assessment.started_at ||
                                  assessment.queued_at,
                              )}
                            </p>
                          </>
                        ) : (
                          <p className="mt-3 text-sm text-[var(--cx-muted)]">
                            No scheduled run yet
                          </p>
                        )}
                      </div>
                    </div>

                    <div className="mt-5 text-xs text-[var(--cx-muted)]">
                      Profile: {formatProfile(policy.profile)}
                      {" · "}
                      Target authorization:{" "}
                      {target?.authorization_confirmed
                        ? "Confirmed"
                        : "Unavailable"}
                    </div>
                  </article>
                );
              })}
            </div>
          )}
        </section>

        {preference ? (
          <section className="cx-card rounded-[30px] p-6 sm:p-8">
            <div className="flex flex-col gap-5 md:flex-row md:items-start md:justify-between">
              <div>
                <p className="text-xs font-semibold uppercase tracking-[0.18em] text-[var(--cx-subtle)]">
                  Notifications
                </p>
                <h2 className="mt-2 text-2xl font-bold">Email Alerts</h2>
                <p className="mt-2 max-w-2xl text-sm leading-6 text-[var(--cx-muted)]">
                  Alerts are generated from persisted monitoring change events.
                  A missing finding means no longer detected, not automatically
                  resolved.
                </p>
              </div>

              <label className="flex cursor-pointer items-center gap-3">
                <input
                  type="checkbox"
                  checked={preference.email_enabled}
                  onChange={(event) =>
                    setPreference({
                      ...preference,
                      email_enabled: event.target.checked,
                    })
                  }
                  className="h-4 w-4"
                />
                <span className="text-sm font-semibold">
                  Email notifications
                </span>
              </label>
            </div>

            <div className="cx-divider my-7" />

            <div className="grid gap-4 md:grid-cols-2">
              {EVENT_OPTIONS.map((option) => {
                const checked = preference.event_types.includes(option.value);

                return (
                  <label
                    key={option.value}
                    className="cx-inset-sm flex cursor-pointer gap-4 rounded-2xl p-4"
                  >
                    <input
                      type="checkbox"
                      checked={checked}
                      disabled={!preference.email_enabled}
                      onChange={() => toggleEvent(option.value)}
                      className="mt-1 h-4 w-4"
                    />

                    <span>
                      <span className="block text-sm font-semibold">
                        {option.label}
                      </span>
                      <span className="mt-1 block text-xs leading-5 text-[var(--cx-muted)]">
                        {option.description}
                      </span>
                    </span>
                  </label>
                );
              })}
            </div>

            <div className="mt-7 max-w-sm">
              <label
                htmlFor="minimum-risk-delta"
                className="text-sm font-semibold"
              >
                Minimum risk change
              </label>

              <div className="mt-2 flex items-center gap-3">
                <input
                  id="minimum-risk-delta"
                  type="number"
                  min={1}
                  max={100}
                  value={preference.minimum_risk_delta}
                  disabled={!preference.email_enabled}
                  onChange={(event) => {
                    const value = Number(event.target.value);

                    setPreference({
                      ...preference,
                      minimum_risk_delta: Number.isFinite(value)
                        ? Math.min(100, Math.max(1, value))
                        : 1,
                    });
                  }}
                  className="cx-input w-28"
                />
                <span className="text-sm text-[var(--cx-muted)]">
                  risk point(s)
                </span>
              </div>
            </div>

            <div className="mt-7 flex flex-wrap items-center gap-4">
              <button
                type="button"
                disabled={savingPreferences}
                onClick={() => void savePreferences()}
                className="cx-button cx-button-primary"
              >
                {savingPreferences ? "Saving..." : "Save Preferences"}
              </button>

              <p className="text-xs text-[var(--cx-muted)]">
                {preference.persisted
                  ? "Saved preference"
                  : "Using platform defaults until you save"}
              </p>
            </div>
          </section>
        ) : null}
      </div>
    </main>
  );
}
