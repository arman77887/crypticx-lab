import type { Metadata } from "next";

export const metadata: Metadata = {
  title: "Terms of Service",
  description: "Terms of Service for CrypticX Lab.",
};

export default function TermsPage() {
  return (
    <main className="mx-auto min-h-[70vh] max-w-4xl px-6 py-16 text-zinc-200">
      <h1 className="text-4xl font-bold text-white">Terms of Service</h1>
      <p className="mt-3 text-sm text-zinc-400">Last updated: September 18, 2026</p>

      <div className="mt-10 space-y-8 leading-7">
        <section>
          <h2 className="text-xl font-semibold text-white">1. Acceptance of Terms</h2>
          <p className="mt-2">
            By accessing or using CrypticX Lab, you agree to these Terms of
            Service. If you do not agree, you must not use the service.
          </p>
        </section>

        <section>
          <h2 className="text-xl font-semibold text-white">2. Service</h2>
          <p className="mt-2">
            CrypticX Lab provides cybersecurity assessment, monitoring,
            intelligence, reporting, and related security tools for legitimate
            and authorized security purposes.
          </p>
        </section>

        <section>
          <h2 className="text-xl font-semibold text-white">3. Authorized Use</h2>
          <p className="mt-2">
            You may only scan, assess, test, or monitor systems, domains,
            applications, APIs, networks, or other assets that you own or for
            which you have explicit authorization. You are responsible for
            obtaining all permissions required before starting an assessment.
          </p>
          <p className="mt-2">
            You must not use CrypticX Lab for unauthorized access, disruption,
            credential theft, malware distribution, unlawful surveillance,
            exploitation, or any activity that violates applicable law or the
            rights of another person or organization.
          </p>
        </section>

        <section>
          <h2 className="text-xl font-semibold text-white">4. Accounts</h2>
          <p className="mt-2">
            You are responsible for maintaining the security of your account,
            authentication credentials, and authorized devices. Information
            provided during registration must be accurate and kept reasonably
            up to date.
          </p>
        </section>

        <section>
          <h2 className="text-xl font-semibold text-white">5. Paid Plans</h2>
          <p className="mt-2">
            Paid subscriptions provide access to features described on our
            pricing page. Subscription prices and billing intervals are shown
            before checkout. Payments are processed by our authorized payment
            provider. Applicable taxes may be calculated during checkout.
          </p>
        </section>

        <section>
          <h2 className="text-xl font-semibold text-white">6. Subscription Cancellation</h2>
          <p className="mt-2">
            You may cancel a recurring subscription to prevent future renewal.
            Unless otherwise required by law, cancellation does not
            automatically refund charges already processed. Refund requests
            are handled under our Refund Policy.
          </p>
        </section>

        <section>
          <h2 className="text-xl font-semibold text-white">7. Availability and Changes</h2>
          <p className="mt-2">
            We may maintain, improve, modify, suspend, or discontinue features
            where reasonably necessary. We do not guarantee uninterrupted or
            error-free operation.
          </p>
        </section>

        <section>
          <h2 className="text-xl font-semibold text-white">8. Security Results</h2>
          <p className="mt-2">
            Security findings and automated assessments are informational and
            may contain false positives, false negatives, or incomplete
            results. They should be independently reviewed before important
            security decisions are made.
          </p>
        </section>

        <section>
          <h2 className="text-xl font-semibold text-white">9. Limitation of Liability</h2>
          <p className="mt-2">
            To the extent permitted by applicable law, CrypticX Lab is not
            liable for indirect, incidental, special, or consequential losses
            arising from use of the service or reliance on automated security
            results.
          </p>
        </section>

        <section>
          <h2 className="text-xl font-semibold text-white">10. Contact</h2>
          <p className="mt-2">
            Questions about these terms may be submitted through the CrypticX
            Lab Contact page.
          </p>
        </section>
      </div>
    </main>
  );
}
