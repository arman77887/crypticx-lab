"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { useEffect, useState } from "react";
import { getCurrentUser, getStoredToken, logout } from "@/lib/api";

type CurrentUser = {
  id: string;
  name: string;
  email: string;
  roles?: Array<{
    id?: string;
    name: string;
  }>;
};

type NavLink = {
  href: string;
  label: string;
};

export default function Navbar() {
  const pathname = usePathname();

  const [user, setUser] = useState<CurrentUser | null>(null);
  const [loading, setLoading] = useState(true);
  const [mobileOpen, setMobileOpen] = useState(false);

  useEffect(() => {
    let mounted = true;

    async function loadUser() {
      const token = getStoredToken();

      if (!token) {
        if (mounted) {
          setUser(null);
          setLoading(false);
        }
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
      } catch {
        if (mounted) {
          setUser(null);
        }
      } finally {
        if (mounted) {
          setLoading(false);
        }
      }
    }

    void loadUser();

    return () => {
      mounted = false;
    };
  }, []);

  useEffect(() => {
    setMobileOpen(false);
  }, [pathname]);

  const roleNames =
    user?.roles?.map((role) =>
      role.name.toLowerCase(),
    ) ?? [];

  const isAdmin =
    roleNames.includes("owner") ||
    roleNames.includes("administrator");

  async function handleLogout() {
    try {
      await logout();
    } catch {
      // Local auth state is still cleared by the logout helper.
    } finally {
      setUser(null);
      window.location.href = "/";
    }
  }

  const publicLinks: NavLink[] = [
    { href: "/", label: "Home" },
    { href: "/tools", label: "Tools" },
    { href: "/scanner", label: "Scanner" },
    { href: "/labs", label: "Security Labs" },
    { href: "/docs", label: "Docs" },
    { href: "/pricing", label: "Pricing" },
    { href: "/contact", label: "Contact" },
  ];

  const userLinks: NavLink[] = [
    { href: "/", label: "Home" },
    { href: "/dashboard", label: "Dashboard" },
    { href: "/scanner", label: "Scanner" },
    { href: "/findings", label: "Findings" },
    { href: "/monitoring", label: "Monitoring" },
    { href: "/docs", label: "Docs" },
    { href: "/profile", label: "Profile" },
  ];

  const adminLinks: NavLink[] = [
    { href: "/", label: "Home" },
    { href: "/admin", label: "Admin Console" },
    { href: "/scanner", label: "Scanner" },
    { href: "/findings", label: "Findings" },
    { href: "/monitoring", label: "Monitoring" },
    { href: "/docs", label: "Docs" },
    { href: "/profile", label: "Profile" },
  ];

  const mainLinks = user
    ? isAdmin
      ? adminLinks
      : userLinks
    : publicLinks;

  function isActive(href: string): boolean {
    if (href === "/") {
      return pathname === "/";
    }

    if (href === "/admin") {
      return (
        pathname === "/admin" ||
        pathname.startsWith("/admin/")
      );
    }

    return (
      pathname === href ||
      pathname.startsWith(`${href}/`)
    );
  }

  const displayName =
    user?.name?.trim() ||
    user?.email?.split("@")[0] ||
    "Account";

  const initial =
    displayName.charAt(0).toUpperCase() || "U";

  return (
    <header className="sticky top-0 z-50 border-b border-[var(--cx-border)] bg-[var(--cx-bg)]/95 backdrop-blur-xl">
      <div className="mx-auto flex h-14 max-w-7xl items-center gap-3 px-4 sm:px-6 lg:px-8">
        <Link
          href="/"
          aria-label="CrypticX Lab home"
          className="flex min-w-0 shrink-0 items-center gap-3 text-[var(--cx-text)]"
        >
          <span className="flex h-10 w-10 shrink-0 items-center justify-center sm:h-11 sm:w-11">
            <img
              src="/brand/crypticx2.png"
              alt="CrypticX Lab"
              width={64}
              height={64}
              className="h-full w-full object-contain"
            />
          </span>
        </Link>

        <nav className="ml-auto hidden min-w-0 items-center gap-1 xl:flex">
          {mainLinks.map((link) => {
            const active = isActive(link.href);

            return (
              <Link
                key={link.href}
                href={link.href}
                aria-current={active ? "page" : undefined}
                className={[
                  "rounded-xl px-3 py-2 text-sm font-semibold transition",
                  active
                    ? "border border-white/[0.09] bg-white/[0.07] text-[var(--cx-text)]"
                    : "border border-transparent text-[var(--cx-muted)] hover:bg-white/[0.04] hover:text-[var(--cx-text)]",
                ].join(" ")}
              >
                {link.label}
              </Link>
            );
          })}
        </nav>

        <div className="ml-auto hidden shrink-0 items-center gap-2 xl:flex">
          {!loading && !user && (
            <>
              <Link
                href="/login"
                className="cx-button cx-button-secondary text-sm"
              >
                Sign In
              </Link>

              <Link
                href="/register"
                className="cx-button cx-button-primary text-sm"
              >
                Sign Up
              </Link>
            </>
          )}

          {!loading && user && (
            <>
              <Link
                href="/profile"
                title={user.email}
                className="flex max-w-[180px] items-center gap-2 rounded-xl border border-white/[0.07] bg-white/[0.025] px-2.5 py-2 transition hover:bg-white/[0.05]"
              >
                <span className="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg border border-white/[0.08] bg-white/[0.05] text-xs font-bold text-[var(--cx-text)]">
                  {initial}
                </span>

                <span className="min-w-0">
                  <span className="block truncate text-xs font-semibold text-[var(--cx-text)]">
                    {displayName}
                  </span>
                  <span className="block truncate text-[9px] uppercase tracking-wider text-[var(--cx-muted)]">
                    {isAdmin ? "Administrator" : "User Account"}
                  </span>
                </span>
              </Link>

              <button
                type="button"
                onClick={handleLogout}
                className="rounded-xl border border-white/[0.08] px-3 py-2.5 text-xs font-semibold text-[var(--cx-muted)] transition hover:border-red-400/20 hover:bg-red-500/[0.06] hover:text-red-300"
              >
                Logout
              </button>
            </>
          )}

          {loading && (
            <div className="h-10 w-32 animate-pulse rounded-xl bg-white/[0.04]" />
          )}
        </div>

        <button
          type="button"
          onClick={() =>
            setMobileOpen((value) => !value)
          }
          className="ml-auto rounded-xl border border-white/[0.08] bg-white/[0.03] px-3.5 py-2.5 text-xs font-semibold text-[var(--cx-text)] xl:hidden"
          aria-expanded={mobileOpen}
          aria-label="Toggle navigation"
        >
          {mobileOpen ? "Close" : "Menu"}
        </button>
      </div>

      {mobileOpen && (
        <div className="border-t border-[var(--cx-border)] bg-[var(--cx-bg)] xl:hidden">
          <div className="mx-auto max-w-7xl px-4 py-4 sm:px-6">
            {!loading && user && (
              <Link
                href="/profile"
                className="mb-4 flex items-center gap-3 rounded-2xl border border-white/[0.07] bg-white/[0.025] p-3"
              >
                <span className="flex h-10 w-10 items-center justify-center rounded-xl border border-white/[0.08] bg-white/[0.05] text-sm font-bold">
                  {initial}
                </span>

                <span className="min-w-0">
                  <span className="block truncate text-sm font-semibold">
                    {displayName}
                  </span>
                  <span className="block truncate text-xs text-[var(--cx-muted)]">
                    {user.email}
                  </span>
                </span>
              </Link>
            )}

            <nav className="grid gap-1">
              {mainLinks.map((link) => {
                const active = isActive(link.href);

                return (
                  <Link
                    key={link.href}
                    href={link.href}
                    aria-current={active ? "page" : undefined}
                    className={[
                      "rounded-xl border px-4 py-3 text-sm font-semibold transition",
                      active
                        ? "border-white/[0.09] bg-white/[0.07] text-[var(--cx-text)]"
                        : "border-transparent text-[var(--cx-muted)] hover:bg-white/[0.04] hover:text-[var(--cx-text)]",
                    ].join(" ")}
                  >
                    {link.label}
                  </Link>
                );
              })}
            </nav>

            <div className="my-4 h-px bg-[var(--cx-border)]" />

            {!loading && !user && (
              <div className="grid grid-cols-2 gap-2">
                <Link
                  href="/login"
                  className="cx-button cx-button-secondary justify-center"
                >
                  Sign In
                </Link>

                <Link
                  href="/register"
                  className="cx-button cx-button-primary justify-center"
                >
                  Sign Up
                </Link>
              </div>
            )}

            {!loading && user && (
              <button
                type="button"
                onClick={handleLogout}
                className="w-full rounded-xl border border-red-400/15 bg-red-500/[0.04] px-4 py-3 text-sm font-semibold text-red-300 transition hover:bg-red-500/[0.08]"
              >
                Logout
              </button>
            )}
          </div>
        </div>
      )}
    </header>
  );
}
