import type { Metadata } from "next";

export const metadata: Metadata = {
  title: "Refund Policy",
  description: "Refund Policy for CrypticX Lab subscriptions.",
};

export default function RefundPage() {
  return (
    <main className="mx-auto min-h-[70vh] max-w-4xl px-6 py-16 text-zinc-200">
      <h1 className="text-4xl font-bold text-white">Refund Policy</h1>
      <p className="mt-3 text-sm text-zinc-400">Last updated: September 18, 2026</p>

      <div className="mt-10 space-y-8 leading-7">
        <section>
          <h2 className="text-xl font-semibold text-white">1. Subscription Purchases</h2>
          <p className="mt-2">
            CrypticX Lab offers recurring paid subscription plans. Pricing and
            billing frequency are displayed before you complete checkout.
          </p>
        </section>

        <section>
          <h2 className="text-xl font-semibold text-white">2. Refund Requests</h2>
          <p className="mt-2">
            If you believe a charge was made incorrectly, you experienced a
            material service problem, or you have another legitimate reason
            for requesting a refund, contact us as soon as reasonably possible
            through our Contact page with the email associated with the
            purchase and relevant transaction details.
          </p>
        </section>

        <section>
          <h2 className="text-xl font-semibold text-white">3. Review of Requests</h2>
          <p className="mt-2">
            Refund requests are reviewed based on the circumstances of the
            purchase, service usage, technical issues, applicable consumer
            rights, and payment-provider requirements. Nothing in this policy
            limits refund or cancellation rights that cannot legally be
            excluded.
          </p>
        </section>

        <section>
          <h2 className="text-xl font-semibold text-white">4. Renewals and Cancellation</h2>
          <p className="mt-2">
            Canceling a subscription prevents future renewals but does not by
            itself reverse a payment that has already been processed. If you
            need a refund for a processed renewal, submit a refund request for
            review.
          </p>
        </section>

        <section>
          <h2 className="text-xl font-semibold text-white">5. Approved Refunds</h2>
          <p className="mt-2">
            Approved refunds are returned through the original payment method
            and may take additional time to appear depending on the payment
            method, financial institution, and payment processor.
          </p>
        </section>

        <section>
          <h2 className="text-xl font-semibold text-white">6. Contact</h2>
          <p className="mt-2">
            To request a refund or ask a billing question, use the CrypticX Lab
            Contact page.
          </p>
        </section>
      </div>
    </main>
  );
}
