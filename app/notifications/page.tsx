"use client";

import { useState } from "react";
import Link from "next/link";

type Notification = {
  id: number;
  title: string;
  message: string;
  category: "Security" | "Assessment" | "Account" | "System";
  time: string;
  unread: boolean;
};

const initialNotifications: Notification[] = [
  {
    id: 1,
    title: "High-severity finding detected",
    message:
      "A high-severity security finding was detected in the latest assessment.",
    category: "Security",
    time: "10 minutes ago",
    unread: true,
  },
  {
    id: 2,
    title: "Assessment completed",
    message:
      "The Web Security assessment for example.com has completed successfully.",
    category: "Assessment",
    time: "1 hour ago",
    unread: true,
  },
  {
    id: 3,
    title: "New device trusted",
    message:
      "A new device was added to your trusted-device list.",
    category: "Account",
    time: "Yesterday",
    unread: false,
  },
  {
    id: 4,
    title: "Report generation completed",
    message:
      "Your API Security Assessment report is ready for review.",
    category: "Assessment",
    time: "Yesterday",
    unread: false,
  },
  {
    id: 5,
    title: "Security platform update",
    message:
      "CrypticX Lab security and infrastructure components have been updated.",
    category: "System",
    time: "3 days ago",
    unread: false,
  },
];

