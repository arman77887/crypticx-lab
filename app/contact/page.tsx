"use client";

import Link from "next/link";
import Navbar from "@/components/Navbar";
import { FormEvent, useState } from "react";
import { apiRequest } from "@/lib/api";

type ContactCategory =
  | "general"
  | "security"
  | "partnership"
  | "support";

type ContactResponse = {
  success: boolean;
  message: string;
};

const contactTypes: Array<{
  category: ContactCategory;
  title: string;
  description: string;
}> = [
  {
    category: "general",
    title: "General Inquiry",
    description:
      "Questions about CrypticX Lab, the platform, documentation, or community.",
  },
  {
    category: "security",
    title: "Security Research",
    description:
      "Responsible security research, vulnerability disclosure, and security-related communication.",
  },
  {
    category: "partnership",
    title: "Partnership",
    description:
      "Organizations and teams interested in collaboration, integrations, or partnerships.",
  },
  {
    category: "support",
    title: "Technical Support",
    description:
      "Help with platform usage, assessment workflows, or technical documentation.",
  },
];

const channels = [
  {
    label: "General",
    value: "Secure Contact",
    note: "Questions, collaboration, and platform inquiries",
  },
  {
    label: "Security",
    value: "Private Disclosure",
    note: "Responsible vulnerability and security reports",
  },
  {
    label: "Support",
    value: "Technical Assistance",
    note: "Platform usage and assessment support",
  },
];

