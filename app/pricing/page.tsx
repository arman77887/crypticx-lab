"use client";

import Link from "next/link";
import { useEffect, useMemo, useState } from "react";
import Navbar from "@/components/Navbar";
import {
  initializePaddle,
  type Paddle,
} from "@paddle/paddle-js";
import {
  createBillingCheckout,
  getAccountSubscription,
  getBillingStatus,
  getPlanCatalog,
  getStoredToken,
  type AccountSubscriptionState,
  type PremiumPlan,
} from "@/lib/api";

const paddleClientToken =
  process.env.NEXT_PUBLIC_PADDLE_CLIENT_TOKEN ?? "";

const paddleEnvironment =
  process.env.NEXT_PUBLIC_PADDLE_ENVIRONMENT === "sandbox"
    ? "sandbox"
    : "production";

let paddlePromise: Promise<Paddle | undefined> | null = null;

function getPaddle(): Promise<Paddle | undefined> {
  if (!paddleClientToken) {
    return Promise.reject(
      new Error(
        "Paddle client-side token is not configured.",
      ),
    );
  }

  if (!paddlePromise) {
    paddlePromise = initializePaddle({
      token: paddleClientToken,
      environment: paddleEnvironment,
    });
  }

  return paddlePromise;
}

const planOrder = ["free", "professional", "team"] as const;

const descriptions: Record<string, string> = {
  free:
    "Core security tooling and learning resources for individuals, students, and researchers.",
  professional:
    "Expanded assessment capacity, monitoring, reporting, and priority processing for independent researchers and developers.",
  team:
    "Higher-capacity security workflows with collaboration-oriented capabilities for security teams.",
};

const labels: Record<string, string> = {
  free: "Open Source",
  professional: "For Researchers",
  team: "For Teams",
};

const capabilityLabels: Record<string, string> = {
  core_tools: "Core security tools",
  security_labs: "Security Labs",
  target_management: "Target management",
  assessments: "Security assessments",
  reports: "Security reports",
  monitoring: "Automated monitoring",
  priority_processing: "Priority processing",
  team_workspace: "Team workspace",
  shared_targets: "Shared target management",
  team_rbac: "Team role-based access",
  team_audit_activity: "Team audit activity",
};

const limitLabels: Record<string, string> = {
  targets_total: "Targets",
  assessments_monthly: "Assessments / month",
  reports_monthly: "Reports / month",
  monitoring_policies: "Monitoring policies",
  concurrent_assessments: "Plan concurrency",
};

const principles = [
  {
    number: "01",
    title: "Open core",
    description:
      "The core platform remains useful on the free plan. Paid tiers add capacity, automation, and collaboration.",
  },
  {
    number: "02",
    title: "Transparent limits",
    description:
      "Plan limits are published directly from the same backend entitlement catalog used by quota enforcement.",
  },
  {
    number: "03",
    title: "Safety stays independent",
    description:
      "Paid plans never bypass authorization requirements, SSRF protections, runtime isolation, or platform safety ceilings.",
  },
];

function formatLimit(value: number | null): string {
  return value === null ? "Unlimited" : value.toLocaleString();
}

