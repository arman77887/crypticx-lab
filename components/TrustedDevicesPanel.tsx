"use client";

import {
  useCallback,
  useEffect,
  useState,
} from "react";

import {
  clearTrustedDeviceCredential,
  getStoredTrustedDeviceId,
  getTrustedDevices,
  registerTrustedDevice,
  revokeTrustedDevice,
  storeTrustedDeviceCredential,
  type TrustedDevice,
  verifyTrustedDevice,
} from "@/lib/api";

function status(device: TrustedDevice): string {
  if (device.revoked_at) {
    return "Revoked";
  }

  if (device.is_trusted) {
    return "Trusted";
  }

  return "Pending";
}

function deviceName(device: TrustedDevice): string {
  if (device.name) {
    return device.name;
  }

  return [
    device.platform,
    device.browser,
    device.device_type,
  ]
    .filter(Boolean)
    .join(" · ") || "Unknown device";
}

export default function TrustedDevicesPanel() {
  const [devices, setDevices] = useState<TrustedDevice[]>([]);
  const [loading, setLoading] = useState(true);
  const [working, setWorking] = useState(false);
  const [error, setError] = useState("");
  const [message, setMessage] = useState("");

  const load = useCallback(async () => {
    try {
      setLoading(true);
      setError("");

      const response = await getTrustedDevices();

      setDevices(response.data.devices);
    } catch (err) {
      setError(
        err instanceof Error
          ? err.message
          : "Unable to load trusted devices.",
      );
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  async function trustThisDevice() {
    if (working) {
      return;
    }

    const name =
      window.prompt(
        "Name this device:",
        "Current Browser",
      )?.trim() || "Current Browser";

    try {
      setWorking(true);
      setError("");
      setMessage("");

      const registration =
        await registerTrustedDevice();

      const deviceId =
        registration.data.device.id;

      const deviceToken =
        registration.data.device_token;

      await verifyTrustedDevice(
        deviceId,
        deviceToken,
        name,
      );

      /*
       * Persist only after server-side verification succeeds.
       */
      storeTrustedDeviceCredential(
        deviceId,
        deviceToken,
      );

      setMessage(
        "This browser is now trusted for 30 days.",
      );

      await load();
    } catch (err) {
      setError(
        err instanceof Error
          ? err.message
          : "Unable to trust this device.",
      );
    } finally {
      setWorking(false);
    }
  }

  async function revoke(device: TrustedDevice) {
    if (working) {
      return;
    }

    if (
      !window.confirm(
        `Revoke trusted-device access for "${deviceName(device)}"?`,
      )
    ) {
      return;
    }

    try {
      setWorking(true);
      setError("");
      setMessage("");

      await revokeTrustedDevice(device.id);

      if (
        getStoredTrustedDeviceId() === device.id
      ) {
        clearTrustedDeviceCredential();
      }

      setMessage("Device trust revoked.");

      await load();
    } catch (err) {
      setError(
        err instanceof Error
          ? err.message
          : "Unable to revoke device.",
      );
    } finally {
      setWorking(false);
    }
  }

  const currentDeviceId =
    getStoredTrustedDeviceId();

  return (
    <section className="mt-6 cx-card rounded-[30px] p-6 sm:p-8">
      <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
          <h2 className="text-xl font-bold">
            Trusted Devices
          </h2>

          <p className="mt-1 text-sm text-[var(--cx-muted)]">
            Browser-held device credentials used for
            administrator access enforcement.
          </p>
        </div>

        <button
          type="button"
          disabled={working}
          onClick={() => void trustThisDevice()}
          className="cx-button cx-button-primary rounded-xl px-4 py-2.5 text-sm font-semibold disabled:opacity-50"
        >
          {working
            ? "Working..."
            : "Trust This Device"}
        </button>
      </div>

      {error && (
        <div className="mt-5 rounded-2xl border border-red-500/20 bg-red-500/[0.06] px-4 py-3 text-sm text-red-200">
          {error}
        </div>
      )}

      {message && (
        <div className="mt-5 rounded-2xl border border-emerald-500/20 bg-emerald-500/[0.05] px-4 py-3 text-sm text-emerald-200">
          {message}
        </div>
      )}

      <div className="mt-6 space-y-3">
        {loading ? (
          <div className="cx-inset-sm rounded-2xl p-5 text-sm text-[var(--cx-muted)]">
            Loading trusted devices...
          </div>
        ) : devices.length === 0 ? (
          <div className="cx-inset-sm rounded-2xl p-5 text-sm text-[var(--cx-muted)]">
            No trusted devices registered.
          </div>
        ) : (
          devices.map((device) => {
            const current =
              currentDeviceId === device.id;

            return (
              <div
                key={device.id}
                className="cx-inset-sm rounded-2xl p-5"
              >
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                  <div>
                    <div className="font-semibold">
                      {deviceName(device)}
                    </div>

                    <div className="mt-1 text-xs text-[var(--cx-muted)]">
                      {current
                        ? "Current browser · "
                        : ""}
                      {device.last_ip_address
                        ? `${device.last_ip_address} · `
                        : ""}
                      {device.expires_at
                        ? `Expires ${new Date(
                            device.expires_at,
                          ).toLocaleString()}`
                        : "No active trust expiry"}
                    </div>
                  </div>

                  <div className="flex items-center gap-3">
                    <span className="rounded-full px-3 py-1 text-xs font-semibold cx-inset-sm">
                      {current
                        ? `${status(device)} · Current`
                        : status(device)}
                    </span>

                    {!device.revoked_at && (
                      <button
                        type="button"
                        disabled={working}
                        onClick={() =>
                          void revoke(device)
                        }
                        className="cx-button cx-button-secondary rounded-xl px-4 py-2 text-sm font-semibold disabled:opacity-50"
                      >
                        Revoke
                      </button>
                    )}
                  </div>
                </div>
              </div>
            );
          })
        )}
      </div>

      <p className="mt-5 text-xs leading-5 text-[var(--cx-muted)]">
        Device trust is an additional possession
        signal. It is not MFA or a passkey.
      </p>
    </section>
  );
}