export default function ContactPage() {
  const [form, setForm] = useState({
    name: "",
    email: "",
    category: "",
    subject: "",
    message: "",
    website: "",
  });

  const [sending, setSending] = useState(false);
  const [success, setSuccess] = useState("");
  const [error, setError] = useState("");

  function selectCategory(category: ContactCategory) {
    setForm((current) => ({
      ...current,
      category,
    }));

    setTimeout(() => {
      document
        .getElementById("contact-form")
        ?.scrollIntoView({
          behavior: "smooth",
          block: "start",
        });
    }, 0);
  }

  async function handleSubmit(
    event: FormEvent<HTMLFormElement>,
  ) {
    event.preventDefault();

    setSuccess("");
    setError("");
    setSending(true);

    try {
      const response = await apiRequest<ContactResponse>(
        "/contact",
        {
          method: "POST",
          body: JSON.stringify(form),
        },
      );

      setSuccess(
        response.message ||
          "Your message has been sent successfully.",
      );

      setForm({
        name: "",
        email: "",
        category: "",
        subject: "",
        message: "",
        website: "",
      });
    } catch (requestError) {
      setError(
        requestError instanceof Error
          ? requestError.message
          : "Message could not be sent. Please try again.",
      );
    } finally {
      setSending(false);
    }
  }

  return (
    <>
      <Navbar />
      <main className="min-h-screen bg-[var(--cx-bg)] text-[var(--cx-text)]">

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
              Have a question, security concern, partnership
              idea, or technical issue? Send it securely through
              CrypticX Lab.
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
              Messages are routed privately through our secure
              backend. Public receiver addresses are not exposed.
            </p>
          </div>
        </div>
      </section>

      <section className="mx-auto max-w-7xl px-5 pb-20 sm:px-8 lg:px-10">
        <div className="grid gap-5 md:grid-cols-2">
          {contactTypes.map((type, index) => (
            <article
              key={type.category}
              className="cx-card rounded-[26px] p-7"
            >
              <span className="text-xs font-bold tracking-[0.2em] text-[var(--cx-subtle)]">
                0{index + 1}
              </span>

              <h2 className="mt-5 text-xl font-semibold">
                {type.title}
              </h2>

              <p className="mt-3 max-w-xl text-sm leading-7 text-[var(--cx-muted)]">
                {type.description}
              </p>

              <button
                type="button"
                onClick={() =>
                  selectCategory(type.category)
                }
                className="mt-6 inline-flex text-sm font-semibold"
              >
                Contact us
                <span className="ml-2">→</span>
              </button>
            </article>
          ))}
        </div>
      </section>

      <section
        id="contact-form"
        className="scroll-mt-24 border-y border-[var(--cx-border)] bg-[var(--cx-surface)]"
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
                Your message is submitted directly to the
                CrypticX Lab backend and privately delivered to
                the appropriate inbox.
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

                    <p className="mt-2 font-semibold">
                      {channel.value}
                    </p>

                    <p className="mt-1 text-xs text-[var(--cx-muted)]">
                      {channel.note}
                    </p>
                  </div>
                ))}
              </div>
            </div>

            <form
              onSubmit={handleSubmit}
              className="cx-card rounded-[30px] p-7 sm:p-9"
            >
              <div
                aria-hidden="true"
                className="absolute -left-[9999px] h-0 w-0 overflow-hidden"
              >
                <label htmlFor="website">
                  Website
                </label>

                <input
                  id="website"
                  name="website"
                  type="text"
                  tabIndex={-1}
                  autoComplete="off"
                  value={form.website}
                  onChange={(event) =>
                    setForm((current) => ({
                      ...current,
                      website: event.target.value,
                    }))
                  }
                />
              </div>

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
                    required
                    minLength={2}
                    maxLength={100}
                    autoComplete="name"
                    placeholder="Your name"
                    className="cx-input w-full"
                    value={form.name}
                    onChange={(event) =>
                      setForm((current) => ({
                        ...current,
                        name: event.target.value,
                      }))
                    }
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
                    required
                    maxLength={254}
                    autoComplete="email"
                    placeholder="you@example.com"
                    className="cx-input w-full"
                    value={form.email}
                    onChange={(event) =>
                      setForm((current) => ({
                        ...current,
                        email: event.target.value,
                      }))
                    }
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
                  required
                  className="cx-input w-full"
                  value={form.category}
                  onChange={(event) =>
                    setForm((current) => ({
                      ...current,
                      category: event.target.value,
                    }))
                  }
                >
                  <option value="" disabled>
                    Select a category
                  </option>

                  <option value="general">
                    General Inquiry
                  </option>

                  <option value="security">
                    Security Research
                  </option>

                  <option value="partnership">
                    Partnership
                  </option>

                  <option value="support">
                    Technical Support
                  </option>
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
                  required
                  minLength={3}
                  maxLength={160}
                  placeholder="How can we help?"
                  className="cx-input w-full"
                  value={form.subject}
                  onChange={(event) =>
                    setForm((current) => ({
                      ...current,
                      subject: event.target.value,
                    }))
                  }
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
                  required
                  minLength={10}
                  maxLength={10000}
                  rows={7}
                  placeholder="Write your message..."
                  className="cx-input min-h-[170px] w-full resize-y"
                  value={form.message}
                  onChange={(event) =>
                    setForm((current) => ({
                      ...current,
                      message: event.target.value,
                    }))
                  }
                />
              </div>

              <div className="cx-inset-sm mt-6 rounded-2xl p-4">
                <p className="text-xs leading-5 text-[var(--cx-muted)]">
                  Do not include passwords, private keys,
                  authentication tokens, or other sensitive
                  credentials in this form.
                </p>
              </div>

              {success && (
                <div
                  role="status"
                  className="mt-6 rounded-2xl border border-emerald-500/30 bg-emerald-500/10 p-4 text-sm text-emerald-300"
                >
                  {success}
                </div>
              )}

              {error && (
                <div
                  role="alert"
                  className="mt-6 rounded-2xl border border-red-500/30 bg-red-500/10 p-4 text-sm text-red-300"
                >
                  {error}
                </div>
              )}

              <button
                type="submit"
                disabled={sending}
                className="cx-button cx-button-primary mt-7 w-full disabled:cursor-not-allowed disabled:opacity-60"
              >
                {sending
                  ? "Sending..."
                  : "Send Message"}
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
            Use the private security contact channel for
            responsible vulnerability disclosure. Include the
            affected component, impact, reproduction details, and
            remediation suggestions where possible.
          </p>

          <div className="mt-7 flex flex-col justify-center gap-3 sm:flex-row">
            <button
              type="button"
              onClick={() =>
                selectCategory("security")
              }
              className="cx-button cx-button-primary"
            >
              Security Disclosure
            </button>

            <Link
              href="/docs"
              className="cx-button cx-button-secondary"
            >
              Read Documentation
            </Link>
          </div>
        </div>
      </section>

      <footer className="border-t border-[var(--cx-border)]">
        <div className="mx-auto flex max-w-7xl flex-col gap-3 px-5 py-8 text-sm text-[var(--cx-muted)] sm:px-8 lg:flex-row lg:items-center lg:justify-between lg:px-10">
          <p>
            © 2026 CrypticX Lab. Open security infrastructure.
          </p>

          <p>
            Responsible communication and responsible research.
          </p>
        </div>
      </footer>
    </main>
    </>
  );
}
