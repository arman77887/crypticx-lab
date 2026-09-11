"use client";

import Link from "next/link";
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

export default function Navbar() {
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

    loadUser();

    return () => {
      mounted = false;
    };
  }, []);

  const roleNames =
    user?.roles?.map((role) => role.name.toLowerCase()) ?? [];

  const isAdmin =
    roleNames.includes("owner") ||
    roleNames.includes("administrator");

  async function handleLogout() {
    try {
      await logout();
    } catch {
      // Local auth state will still be cleared by logout helper.
    } finally {
      setUser(null);
      window.location.href = "/";
    }
  }

  const publicLinks = [
    { href: "/tools", label: "Tools" },
    { href: "/scanner", label: "Scanner" },
    { href: "/labs", label: "Security Labs" },
    { href: "/docs", label: "Docs" },
    { href: "/pricing", label: "Pricing" },
  ];

  const authenticatedLinks = isAdmin
    ? [
        { href: "/admin", label: "Admin Console" },
        { href: "/scanner", label: "Scanner" },
        { href: "/findings", label: "Findings" },
        { href: "/monitoring", label: "Monitoring" },
        { href: "/docs", label: "Docs" },
      ]
    : [
        { href: "/dashboard", label: "Dashboard" },
        { href: "/scanner", label: "Scanner" },
        { href: "/findings", label: "Findings" },
        { href: "/monitoring", label: "Monitoring" },
        { href: "/docs", label: "Docs" },
      ];

  const mainLinks = user ? authenticatedLinks : publicLinks;

  return (
    <header className="sticky top-0 z-50 border-b border-[var(--cx-border)] bg-[var(--cx-bg)]/90 backdrop-blur-xl">
      <div className="mx-auto flex min-h-[72px] max-w-7xl items-center justify-between gap-6 px-4 sm:px-6 lg:px-8">
        <Link
          href="/"
          className="flex items-center gap-3 font-semibold tracking-tight text-[var(--cx-text)]"
        >
          <span className="flex h-10 w-10 items-center justify-center rounded-2xl bg-[var(--cx-dark)] text-sm font-black text-white shadow-lg">
            CX
          </span>

          <div className="leading-tight">
            <div className="text-base font-bold">CrypticX Lab</div>
            <div className="text-[10px] uppercase tracking-[0.2em] text-[var(--cx-muted)]">
              Security Intelligence
            </div>
          </div>
        </Link>

        <nav className="hidden items-center gap-1 lg:flex">
          {mainLinks.map((link) => (
            <Link
              key={link.href}
              href={link.href}
              className="rounded-xl px-3 py-2 text-sm font-medium text-[var(--cx-muted)] transition hover:bg-white/[0.04] hover:text-[var(--cx-text)]"
            >
              {link.label}
            </Link>
          ))}
        </nav>

        <div className="hidden items-center gap-2 lg:flex">
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
            <button
              type="button"
              onClick={handleLogout}
              className="cx-button cx-button-secondary text-sm"
            >
              Logout
            </button>
          )}

          {loading && (
            <div className="h-10 w-36 animate-pulse rounded-xl bg-white/[0.04]" />
          )}
        </div>

        <button
          type="button"
          onClick={() => setMobileOpen((value) => !value)}
          className="cx-button cx-button-secondary px-3 lg:hidden"
          aria-label="Toggle navigation"
        >
          {mobileOpen ? "Close" : "Menu"}
        </button>
      </div>

      {mobileOpen && (
        <div className="fixed inset-x-0 top-[72px] z-[9999] max-h-[calc(100vh-72px)] overflow-y-auto border-t border-[var(--cx-border)] bg-[var(--cx-bg)] px-4 py-4 shadow-xl lg:hidden">
          <div className="mx-auto flex max-w-7xl flex-col gap-2">
            {mainLinks.map((link) => (
              <Link
                key={link.href}
                href={link.href}
                onClick={() => setMobileOpen(false)}
                className="rounded-xl px-4 py-3 text-sm font-medium text-[var(--cx-muted)] hover:bg-white/[0.04] hover:text-[var(--cx-text)]"
              >
                {link.label}
              </Link>
            ))}

            <div className="my-2 h-px bg-[var(--cx-border)]" />

            {!loading && !user && (
              <>
                <Link
                  href="/login"
                  onClick={() => setMobileOpen(false)}
                  className="cx-button cx-button-secondary justify-center"
                >
                  Sign In
                </Link>

                <Link
                  href="/register"
                  onClick={() => setMobileOpen(false)}
                  className="cx-button cx-button-primary justify-center"
                >
                  Sign Up
                </Link>
              </>
            )}

            {!loading && user && (
              <button
                type="button"
                onClick={handleLogout}
                className="cx-button cx-button-secondary justify-center"
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
