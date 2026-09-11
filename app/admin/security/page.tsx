"use client";

import {
  useCallback,
  useEffect,
  useMemo,
  useState,
} from "react";

import Link from "next/link";

import {
  type AdminSecurityDevice,
  type AdminSecurityDeviceStatus,
  type AdminSecurityResponse,
  getAdminSecurity,
  revokeAdminSecurityDevice,
} from "@/lib/api";

type Filter =
  | "all"
  | AdminSecurityDeviceStatus;

function formatDate(value?: string | null) {
  if (!value) return "—";

  const date = new Date(value);

  if (Number.isNaN(date.getTime())) {
    return value;
  }

  return date.toLocaleString();
}

function deviceLabel(
  device: AdminSecurityDevice,
) {
  return (
    device.name ||
    [
      device.browser,
      device.platform,
    ]
      .filter(Boolean)
      .join(" on ") ||
    "Unnamed device"
  );
}

function statusClasses(
  status: AdminSecurityDeviceStatus,
) {
  switch (status) {
    case "trusted":
      return "border-emerald-500/20 bg-emerald-500/[0.06] text-emerald-300";

    case "expired":
      return "border-amber-500/20 bg-amber-500/[0.06] text-amber-300";

    case "revoked":
      return "border-red-500/20 bg-red-500/[0.07] text-red-300";

    default:
      return "border-white/[0.09] bg-white/[0.03] text-white/45";
  }
}

