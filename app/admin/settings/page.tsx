"use client";

import {
  useCallback,
  useEffect,
  useState,
} from "react";

import Link from "next/link";

import {
  type AdminPlatformSettings,
  type AdminSettingsCapabilities,
  getAdminSettings,
  updateAdminSettings,
} from "@/lib/api";

type SettingsState =
  AdminPlatformSettings;

function Capability({
  label,
  enabled,
  description,
}: {
  label: string;
  enabled: boolean;
  description: string;
}) {
  return (
    <div className="rounded-2xl border border-white/[0.06] bg-black/25 p-4">
      <div className="flex items-center justify-between gap-4">
        <span className="font-semibold text-white/75">
          {label}
        </span>

        <span
          className={
            enabled
              ? "rounded-full border border-emerald-500/20 bg-emerald-500/[0.06] px-3 py-1 text-[10px] font-bold uppercase tracking-wider text-emerald-300"
              : "rounded-full border border-white/[0.08] bg-white/[0.025] px-3 py-1 text-[10px] font-bold uppercase tracking-wider text-white/35"
          }
        >
          {enabled
            ? "Enforced"
            : "Not Implemented"}
        </span>
      </div>

      <p className="mt-2 text-xs leading-5 text-white/30">
        {description}
      </p>
    </div>
  );
}

