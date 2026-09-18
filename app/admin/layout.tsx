"use client";

import { useEffect, useState } from "react";
import { usePathname, useRouter } from "next/navigation";
import AdminSidebar from "./components/AdminSidebar";
import { getCurrentUser, getStoredToken } from "@/lib/api";

type AdminLayoutState = "checking" | "authorized" | "denied";

const ADMIN_ROLES = new Set(["Owner", "Administrator"]);

export default function AdminLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  const router = useRouter();
  const pathname = usePathname();

  const [state, setState] = useState<AdminLayoutState>("checking");
  const [mobileOpen, setMobileOpen] = useState(false);

  useEffect(() => {
    let active = true;

    async function verifyAdminAccess() {
      const token = getStoredToken();

      if (!token) {
        router.replace(`/login?next=${encodeURIComponent(pathname)}`);
        return;
      }

      try {
        const response = await getCurrentUser();

        const roles = response.data.user.roles ?? [];
        const isAdmin = roles.some((role) =>
          ADMIN_ROLES.has(role.name),
        );

        if (!active) {
          return;
        }

        if (!isAdmin) {
          setState("denied");
          return;
        }

        setState("authorized");
      } catch {
        if (!active) {
          return;
        }

        window.localStorage.removeItem("crypticx_token");
        window.sessionStorage.removeItem("crypticx_token");

        router.replace(`/login?next=${encodeURIComponent(pathname)}`);
      }
    }

    verifyAdminAccess();

    return () => {
      active = false;
    };
  }, [pathname, router]);

  if (state === "checking") {
    return (
      <main className="flex min-h-screen items-center justify-center bg-[#050505] px-6 text-white">
        <div className="w-full max-w-sm rounded-2xl border border-white/[0.07] bg-[#09090b] p-6 text-center shadow-[0_20px_60px_rgba(0,0,0,0.45)]">
          <img
            src="/brand/crypticx2.png"
            alt="CrypticX Lab"
            width={72}
            height={72}
            className="mx-auto h-16 w-16 object-contain"
          />

          <div className="mt-4 text-[10px] font-bold uppercase tracking-[0.25em] text-white/35">
            CrypticX Security
          </div>

          <h1 className="mt-2 text-lg font-bold">
            Verifying administrative access
          </h1>

          <p className="mt-2 text-xs leading-5 text-white/30">
            Validating authentication and administrative permissions...
          </p>
        </div>
      </main>
    );
  }

  if (state === "denied") {
    return (
      <main className="flex min-h-screen items-center justify-center bg-[#050505] px-6 text-white">
        <div className="w-full max-w-md rounded-2xl border border-white/[0.07] bg-[#09090b] p-7 text-center shadow-[0_20px_60px_rgba(0,0,0,0.45)]">
          <img
            src="/brand/crypticx2.png"
            alt="CrypticX Lab"
            width={72}
            height={72}
            className="mx-auto h-16 w-16 object-contain"
          />

          <div className="mt-5 text-[10px] font-bold uppercase tracking-[0.25em] text-white/35">
            Access Control
          </div>

          <h1 className="mt-2 text-xl font-bold">
            Administrative access denied
          </h1>

          <p className="mt-2 text-sm leading-6 text-white/35">
            Your authenticated account does not have an Owner or
            Administrator role.
          </p>

          <button
            type="button"
            onClick={() => router.replace("/")}
            className="mt-6 rounded-xl border border-white/[0.08] bg-white/[0.04] px-5 py-3 text-xs font-semibold text-white/70 transition hover:bg-white/[0.07] hover:text-white"
          >
            Return to Platform
          </button>
        </div>
      </main>
    );
  }

  return (
    <div className="min-h-screen bg-[#050505] text-white">
      <AdminSidebar />

      <div className="min-h-screen lg:pl-64">
        <div className="flex items-center justify-between border-b border-white/[0.06] bg-[#070708] px-4 py-3 lg:hidden">
          <button
            type="button"
            onClick={() => router.push("/")}
            aria-label="CrypticX Lab home"
            className="flex items-center gap-2"
          >
            <img
              src="/brand/crypticx2.png"
              alt="CrypticX Lab"
              width={44}
              height={44}
              className="h-10 w-10 object-contain"
            />
            <span className="text-sm font-bold text-white">
              CrypticX Lab
            </span>
          </button>

          <button
            type="button"
            onClick={() => setMobileOpen((value) => !value)}
            className="rounded-xl border border-white/[0.08] bg-white/[0.03] px-4 py-2 text-xs font-semibold text-white/70"
          >
            ☰ Menu
          </button>
        </div>

        {mobileOpen && (
          <div className="border-b border-white/[0.06] bg-[#07080a] px-4 py-3 lg:hidden">
            <nav className="grid gap-1">
              {[
                ["Dashboard", "/admin"],
                ["Users", "/admin/users"],
                ["Targets", "/admin/targets"],
                ["Assessments", "/admin/scans"],
                ["Findings", "/admin/findings"],
                ["Reports", "/admin/reports"],
                ["Monitoring", "/admin/monitoring"],
                ["Workers", "/admin/workers"],
                ["Audit Logs", "/admin/audit-logs"],
                ["Roles", "/admin/roles"],
                ["Security", "/admin/security"],
                ["Settings", "/admin/settings"],
                ["View Site", "/"],
              ].map(([label, href]) => (
                <button
                  key={href}
                  type="button"
                  onClick={() => {
                    setMobileOpen(false);
                    router.push(href);
                  }}
                  className="rounded-xl px-4 py-3 text-left text-sm text-white/55 transition hover:bg-white/[0.04] hover:text-white"
                >
                  {label}
                </button>
              ))}
            </nav>
          </div>
        )}

        {children}
      </div>
    </div>
  );
}