export default function NotificationsPage() {
  const [notifications, setNotifications] =
    useState(initialNotifications);
  const [filter, setFilter] = useState<
    "All" | "Unread" | Notification["category"]
  >("All");

  const filteredNotifications = notifications.filter((item) => {
    if (filter === "Unread") return item.unread;
    if (filter === "All") return true;
    return item.category === filter;
  });

  const unreadCount = notifications.filter(
    (item) => item.unread
  ).length;

  const markAllRead = () => {
    setNotifications((items) =>
      items.map((item) => ({
        ...item,
        unread: false,
      }))
    );
  };

  const markRead = (id: number) => {
    setNotifications((items) =>
      items.map((item) =>
        item.id === id ? { ...item, unread: false } : item
      )
    );
  };

  return (
    <main className="min-h-screen bg-[var(--cx-bg)] text-[var(--cx-text)]">

      <section className="mx-auto max-w-6xl px-5 pb-20 pt-28 sm:px-8">
        <div className="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
          <div>
            <div className="mb-3 inline-flex rounded-full px-4 py-2 text-xs font-semibold tracking-wide cx-inset-sm">
              SECURITY CENTER
            </div>

            <h1 className="text-4xl font-bold tracking-tight sm:text-5xl">
              Notifications
            </h1>

            <p className="mt-4 max-w-2xl text-base leading-7 text-[var(--cx-muted)]">
              Review security alerts, assessment events, account activity,
              and important CrypticX Lab system notifications.
            </p>
          </div>

          <button
            type="button"
            onClick={markAllRead}
            className="cx-button cx-button-secondary rounded-2xl px-5 py-3 text-sm font-semibold"
          >
            Mark all as read
          </button>
        </div>

        <div className="mt-10 grid gap-5 sm:grid-cols-3">
          <div className="cx-card rounded-3xl p-6">
            <div className="text-3xl font-bold">
              {notifications.length}
            </div>

            <div className="mt-2 text-sm text-[var(--cx-muted)]">
              Total Notifications
            </div>
          </div>

          <div className="cx-card rounded-3xl p-6">
            <div className="text-3xl font-bold">{unreadCount}</div>

            <div className="mt-2 text-sm text-[var(--cx-muted)]">
              Unread
            </div>
          </div>

          <div className="cx-card rounded-3xl p-6">
            <div className="text-3xl font-bold">24/7</div>

            <div className="mt-2 text-sm text-[var(--cx-muted)]">
              Security Monitoring
            </div>
          </div>
        </div>

        <div className="mt-8 cx-card rounded-[30px] p-5 sm:p-7">
          <div className="flex flex-wrap gap-2">
            {(
              [
                "All",
                "Unread",
                "Security",
                "Assessment",
                "Account",
                "System",
              ] as const
            ).map((item) => (
              <button
                key={item}
                type="button"
                onClick={() => setFilter(item)}
                className={
                  filter === item
                    ? "cx-button cx-button-primary rounded-xl px-4 py-2 text-sm font-semibold"
                    : "cx-button cx-button-secondary rounded-xl px-4 py-2 text-sm font-semibold"
                }
              >
                {item}
              </button>
            ))}
          </div>
        </div>

        <section className="mt-6 cx-card rounded-[30px] p-5 sm:p-7">
          <div className="mb-5">
            <h2 className="text-xl font-bold">
              Activity & Alerts
            </h2>

            <p className="mt-1 text-sm text-[var(--cx-muted)]">
              Important events from your security workspace.
            </p>
          </div>

          <div className="space-y-3">
            {filteredNotifications.map((item) => (
              <article
                key={item.id}
                className={
                  item.unread
                    ? "cx-inset-sm rounded-2xl border border-[var(--cx-border)] p-5"
                    : "cx-inset-sm rounded-2xl p-5"
                }
              >
                <div className="flex gap-4">
                  <div className="cx-raised-sm flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl text-sm font-bold">
                    {item.category === "Security"
                      ? "!"
                      : item.category === "Assessment"
                        ? "✓"
                        : item.category === "Account"
                          ? "◉"
                          : "•"}
                  </div>

                  <div className="min-w-0 flex-1">
                    <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                      <div>
                        <div className="flex flex-wrap items-center gap-2">
                          <h3 className="font-semibold">
                            {item.title}
                          </h3>

                          {item.unread && (
                            <span className="rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide cx-inset-sm">
                              New
                            </span>
                          )}
                        </div>

                        <p className="mt-2 text-sm leading-6 text-[var(--cx-muted)]">
                          {item.message}
                        </p>
                      </div>

                      <span className="shrink-0 text-xs text-[var(--cx-muted)]">
                        {item.time}
                      </span>
                    </div>

                    <div className="mt-4 flex flex-wrap items-center gap-2">
                      <span className="rounded-full px-3 py-1 text-xs font-semibold cx-inset-sm">
                        {item.category}
                      </span>

                      {item.unread && (
                        <button
                          type="button"
                          onClick={() => markRead(item.id)}
                          className="cx-button cx-button-secondary rounded-xl px-3 py-1.5 text-xs font-semibold"
                        >
                          Mark as read
                        </button>
                      )}
                    </div>
                  </div>
                </div>
              </article>
            ))}
          </div>

          {filteredNotifications.length === 0 && (
            <div className="cx-inset-sm rounded-2xl p-8 text-center">
              <h3 className="font-semibold">
                No notifications
              </h3>

              <p className="mt-2 text-sm text-[var(--cx-muted)]">
                There are no notifications matching this filter.
              </p>
            </div>
          )}
        </section>

        <section className="mt-8 grid gap-6 lg:grid-cols-2">
          <div className="cx-card rounded-[30px] p-6 sm:p-7">
            <h2 className="text-xl font-bold">
              Notification Preferences
            </h2>

            <p className="mt-2 text-sm leading-6 text-[var(--cx-muted)]">
              Configure which security and platform events should generate
              notifications.
            </p>

            <Link
              href="/settings"
              className="cx-button cx-button-secondary mt-5 inline-flex rounded-xl px-4 py-2 text-sm font-semibold"
            >
              Open Settings →
            </Link>
          </div>

          <div className="cx-card rounded-[30px] p-6 sm:p-7">
            <h2 className="text-xl font-bold">
              Security Alerts
            </h2>

            <p className="mt-2 text-sm leading-6 text-[var(--cx-muted)]">
              Critical security events should remain visible until reviewed.
              Production alerts will be generated by verified backend events
              and recorded in the security audit trail.
            </p>

            <Link
              href="/findings"
              className="cx-button cx-button-secondary mt-5 inline-flex rounded-xl px-4 py-2 text-sm font-semibold"
            >
              Review Findings →
            </Link>
          </div>
        </section>

        <div className="mt-8 cx-card rounded-[30px] p-6">
          <div className="flex gap-4">
            <div className="cx-inset-sm flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl">
              ✓
            </div>

            <div>
              <h2 className="font-bold">
                Audit-ready notifications
              </h2>

              <p className="mt-1 text-sm leading-6 text-[var(--cx-muted)]">
                Production notifications will be connected to authenticated
                backend events, severity rules, delivery controls, audit
                logging, and user notification preferences.
              </p>
            </div>
          </div>
        </div>
      </section>
    </main>
  );
}