export default function AdminSettingsPage() {
  const [settings, setSettings] =
    useState<SettingsState | null>(
      null,
    );

  const [capabilities, setCapabilities] =
    useState<AdminSettingsCapabilities | null>(
      null,
    );

  const [loading, setLoading] =
    useState(true);

  const [saving, setSaving] =
    useState(false);

  const [error, setError] =
    useState("");

  const [message, setMessage] =
    useState("");

  const load = useCallback(async () => {
    try {
      setLoading(true);
      setError("");

      const response =
        await getAdminSettings();

      setSettings(
        response.data.settings,
      );

      setCapabilities(
        response.data.capabilities,
      );
    } catch (err) {
      setError(
        err instanceof Error
          ? err.message
          : "Unable to load platform settings.",
      );
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  async function toggle(
    key: keyof SettingsState,
  ) {
    if (!settings || saving) {
      return;
    }

    const nextValue =
      !settings[key];

    const action =
      nextValue ? "Enable" : "Disable";

    const confirmationMessage =
      key === "public_registration_enabled"
        ? `${action} public account registration?`
        : key === "assessment_creation_enabled"
          ? `${action} creation of new assessments?`
          : `${action} trusted-device enforcement for administrator API access?`;

    const confirmed =
      window.confirm(confirmationMessage);

    if (!confirmed) {
      return;
    }

    try {
      setSaving(true);
      setError("");
      setMessage("");

      const response =
        await updateAdminSettings({
          [key]: nextValue,
        });

      setSettings(
        response.data.settings,
      );

      setMessage(response.message);
    } catch (err) {
      setError(
        err instanceof Error
          ? err.message
          : "Unable to update platform settings.",
      );
    } finally {
      setSaving(false);
    }
  }

  return (
    <main className="min-h-screen bg-[#050505] text-white">

      <section className="mx-auto max-w-7xl px-5 pb-24 pt-28 sm:px-8">
        <div className="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
          <div>
            <div className="mb-3 inline-flex items-center gap-2 rounded-full border border-red-500/15 bg-red-500/[0.04] px-4 py-2 text-[10px] font-bold uppercase tracking-[0.22em] text-red-400">
              <span className="h-1.5 w-1.5 rounded-full bg-red-500 shadow-[0_0_12px_rgba(239,68,68,.8)]" />
              Admin · Platform Configuration
            </div>

            <h1 className="text-4xl font-black tracking-tight sm:text-5xl">
              Platform Settings
            </h1>

            <p className="mt-4 max-w-3xl text-sm leading-7 text-white/40">
              Persistent operational controls
              backed by CrypticX Lab database
              state and server-side
              enforcement.
            </p>
          </div>

          <Link
            href="/admin"
            className="cx-button cx-button-secondary rounded-xl px-5 py-3 text-sm font-semibold"
          >
            ← Command Center
          </Link>
        </div>

        {error && (
          <div className="mt-6 rounded-2xl border border-red-500/20 bg-red-500/[0.06] px-4 py-3 text-sm text-red-200">
            {error}
          </div>
        )}

        {message && (
          <div className="mt-6 rounded-2xl border border-emerald-500/20 bg-emerald-500/[0.05] px-4 py-3 text-sm text-emerald-200">
            {message}
          </div>
        )}

        <section className="mt-8 grid gap-5 lg:grid-cols-2">
          <div className="rounded-3xl border border-red-500/12 bg-[#09090b] p-6 shadow-[0_20px_70px_rgba(0,0,0,.4)] sm:p-8">
            <div className="text-[10px] font-bold uppercase tracking-[0.2em] text-red-400">
              Enforced Controls
            </div>

            <h2 className="mt-2 text-2xl font-black">
              Access & Assessment Policy
            </h2>

            <p className="mt-3 text-sm leading-7 text-white/35">
              These controls are persisted in
              PostgreSQL and checked by the
              backend before the corresponding
              operation is allowed.
            </p>

            <div className="mt-7 space-y-4">
              <div className="rounded-2xl border border-white/[0.07] bg-black/25 p-5">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                  <div>
                    <h3 className="font-bold text-white/80">
                      Public Registration
                    </h3>

                    <p className="mt-2 text-xs leading-5 text-white/30">
                      Controls whether new
                      public accounts can be
                      created through the
                      registration API.
                    </p>
                  </div>

                  <button
                    type="button"
                    disabled={
                      loading || saving
                    }
                    onClick={() =>
                      void toggle(
                        "public_registration_enabled",
                      )
                    }
                    className={
                      settings
                        ?.public_registration_enabled
                        ? "cx-button cx-button-primary shrink-0 rounded-xl px-5 py-2.5 text-xs font-bold"
                        : "cx-button cx-button-secondary shrink-0 rounded-xl px-5 py-2.5 text-xs font-bold"
                    }
                  >
                    {loading
                      ? "Loading..."
                      : settings
                          ?.public_registration_enabled
                        ? "Enabled"
                        : "Disabled"}
                  </button>
                </div>
              </div>

              <div className="rounded-2xl border border-white/[0.07] bg-black/25 p-5">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                  <div>
                    <h3 className="font-bold text-white/80">
                      Assessment Creation
                    </h3>

                    <p className="mt-2 text-xs leading-5 text-white/30">
                      Controls whether users
                      can create new scanner
                      assessments. Existing
                      historical assessments
                      remain accessible.
                    </p>
                  </div>

                  <button
                    type="button"
                    disabled={
                      loading || saving
                    }
                    onClick={() =>
                      void toggle(
                        "assessment_creation_enabled",
                      )
                    }
                    className={
                      settings
                        ?.assessment_creation_enabled
                        ? "cx-button cx-button-primary shrink-0 rounded-xl px-5 py-2.5 text-xs font-bold"
                        : "cx-button cx-button-secondary shrink-0 rounded-xl px-5 py-2.5 text-xs font-bold"
                    }
                  >
                    {loading
                      ? "Loading..."
                      : settings
                          ?.assessment_creation_enabled
                        ? "Enabled"
                        : "Disabled"}
                  </button>
                </div>
              </div>
            </div>
          </div>

          <div className="rounded-3xl border border-white/[0.07] bg-[#09090b] p-6 shadow-[0_20px_70px_rgba(0,0,0,.4)] sm:p-8">
            <div className="text-[10px] font-bold uppercase tracking-[0.2em] text-red-400">
              Enforcement Matrix
            </div>

            <h2 className="mt-2 text-2xl font-black">
              Current Security Capability
            </h2>

            <p className="mt-3 text-sm leading-7 text-white/35">
              Features without backend
              enforcement are intentionally
              reported as unavailable rather
              than simulated as active.
            </p>

            <div className="mt-7 space-y-3">
              <Capability
                label="Registration Kill Switch"
                enabled={
                  capabilities
                    ?.public_registration_enforcement ??
                  false
                }
                description="AuthController checks persistent platform policy before creating a user."
              />

              <Capability
                label="Assessment Kill Switch"
                enabled={
                  capabilities
                    ?.assessment_creation_enforcement ??
                  false
                }
                description="AssessmentController checks persistent policy before queueing a new assessment."
              />

              <Capability
                label="Administrator MFA"
                enabled={
                  capabilities
                    ?.mfa_enforcement ??
                  false
                }
                description="No MFA/passkey challenge is currently enforced for administrator access."
              />

              <div className="rounded-2xl border border-white/[0.06] bg-black/25 p-4">
                <div className="flex items-center justify-between gap-4">
                  <div>
                    <span className="font-semibold text-white/75">
                      Trusted Device Admin Gate
                    </span>

                    <p className="mt-2 text-xs leading-5 text-white/30">
                      Require a valid trusted-device credential on administrator API requests.
                    </p>
                  </div>

                  <button
                    type="button"
                    disabled={
                      loading ||
                      saving ||
                      !settings
                    }
                    onClick={() =>
                      void toggle(
                        "trusted_device_admin_enforcement",
                      )
                    }
                    className={
                      settings
                        ?.trusted_device_admin_enforcement
                        ? "cx-button cx-button-primary shrink-0 rounded-xl px-5 py-2.5 text-xs font-bold"
                        : "cx-button cx-button-secondary shrink-0 rounded-xl px-5 py-2.5 text-xs font-bold"
                    }
                  >
                    {loading
                      ? "Loading..."
                      : settings
                          ?.trusted_device_admin_enforcement
                        ? "Enforced"
                        : "Disabled"}
                  </button>
                </div>

                <p className="mt-3 text-[11px] leading-5 text-white/25">
                  Enabling this control requires this browser to already be trusted.
                </p>
              </div>

              <Capability
                label="Session Termination on Revoke"
                enabled={
                  capabilities
                    ?.session_termination_on_device_revoke ??
                  false
                }
                description="Revoking device trust does not currently delete active Sanctum tokens."
              />

              <Capability
                label="Worker Resource Limits"
                enabled={
                  capabilities
                    ?.worker_resource_limits ??
                  false
                }
                description="CPU and memory resource enforcement has not yet been implemented."
              />

              <Capability
                label="Worker Egress Policy"
                enabled={
                  capabilities
                    ?.worker_egress_policy ??
                  false
                }
                description="Dedicated scanner-worker outbound network policy is not yet observable."
              />

              <Capability
                label="Worker Isolation"
                enabled={
                  capabilities
                    ?.worker_isolation ??
                  false
                }
                description="Dedicated execution isolation has not yet been implemented as an enforced platform control."
              />

              <Capability
                label="Retention Cleanup"
                enabled={
                  capabilities
                    ?.retention_cleanup ??
                  false
                }
                description="Automatic evidence, audit and report retention jobs do not currently exist."
              />
            </div>
          </div>
        </section>

        <section className="mt-5 rounded-3xl border border-white/[0.07] bg-[#09090b] p-6 sm:p-8">
          <div className="text-[10px] font-bold uppercase tracking-[0.2em] text-red-400">
            Policy Semantics
          </div>

          <h2 className="mt-2 text-xl font-black">
            Existing records are preserved
          </h2>

          <p className="mt-3 max-w-4xl text-sm leading-7 text-white/35">
            Disabling registration prevents
            creation of new public accounts.
            Disabling assessment creation
            prevents new assessments from
            entering the queue. It does not
            delete users, targets, findings,
            reports, or existing assessment
            history.
          </p>
        </section>
      </section>
    </main>
  );
}
