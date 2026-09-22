import type { Metadata } from "next";

export const metadata: Metadata = {
  title: "Privacy Policy",
  description: "Privacy Policy for CrypticX Lab.",
};

export default function PrivacyPage() {
  return (
    <main className="mx-auto min-h-[70vh] max-w-4xl px-6 py-16 text-zinc-200">
      <h1 className="text-4xl font-bold text-white">Privacy Policy</h1>
      <p className="mt-3 text-sm text-zinc-400">Last updated: September 18, 2026</p>

      <div className="mt-10 space-y-8 leading-7">
        <section>
          <h2 className="text-xl font-semibold text-white">1. Information We Collect</h2>
          <p className="mt-2">
            We may collect account information such as your name and email
            address, authentication and security information, subscription
            status, support communications, and information necessary to
            operate security assessments you request.
          </p>
        </section>

        <section>
          <h2 className="text-xl font-semibold text-white">2. Assessment Data</h2>
          <p className="mt-2">
            When you use security tools, we may process targets you submit,
            assessment configuration, technical observations, findings,
            evidence, risk information, and generated reports in order to
            provide the requested service.
          </p>
        </section>

        <section>
          <h2 className="text-xl font-semibold text-white">3. How We Use Information</h2>
          <p className="mt-2">
            Information is used to provide and secure CrypticX Lab, authenticate
            users, perform requested assessments, generate reports, manage
            subscriptions, prevent abuse, provide support, and improve service
            reliability.
          </p>
        </section>

        <section>
          <h2 className="text-xl font-semibold text-white">4. Payments</h2>
          <p className="mt-2">
            Payments are processed by Polar. Payment information submitted at
            checkout is handled by the payment provider according to its own
            privacy and security practices. CrypticX Lab does not need to store
            full payment card details to provide subscriptions.
          </p>
        </section>

        <section>
          <h2 className="text-xl font-semibold text-white">5. Data Sharing</h2>
          <p className="mt-2">
            We may use service providers where necessary to operate the
            platform, process payments, deliver email, host infrastructure, or
            protect the service. We do not sell personal information.
          </p>
        </section>

        <section>
          <h2 className="text-xl font-semibold text-white">6. Security and Retention</h2>
          <p className="mt-2">
            We use technical and organizational safeguards designed to protect
            information. Data is retained for as long as reasonably necessary
            to provide the service, meet security and operational needs, and
            satisfy applicable legal obligations.
          </p>
        </section>

        <section>
          <h2 className="text-xl font-semibold text-white">7. Your Requests</h2>
          <p className="mt-2">
            You may contact us regarding access, correction, deletion, or other
            privacy requests. Some information may need to be retained where
            required for security, fraud prevention, billing, or legal
            obligations.
          </p>
        </section>

        <section>
          <h2 className="text-xl font-semibold text-white">8. Policy Updates</h2>
          <p className="mt-2">
            We may update this Privacy Policy when our services or legal
            obligations change. The latest version will be published on this
            page with its updated date.
          </p>
        </section>

        <section>
          <h2 className="text-xl font-semibold text-white">9. Contact</h2>
          <p className="mt-2">
            Privacy questions or requests may be submitted through the
            CrypticX Lab Contact page.
          </p>
        </section>
      </div>
    </main>
  );
}
