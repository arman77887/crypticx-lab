"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";

const menuGroups = [
  {
    label: "Overview",
    items: [
      { label: "Dashboard", href: "/admin", icon: "⌂" },
    ],
  },
  {
    label: "Platform",
    items: [
      { label: "Users", href: "/admin/users", icon: "♙" },
      { label: "Targets", href: "/admin/targets", icon: "◎" },
      { label: "Assessments", href: "/admin/scans", icon: "⌁" },
      { label: "Findings", href: "/admin/findings", icon: "!" },
      { label: "Reports", href: "/admin/reports", icon: "▤" },
    ],
  },
  {
    label: "Operations",
    items: [
      { label: "Monitoring", href: "/admin/monitoring", icon: "◉" },
      { label: "Workers", href: "/admin/workers", icon: "◈" },
      { label: "Audit Logs", href: "/admin/audit-logs", icon: "≡" },
    ],
  },
  {
    label: "Security",
    items: [
      { label: "Security", href: "/admin/security", icon: "◆" },
      { label: "Settings", href: "/admin/settings", icon: "⚙" },
    ],
  },
];

export default function AdminSidebar() {
  const pathname = usePathname();

  return (
    <aside className="hidden w-72 shrink-0 lg:block">
      <div className="sticky top-24 cx-card rounded-[30px] p-5">
        <div className="mb-6 px-3">
          <div className="text-xs font-semibold tracking-[0.2em] text-[var(--cx-muted)]">
            CRYPTICX LAB
          </div>

          <div className="mt-2 text-lg font-bold">
            Security Operations
          </div>
        </div>

        <nav className="space-y-6">
          {menuGroups.map((group) => (
            <div key={group.label}>
              <div className="mb-2 px-3 text-[10px] font-bold uppercase tracking-[0.2em] text-[var(--cx-muted)]">
                {group.label}
              </div>

              <div className="space-y-1">
                {group.items.map((item) => {
                  const active =
                    item.href === "/admin"
                      ? pathname === "/admin"
                      : pathname.startsWith(item.href);

                  return (
                    <Link
                      key={item.href}
                      href={item.href}
                      className={
                        active
                          ? "flex items-center gap-3 rounded-2xl px-3 py-3 text-sm font-semibold cx-raised"
                          : "flex items-center gap-3 rounded-2xl px-3 py-3 text-sm font-medium text-[var(--cx-muted)] transition hover:text-[var(--cx-text)] cx-inset-sm"
                      }
                    >
                      <span className="flex h-8 w-8 items-center justify-center rounded-xl cx-inset-sm text-sm">
                        {item.icon}
                      </span>

                      <span>{item.label}</span>
                    </Link>
                  );
                })}
              </div>
            </div>
          ))}
        </nav>

        <div className="mt-7 border-t border-[var(--cx-border)] pt-5">
          <div className="cx-inset-sm rounded-2xl p-4">
            <div className="flex items-center gap-3">
              <div className="flex h-10 w-10 items-center justify-center rounded-full cx-raised-sm text-sm font-bold">
                A
              </div>

              <div className="min-w-0">
                <div className="truncate text-sm font-semibold">
                  Administrator
                </div>

                <div className="text-xs text-[var(--cx-muted)]">
                  Owner
                </div>
              </div>
            </div>

            <div className="mt-3 flex items-center gap-2 text-[11px] text-[var(--cx-muted)]">
              <span className="h-2 w-2 rounded-full bg-current" />
              Secure session
            </div>
          </div>
        </div>
      </div>
    </aside>
  );
}