export default function PricingPage() {
  const [plans, setPlans] = useState<Record<string, PremiumPlan>>({});
  const [subscription, setSubscription] =
    useState<AccountSubscriptionState | null>(null);
  const [effectivePlan, setEffectivePlan] = useState("free");
  const [billingConfigured, setBillingConfigured] = useState(false);
  const [checkoutPlan, setCheckoutPlan] = useState<string | null>(null);
  const [checkoutError, setCheckoutError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const [catalogError, setCatalogError] = useState<string | null>(null);

  useEffect(() => {
    let cancelled = false;

    async function load() {
      try {
        const [catalog, billing] = await Promise.all([
          getPlanCatalog(),
          getBillingStatus(),
        ]);

        if (cancelled) {
          return;
        }

        setPlans(catalog.data);
        setBillingConfigured(
          billing.data.configured &&
            billing.data.checkout_enabled,
        );

        if (getStoredToken()) {
          try {
            const account = await getAccountSubscription();

            if (!cancelled) {
              setSubscription(account.data.subscription);
              setEffectivePlan(
                account.data.effective_entitlements.plan.code,
              );
            }
          } catch {
            /*
             * Pricing remains publicly usable if a stale or invalid
             * local authentication token exists.
             */
          }
        }
      } catch (error) {
        if (!cancelled) {
          setCatalogError(
            error instanceof Error
              ? error.message
              : "Unable to load plan information.",
          );
        }
      } finally {
        if (!cancelled) {
          setLoading(false);
        }
      }
    }

    void load();

    return () => {
      cancelled = true;
    };
  }, []);

  async function openPaddleCheckout(
    planCode: "professional" | "team",
  ) {
    if (!getStoredToken()) {
      window.location.href =
        "/login?next=/pricing";
      return;
    }

    setCheckoutError(null);
    setCheckoutPlan(planCode);

    try {
      const checkout =
        await createBillingCheckout(
          planCode,
        );

      if (
        !checkout.data.transaction_id.startsWith(
          "txn_",
        )
      ) {
        throw new Error(
          "Invalid Paddle transaction returned.",
        );
      }

      const paddle = await getPaddle();

      if (!paddle) {
        throw new Error(
          "Paddle checkout could not initialize.",
        );
      }

      paddle.Checkout.open({
        transactionId:
          checkout.data.transaction_id,
        settings: {
          displayMode: "overlay",
          variant: "one-page",
        },
      });
    } catch (error) {
      setCheckoutError(
        error instanceof Error
          ? error.message
          : "Unable to open checkout.",
      );
    } finally {
      setCheckoutPlan(null);
    }
  }

  const orderedPlans = useMemo(
    () =>
      planOrder
        .map((code) => plans[code])
        .filter((plan): plan is PremiumPlan => Boolean(plan)),
    [plans],
  );

  return (
    <main className="min-h-screen bg-[var(--cx-bg)] text-[var(--cx-text)]">
      <Navbar />

      <section className="mx-auto max-w-7xl px-5 pb-14 pt-16 text-center sm:px-8 lg:px-10 lg:pt-24">
        <div className="cx-raised-sm mx-auto inline-flex items-center gap-2 rounded-full px-4 py-2 text-xs font-semibold uppercase tracking-[0.18em] text-[var(--cx-subtle)]">
          <span className="h-2 w-2 rounded-full bg-[var(--cx-text)]" />
          Plans & Pricing
        </div>

        <h1 className="mx-auto mt-7 max-w-4xl text-4xl font-semibold tracking-tight sm:text-5xl lg:text-6xl">
          Security infrastructure
          <span className="block text-[var(--cx-subtle)]">
            that grows with you.
          </span>
        </h1>

        <p className="mx-auto mt-6 max-w-2xl text-base leading-8 text-[var(--cx-muted)] sm:text-lg">
          Start with a useful free tier. Paid plans expand capacity,
          monitoring, and collaboration without weakening platform safety
          boundaries.
        </p>

        {subscription && (
          <div className="cx-raised-sm mx-auto mt-7 max-w-xl rounded-2xl px-5 py-4 text-sm">
            <span className="text-[var(--cx-muted)]">
              Current effective plan:
            </span>{" "}
            <strong className="capitalize">
              {effectivePlan}
            </strong>

            {subscription.subscribed && subscription.status && (
              <span className="ml-2 text-[var(--cx-subtle)]">
                ({subscription.status.replaceAll("_", " ")})
              </span>
            )}
          </div>
        )}

        {checkoutError && (
          <div className="mx-auto mt-6 max-w-2xl rounded-2xl border border-[var(--cx-border)] p-4 text-center text-sm text-[var(--cx-muted)]">
            {checkoutError}
          </div>
        )}
      </section>

      <section className="mx-auto max-w-7xl px-5 pb-20 sm:px-8 lg:px-10">
        {loading && (
          <div className="cx-card rounded-[30px] p-8 text-center text-sm text-[var(--cx-muted)]">
            Loading plan catalog…
          </div>
        )}

        {catalogError && !loading && (
          <div className="cx-card rounded-[30px] p-8 text-center">
            <p className="font-semibold">
              Plan catalog unavailable
            </p>
            <p className="mt-2 text-sm text-[var(--cx-muted)]">
              {catalogError}
            </p>
          </div>
        )}

        {!loading && !catalogError && (
          <div className="grid gap-6 lg:grid-cols-3">
            {orderedPlans.map((plan) => {
              const current = plan.code === effectivePlan;
              const featured = plan.code === "professional";

              const enabledCapabilities = Object.entries(
                plan.capabilities,
              ).filter(([, enabled]) => enabled);

              return (
                <article
                  key={plan.code}
                  className={`relative flex h-full flex-col rounded-[30px] p-7 sm:p-8 ${
                    featured ? "cx-inset" : "cx-card"
                  }`}
                >
                  {featured && !current && (
                    <div className="absolute -top-3 left-1/2 -translate-x-1/2 rounded-full bg-[var(--cx-text)] px-4 py-1.5 text-[11px] font-bold uppercase tracking-[0.14em] text-[var(--cx-bg)]">
                      Popular
                    </div>
                  )}

                  {current && (
                    <div className="absolute -top-3 left-1/2 -translate-x-1/2 rounded-full bg-[var(--cx-text)] px-4 py-1.5 text-[11px] font-bold uppercase tracking-[0.14em] text-[var(--cx-bg)]">
                      Current Plan
                    </div>
                  )}

                  <div className="flex items-center justify-between gap-4">
                    <span className="text-sm font-semibold">
                      {plan.name}
                    </span>

                    <span className="cx-raised-sm rounded-full px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.12em] text-[var(--cx-subtle)]">
                      {labels[plan.code] ?? plan.code}
                    </span>
                  </div>

                  <p className="mt-5 min-h-[72px] text-sm leading-6 text-[var(--cx-muted)]">
                    {descriptions[plan.code] ??
                      "CrypticX Lab subscription plan."}
                  </p>

                  <div className="mt-8 flex items-end gap-2">
                    <span className="text-4xl font-semibold tracking-tight">
                      ${plan.price_monthly_usd}
                    </span>

                    <span className="pb-1 text-xs text-[var(--cx-subtle)]">
                      {plan.price_monthly_usd === 0
                        ? "/ forever"
                        : "/ month"}
                    </span>
                  </div>

                  <div className="cx-divider my-7" />

                  <h2 className="text-xs font-semibold uppercase tracking-[0.16em] text-[var(--cx-subtle)]">
                    Included capabilities
                  </h2>

                  <ul className="mt-4 space-y-3">
                    {enabledCapabilities.map(([capability]) => (
                      <li
                        key={capability}
                        className="flex items-start gap-3 text-sm text-[var(--cx-muted)]"
                      >
                        <span className="mt-1 flex h-4 w-4 shrink-0 items-center justify-center rounded-full bg-[var(--cx-surface)] text-[10px] font-bold text-[var(--cx-text)] shadow-sm">
                          ✓
                        </span>

                        <span>
                          {capabilityLabels[capability] ??
                            capability.replaceAll("_", " ")}
                        </span>
                      </li>
                    ))}
                  </ul>

                  <div className="cx-divider my-7" />

                  <h2 className="text-xs font-semibold uppercase tracking-[0.16em] text-[var(--cx-subtle)]">
                    Capacity
                  </h2>

                  <dl className="mt-4 space-y-3">
                    {Object.entries(plan.limits).map(
                      ([limit, value]) => (
                        <div
                          key={limit}
                          className="flex items-center justify-between gap-4 text-sm"
                        >
                          <dt className="text-[var(--cx-muted)]">
                            {limitLabels[limit] ??
                              limit.replaceAll("_", " ")}
                          </dt>

                          <dd className="font-semibold">
                            {formatLimit(value)}
                          </dd>
                        </div>
                      ),
                    )}
                  </dl>

                  <div className="mt-auto pt-8">
                    {current ? (
                      <div className="cx-button cx-button-secondary w-full cursor-default">
                        Current Plan
                      </div>
                    ) : plan.code === "free" ? (
                      <Link
                        href="/tools"
                        className="cx-button cx-button-secondary w-full"
                      >
                        Explore Free Tools
                      </Link>
                    ) : billingConfigured ? (
                      <button
                        type="button"
                        disabled={
                          checkoutPlan === plan.code
                        }
                        onClick={() =>
                          void openPaddleCheckout(
                            plan.code as
                              | "professional"
                              | "team",
                          )
                        }
                        className="cx-button cx-button-primary w-full disabled:cursor-wait disabled:opacity-60"
                      >
                        {checkoutPlan === plan.code
                          ? "Opening Checkout…"
                          : "Upgrade with Paddle"}
                      </button>
                    ) : (
                      <>
                        <div className="cx-button cx-button-secondary w-full cursor-not-allowed opacity-70">
                          Billing Setup Pending
                        </div>

                        <p className="mt-3 text-center text-xs leading-5 text-[var(--cx-subtle)]">
                          Online checkout is unavailable until a verified
                          payment provider is configured.
                        </p>
                      </>
                    )}
                  </div>
                </article>
              );
            })}
          </div>
        )}
      </section>

      <section className="border-y border-[var(--cx-border)] bg-[var(--cx-surface)]">
        <div className="mx-auto max-w-7xl px-5 py-20 sm:px-8 lg:px-10">
          <div className="grid gap-12 lg:grid-cols-[0.8fr_1.2fr]">
            <div>
              <p className="text-xs font-semibold uppercase tracking-[0.2em] text-[var(--cx-subtle)]">
                Premium architecture
              </p>

              <h2 className="mt-4 text-3xl font-semibold tracking-tight sm:text-4xl">
                Capacity upgrades, not security bypasses.
              </h2>

              <p className="mt-5 text-sm leading-7 text-[var(--cx-muted)]">
                Subscription entitlements control product capacity and
                advanced workflows. Authorization checks and scanner safety
                controls remain independent.
              </p>
            </div>

            <div className="grid gap-4 sm:grid-cols-3">
              {principles.map((principle) => (
                <div
                  key={principle.number}
                  className="cx-raised rounded-2xl p-6"
                >
                  <span className="text-xs font-bold tracking-[0.2em] text-[var(--cx-subtle)]">
                    {principle.number}
                  </span>

                  <h3 className="mt-5 font-semibold">
                    {principle.title}
                  </h3>

                  <p className="mt-3 text-sm leading-6 text-[var(--cx-muted)]">
                    {principle.description}
                  </p>
                </div>
              ))}
            </div>
          </div>
        </div>
      </section>

      <section className="mx-auto max-w-5xl px-5 py-20 sm:px-8 lg:px-10">
        <div className="cx-raised rounded-[30px] p-8 text-center sm:p-10">
          <p className="text-xs font-semibold uppercase tracking-[0.2em] text-[var(--cx-subtle)]">
            Start free
          </p>

          <h2 className="mt-4 text-2xl font-semibold sm:text-3xl">
            Use the real core platform before upgrading.
          </h2>

          <p className="mx-auto mt-4 max-w-2xl text-sm leading-7 text-[var(--cx-muted)]">
            Free accounts retain core tools, Security Labs, authorized
            assessments, targets, and reports within published plan limits.
          </p>

          <div className="mt-7 flex flex-col justify-center gap-3 sm:flex-row">
            <Link
              href="/tools"
              className="cx-button cx-button-primary"
            >
              Explore Tools
            </Link>

            <Link
              href="/labs"
              className="cx-button cx-button-secondary"
            >
              Visit Security Labs
            </Link>
          </div>
        </div>
      </section>

      <footer className="border-t border-[var(--cx-border)]">
        <div className="mx-auto flex max-w-7xl flex-col gap-3 px-5 py-8 text-sm text-[var(--cx-muted)] sm:px-8 lg:flex-row lg:items-center lg:justify-between lg:px-10">
          <p>© 2026 CrypticX Lab. Open security infrastructure.</p>
          <p>Transparent plans. Independent safety boundaries.</p>
        </div>
      </footer>
    </main>
  );
}
