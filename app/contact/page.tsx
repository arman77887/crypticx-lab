import Link from "next/link";
import Navbar from "@/components/Navbar";

const contactTypes = [
  {
    title: "General Inquiry",
    description:
      "Questions about CrypticX Lab, the platform, documentation, or community.",
  },
  {
    title: "Security Research",
    description:
      "Responsible security research, vulnerability disclosure, and security-related communication.",
  },
  {
    title: "Partnership",
    description:
      "Organizations and teams interested in collaboration, integrations, or partnerships.",
  },
  {
    title: "Technical Support",
    description:
      "Help with platform usage, assessment workflows, or technical documentation.",
  },
];

const channels = [
  {
    label: "Email",
    value: "hello@crypticxlab.org",
    note: "General questions and collaboration",
  },
  {
    label: "Security",
    value: "security@crypticxlab.org",
    note: "Responsible vulnerability disclosure",
  },
  {
    label: "Community",
    value: "Open Source",
    note: "Contribute, discuss, and learn",
  },
];

export default function ContactPage() {
  return (
    <main className="min-h-screen bg-[var(--cx-bg)] text-[var(--cx-text)]">
      <Navbar />

      <section className="mx-auto max-w-7xl px-5 pb-16 pt-16 sm:px-8 lg:px-10 lg:pt-24">
        <div className="grid gap-12 lg:grid-cols-[1fr_0.9fr] lg:items-end">
          <div>
            <div className="cx-raised-sm inline-flex items-center gap-2 rounded-full px-4 py-2 text-xs font-semibold uppercase tracking-[0.18em] text-[var(--cx-subtle)]">
              <span className="h-2 w-2 rounded-full bg-[var(--cx-text)]" />
              Contact CrypticX
            </div>

            <h1 className="mt-7 text-4xl font-semibold tracking-tight sm:text-5xl lg:text-6xl">
              Let&apos;s build a more
              <span className="block text-[var(--cx-subtle)]">
                secure open web.
              </span>
            </h1>

            <p className="mt-6 max-w-2xl text-base leading-8 text-[var(--cx-muted)] sm:text-lg">
              Have a question, security concern, partnership idea, or
              technical issue? Choose the appropriate channel and reach out to
              the CrypticX Lab team.
            </p>
          </div>

          <div className="cx-inset rounded-[28px] p-7">
            <p className="text-xs font-semibold uppercase tracking-[0.18em] text-[var(--cx-subtle)]">
              Response principle
            </p>

            <p className="mt-5 text-xl font-semibold leading-8">
              Clear communication.
              <br />
              Responsible disclosure.
              <br />
              Practical security.
            </p>

            <div className="cx-divider my-6" />

            <p className="text-sm leading-6 text-[var(--cx-muted)]">
              Security reports should contain enough technical information to
              reproduce and understand the issue without including unnecessary
              sensitive data.
            </p>
          </div>
        </div>
      </section>

      <section className="mx-auto max-w-7xl px-5 pb-20 sm:px-8 lg:px-10">
        <div className="grid gap-5 md:grid-cols-2">
          {contactTypes.map((type, index) => (
            <article
              key={type.title}
              className="cx-card rounded-[26px] p-7"
            >
              <span className="text-xs font-bold tracking-[0.2em] text-[var(--cx-subtle)]">
                0{index + 1}
              </span>

              <h2 className="mt-5 text-xl font-semibold">{type.title}</h2>

              <p className="mt-3 max-w-xl text-sm leading-7 text-[var(--cx-muted)]">
                {type.description}
              </p>

              <Link
                href="#contact-form"
                className="mt-6 inline-flex text-sm font-semibold"
              >
                Contact us
                <span className="ml-2">→</span>
              </Link>
            </article>
          ))}
        </div>
      </section>

      <section
        id="contact-form"
        className="border-y border-[var(--cx-border)] bg-[var(--cx-surface)]"
      >
        <div className="mx-auto max-w-7xl px-5 py-20 sm:px-8 lg:px-10">
          <div className="grid gap-12 lg:grid-cols-[0.8fr_1.2fr]">
            <div>
              <p className="text-xs font-semibold uppercase tracking-[0.2em] text-[var(--cx-subtle)]">
                Send a message
              </p>

              <h2 className="mt-4 text-3xl font-semibold tracking-tight sm:text-4xl">
                Tell us what you need.
              </h2>

              <p className="mt-5 text-sm leading-7 text-[var(--cx-muted)]">
                This interface is currently prepared for backend integration.
                Once the API is connected, submissions will be validated,
                stored securely, and routed to the appropriate team.
              </p>

              <div className="mt-8 space-y-3">
                {channels.map((channel) => (
                  <div
                    key={channel.label}
                    className="cx-raised rounded-2xl p-5"
                  >
                    <p className="text-xs font-semibold uppercase tracking-[0.14em] text-[var(--cx-subtle)]">
                      {channel.label}
                    </p>

                    <p className="mt-2 font-semibold">{channel.value}</p>

                    <p className="mt-1 text-xs text-[var(--cx-muted)]">
                      {channel.note}
                    </p>
                  </div>
                ))}
              </div>
            </div>

            <form
              className="cx-card rounded-[30px] p-7 sm:p-9"
            >
              <div className="grid gap-6 sm:grid-cols-2">
                <div>
                  <label
                    htmlFor="name"
                    className="mb-2 block text-sm font-semibold"
                  >
                    Name
                  </label>

                  <input
                    id="name"
                    type="text"
                    placeholder="Your name"
                    className="cx-input w-full"
                  />
                </div>

                <div>
                  <label
                    htmlFor="email"
                    className="mb-2 block text-sm font-semibold"
                  >
                    Email
                  </label>

                  <input
                    id="email"
                    type="email"
                    placeholder="you@example.com"
                    className="cx-input w-full"
                  />
                </div>
              </div>

              <div className="mt-6">
                <label
                  htmlFor="category"
                  className="mb-2 block text-sm font-semibold"
                >
                  Inquiry type
                </label>

                <select
                  id="category"
                  defaultValue=""
                  className="cx-input w-full"
                >
                  <option value="" disabled>
                    Select a category
                  </option>
                  <option value="general">General Inquiry</option>
                  <option value="security">Security Research</option>
                  <option value="partnership">Partnership</option>
                  <option value="support">Technical Support</option>
                </select>
              </div>

              <div className="mt-6">
                <label
                  htmlFor="subject"
                  className="mb-2 block text-sm font-semibold"
                >
                  Subject
                </label>

                <input
                  id="subject"
                  type="text"
                  placeholder="How can we help?"
                  className="cx-input w-full"
                />
              </div>

              <div className="mt-6">
                <label
                  htmlFor="message"
                  className="mb-2 block text-sm font-semibold"
                >
                  Message
                </label>

                <textarea
                  id="message"
                  rows={7}
                  placeholder="Write your message..."
                  className="cx-input min-h-[170px] w-full resize-y"
                />
              </div>

              <div className="cx-inset-sm mt-6 rounded-2xl p-4">
                <p className="text-xs leading-5 text-[var(--cx-muted)]">
                  Do not include passwords, private keys, authentication
                  tokens, or other sensitive credentials in this form.
                </p>
              </div>

              <button
                type="submit"
                className="cx-button cx-button-primary mt-7 w-full"
              >
                Send Message
              </button>
            </form>
          </div>
        </div>
      </section>

      <section className="mx-auto max-w-5xl px-5 py-20 sm:px-8 lg:px-10">
        <div className="cx-raised rounded-[30px] p-8 text-center sm:p-10">
          <p className="text-xs font-semibold uppercase tracking-[0.2em] text-[var(--cx-subtle)]">
            Security disclosure
          </p>

          <h2 className="mt-4 text-2xl font-semibold sm:text-3xl">
            Found a security issue?
          </h2>

          <p className="mx-auto mt-4 max-w-2xl text-sm leading-7 text-[var(--cx-muted)]">
            Please use the dedicated security channel for responsible
            vulnerability disclosure. Include affected component, impact,
            reproduction details, and remediation suggestions where possible.
          </p>

          <div className="mt-7 flex flex-col justify-center gap-3 sm:flex-row">
            <Link href="mailto:security@crypticxlab.org" className="cx-button cx-button-primary">
              Security Disclosure
            </Link>

            <Link href="/docs" className="cx-button cx-button-secondary">
              Read Documentation
            </Link>
          </div>
        </div>
      </section>

      <footer className="border-t border-[var(--cx-border)]">
        <div className="mx-auto flex max-w-7xl flex-col gap-3 px-5 py-8 text-sm text-[var(--cx-muted)] sm:px-8 lg:flex-row lg:items-center lg:justify-between lg:px-10">
          <p>© 2026 CrypticX Lab. Open security infrastructure.</p>
          <p>Responsible communication and responsible research.</p>
        </div>
      </footer>
    </main>
  );
}