export default function AdminSecurityPage() {
  const [data, setData] =
    useState<AdminSecurityResponse["data"] | null>(
      null,
    );

  const [loading, setLoading] =
    useState(true);

  const [refreshing, setRefreshing] =
    useState(false);

  const [error, setError] = useState("");

  const [query, setQuery] = useState("");

  const [filter, setFilter] =
    useState<Filter>("all");

  const [revoking, setRevoking] =
    useState<string | null>(null);

  const [selected, setSelected] =
    useState<AdminSecurityDevice | null>(
      null,
    );

  const load = useCallback(
    async (silent = false) => {
      try {
        if (silent) {
          setRefreshing(true);
        } else {
          setLoading(true);
        }

        setError("");

        const response =
          await getAdminSecurity();

        setData(response.data);
      } catch (err) {
        setError(
          err instanceof Error
            ? err.message
            : "Unable to load security telemetry.",
        );
      } finally {
        setLoading(false);
        setRefreshing(false);
      }
    },
    [],
  );

  useEffect(() => {
    void load();

    const timer =
      window.setInterval(() => {
        void load(true);
      }, 20000);

    return () =>
      window.clearInterval(timer);
  }, [load]);

  const devices = useMemo(
    () => data?.devices ?? [],
    [data?.devices],
  );

  const filteredDevices = useMemo(() => {
    const search =
      query.trim().toLowerCase();

    return devices.filter((device) => {
      if (
        filter !== "all" &&
        device.status !== filter
      ) {
        return false;
      }

      if (!search) {
        return true;
      }

      return [
        device.id,
        device.name ?? "",
        device.device_type ?? "",
        device.browser ?? "",
        device.platform ?? "",
        device.last_ip_address ?? "",
        device.user?.name ?? "",
        device.user?.email ?? "",
        device.status,
      ].some((value) =>
        value
          .toLowerCase()
          .includes(search),
      );
    });
  }, [devices, query, filter]);

  async function handleRevoke(
    device: AdminSecurityDevice,
  ) {
    if (
      device.status === "revoked" ||
      revoking
    ) {
      return;
    }

    const confirmed =
      window.confirm(
        `Revoke trust for "${deviceLabel(
          device,
        )}"?\n\nThis removes trusted-device status. It does NOT currently terminate active API sessions.`,
      );

    if (!confirmed) return;

    try {
      setRevoking(device.id);
      setError("");

      await revokeAdminSecurityDevice(
        device.id,
      );

      setSelected(null);

      await load(true);
    } catch (err) {
      setError(
        err instanceof Error
          ? err.message
          : "Unable to revoke device trust.",
      );
    } finally {
      setRevoking(null);
    }
  }

  const summary = data?.summary;

  return (
    <main className="min-h-screen bg-[#050505] text-white">

      <section className="mx-auto max-w-7xl px-5 pb-24 pt-28 sm:px-8">
        <div className="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
          <div>
            <div className="mb-3 inline-flex items-center gap-2 rounded-full border border-red-500/15 bg-red-500/[0.04] px-4 py-2 text-[10px] font-bold uppercase tracking-[0.22em] text-red-400">
              <span className="h-1.5 w-1.5 rounded-full bg-red-500 shadow-[0_0_12px_rgba(239,68,68,.8)]" />
              Admin · Security Operations
            </div>

            <h1 className="text-4xl font-black tracking-tight sm:text-5xl">
              Device Trust Registry
            </h1>

            <p className="mt-4 max-w-3xl text-sm leading-7 text-white/40">
              Platform-wide visibility into
              registered device trust records,
              verification state, expiration
              and administrative revocation.
            </p>
          </div>

          <div className="flex flex-wrap gap-3">
            <Link
              href="/admin"
              className="cx-button cx-button-secondary rounded-xl px-5 py-3 text-sm font-semibold"
            >
              ← Command Center
            </Link>

            <button
              type="button"
              disabled={
                loading || refreshing
              }
              onClick={() =>
                void load(true)
              }
              className="cx-button cx-button-primary rounded-xl px-5 py-3 text-sm font-semibold disabled:opacity-40"
            >
              {refreshing
                ? "Refreshing..."
                : "↻ Refresh"}
            </button>
          </div>
        </div>

        {error && (
          <div className="mt-6 rounded-2xl border border-red-500/20 bg-red-500/[0.06] px-4 py-3 text-sm text-red-200">
            {error}
          </div>
        )}

        <section className="mt-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
          {[
            [
              summary?.total ?? 0,
              "Registered",
            ],
            [
              summary?.trusted ?? 0,
              "Trusted",
            ],
            [
              summary?.pending ?? 0,
              "Pending",
            ],
            [
              summary?.expired ?? 0,
              "Expired",
            ],
            [
              summary?.revoked ?? 0,
              "Revoked",
            ],
          ].map(([value, label]) => (
            <div
              key={String(label)}
              className="rounded-2xl border border-white/[0.07] bg-[#09090b] p-5 shadow-[0_18px_50px_rgba(0,0,0,.35)]"
            >
              <div className="text-3xl font-black">
                {loading ? "—" : value}
              </div>

              <div className="mt-3 text-[10px] font-bold uppercase tracking-[0.16em] text-white/30">
                {label}
              </div>
            </div>
          ))}
        </section>

        <section className="mt-5 rounded-2xl border border-red-500/15 bg-red-500/[0.025] p-6">
          <div className="text-[10px] font-bold uppercase tracking-[0.2em] text-red-400">
            Enforcement Boundary
          </div>

          <h2 className="mt-2 text-xl font-black">
            Trust registry is active.
            Admin access enforcement is not.
          </h2>

          <p className="mt-3 max-w-4xl text-sm leading-7 text-white/40">
            CrypticX Lab currently records,
            verifies, expires and revokes
            trusted-device state. The backend
            does not yet require a trusted
            device before entering the Admin
            Console, and revoking a device
            does not currently terminate
            active Sanctum API tokens.
          </p>

          <div className="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            {[
              [
                "Device Registry",
                data?.capabilities.registry
                  ? "Available"
                  : "Unavailable",
              ],
              [
                "Trust Revocation",
                data?.capabilities.revocation
                  ? "Available"
                  : "Unavailable",
              ],
              [
                "Admin Enforcement",
                data?.capabilities
                  .admin_access_enforcement
                  ? "Enabled"
                  : "Not Enabled",
              ],
              [
                "Session Termination",
                data?.capabilities
                  .session_termination_on_revoke
                  ? "Enabled"
                  : "Not Enabled",
              ],
              [
                "MFA / Passkey",
                data?.capabilities
                  .mfa_or_passkey_verification
                  ? "Enabled"
                  : "Not Implemented",
              ],
            ].map(([label, value]) => (
              <div
                key={label}
                className="rounded-xl border border-white/[0.06] bg-black/25 p-4"
              >
                <div className="text-[9px] font-bold uppercase tracking-wider text-white/25">
                  {label}
                </div>

                <div className="mt-2 text-sm font-semibold text-white/65">
                  {loading ? "—" : value}
                </div>
              </div>
            ))}
          </div>
        </section>

        <section className="mt-5 rounded-2xl border border-white/[0.07] bg-[#09090b] p-5">
          <div className="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
            <input
              type="search"
              value={query}
              onChange={(event) =>
                setQuery(event.target.value)
              }
              placeholder="Search user, email, device, platform, IP..."
              className="cx-input w-full rounded-xl px-4 py-3 text-sm xl:max-w-xl"
            />

            <div className="flex flex-wrap gap-2">
              {(
                [
                  ["all", "All"],
                  ["trusted", "Trusted"],
                  ["pending", "Pending"],
                  ["expired", "Expired"],
                  ["revoked", "Revoked"],
                ] as const
              ).map(([value, label]) => (
                <button
                  key={value}
                  type="button"
                  onClick={() =>
                    setFilter(value)
                  }
                  className={
                    filter === value
                      ? "cx-button cx-button-primary rounded-xl px-4 py-2 text-xs font-semibold"
                      : "cx-button cx-button-secondary rounded-xl px-4 py-2 text-xs font-semibold"
                  }
                >
                  {label}
                </button>
              ))}
            </div>
          </div>

          <div className="mt-4 text-xs text-white/20">
            Auto-refresh every 20 seconds ·
            Owner / Administrator scope
          </div>
        </section>

        <section className="mt-5 overflow-hidden rounded-2xl border border-white/[0.07] bg-[#09090b]">
          <div className="flex items-center justify-between border-b border-white/[0.06] px-5 py-4">
            <div>
              <div className="text-[10px] font-bold uppercase tracking-[0.2em] text-red-400">
                Platform Registry
              </div>

              <h2 className="mt-1 font-black">
                Registered Devices
              </h2>
            </div>

            <span className="text-xs text-white/25">
              {filteredDevices.length} devices
            </span>
          </div>

          {loading ? (
            <div className="p-6 text-sm text-white/35">
              Loading security registry...
            </div>
          ) : filteredDevices.length ? (
            <div className="divide-y divide-white/[0.05]">
              {filteredDevices.map(
                (device) => (
                  <article
                    key={device.id}
                    className="p-5 transition hover:bg-red-500/[0.025]"
                  >
                    <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                      <div className="min-w-0 flex-1">
                        <div className="flex flex-wrap items-center gap-2">
                          <h3 className="font-bold text-white/85">
                            {deviceLabel(
                              device,
                            )}
                          </h3>

                          <span
                            className={`rounded-full border px-3 py-1 text-[10px] font-bold uppercase tracking-wider ${statusClasses(
                              device.status,
                            )}`}
                          >
                            {device.status}
                          </span>
                        </div>

                        <div className="mt-3 flex flex-wrap gap-x-5 gap-y-2 text-xs text-white/30">
                          <span>
                            Owner:{" "}
                            <span className="text-white/55">
                              {device.user
                                ?.name ??
                                "Unknown"}
                            </span>
                          </span>

                          <span>
                            IP:{" "}
                            <span className="text-white/55">
                              {device.last_ip_address ??
                                "—"}
                            </span>
                          </span>

                          <span>
                            Last Seen:{" "}
                            <span className="text-white/55">
                              {formatDate(
                                device.last_seen_at,
                              )}
                            </span>
                          </span>
                        </div>

                        <div className="mt-2 text-[11px] text-white/20">
                          {[
                            device.device_type,
                            device.browser,
                            device.platform,
                          ]
                            .filter(Boolean)
                            .join(" · ") ||
                            "Device details unavailable"}
                        </div>
                      </div>

                      <div className="flex shrink-0 gap-2">
                        <button
                          type="button"
                          onClick={() =>
                            setSelected(
                              device,
                            )
                          }
                          className="cx-button cx-button-secondary rounded-xl px-4 py-2 text-xs font-semibold"
                        >
                          Inspect
                        </button>

                        {device.status !==
                          "revoked" && (
                          <button
                            type="button"
                            disabled={
                              revoking ===
                              device.id
                            }
                            onClick={() =>
                              void handleRevoke(
                                device,
                              )
                            }
                            className="rounded-xl border border-red-500/20 bg-red-500/[0.06] px-4 py-2 text-xs font-semibold text-red-300 transition hover:bg-red-500/[0.1] disabled:opacity-40"
                          >
                            {revoking ===
                            device.id
                              ? "Revoking..."
                              : "Revoke Trust"}
                          </button>
                        )}
                      </div>
                    </div>
                  </article>
                ),
              )}
            </div>
          ) : (
            <div className="px-5 py-14 text-center text-sm text-white/35">
              No devices match the current
              filters.
            </div>
          )}
        </section>

        <section className="mt-5 rounded-2xl border border-white/[0.07] bg-[#09090b] p-6">
          <div className="text-[10px] font-bold uppercase tracking-[0.2em] text-red-400">
            Security Roadmap
          </div>

          <h2 className="mt-2 text-xl font-black">
            Enforcement comes after
            identity verification
          </h2>

          <p className="mt-3 max-w-4xl text-sm leading-7 text-white/35">
            The next enforcement phase should
            add re-authentication, MFA or
            passkey verification, trusted
            device middleware, session/token
            binding and controlled session
            termination. Those controls are
            intentionally not simulated in
            this console.
          </p>
        </section>
      </section>

      {selected && (
        <div className="fixed inset-0 z-[100] flex items-center justify-center bg-black/80 p-4 backdrop-blur-sm">
          <div className="max-h-[92vh] w-full max-w-3xl overflow-y-auto rounded-3xl border border-red-500/15 bg-[#09090b] p-6">
            <div className="flex items-start justify-between gap-4">
              <div>
                <div className="text-[10px] font-bold uppercase tracking-[0.2em] text-red-400">
                  Device Record
                </div>

                <h2 className="mt-2 text-2xl font-black">
                  {deviceLabel(selected)}
                </h2>
              </div>

              <button
                type="button"
                onClick={() =>
                  setSelected(null)
                }
                className="text-xl text-white/35 hover:text-white"
              >
                ×
              </button>
            </div>

            <div className="mt-6 grid gap-3 sm:grid-cols-2">
              {[
                ["ID", selected.id],
                [
                  "Status",
                  selected.status,
                ],
                [
                  "Owner",
                  selected.user?.name ??
                    "Unknown",
                ],
                [
                  "Owner Email",
                  selected.user?.email ??
                    "—",
                ],
                [
                  "Device Type",
                  selected.device_type ??
                    "—",
                ],
                [
                  "Browser",
                  selected.browser ?? "—",
                ],
                [
                  "Platform",
                  selected.platform ?? "—",
                ],
                [
                  "Last IP",
                  selected.last_ip_address ??
                    "—",
                ],
                [
                  "First Seen",
                  formatDate(
                    selected.first_seen_at,
                  ),
                ],
                [
                  "Last Seen",
                  formatDate(
                    selected.last_seen_at,
                  ),
                ],
                [
                  "Verified",
                  formatDate(
                    selected.verified_at,
                  ),
                ],
                [
                  "Expires",
                  formatDate(
                    selected.expires_at,
                  ),
                ],
                [
                  "Revoked",
                  formatDate(
                    selected.revoked_at,
                  ),
                ],
                [
                  "Revoked By",
                  selected.revoked_by
                    ?.name ?? "—",
                ],
              ].map(([label, value]) => (
                <div
                  key={label}
                  className="rounded-xl border border-white/[0.06] bg-black/25 p-4"
                >
                  <div className="text-[9px] font-bold uppercase tracking-wider text-white/25">
                    {label}
                  </div>

                  <div className="mt-2 break-all text-sm text-white/60">
                    {value}
                  </div>
                </div>
              ))}
            </div>

            {selected.status !==
              "revoked" && (
              <div className="mt-5 border-t border-white/[0.06] pt-5">
                <button
                  type="button"
                  disabled={
                    revoking ===
                    selected.id
                  }
                  onClick={() =>
                    void handleRevoke(
                      selected,
                    )
                  }
                  className="rounded-xl border border-red-500/20 bg-red-500/[0.06] px-5 py-3 text-sm font-semibold text-red-300 transition hover:bg-red-500/[0.1] disabled:opacity-40"
                >
                  {revoking === selected.id
                    ? "Revoking..."
                    : "Revoke Device Trust"}
                </button>

                <p className="mt-3 text-xs leading-5 text-white/25">
                  This revokes the device trust
                  record only. It does not
                  currently terminate active
                  authentication tokens.
                </p>
              </div>
            )}
          </div>
        </div>
      )}
    </main>
  );
}
