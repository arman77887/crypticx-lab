import Link from "next/link";

export default function Footer() {
  return (
    <footer className="border-t border-white/10 bg-black/30">
      <div className="mx-auto flex max-w-7xl flex-col gap-4 px-6 py-8 text-sm text-zinc-400 sm:flex-row sm:items-center sm:justify-between">
        <p>© {new Date().getFullYear()} CrypticX Lab. All rights reserved.</p>

        <nav
          aria-label="Legal"
          className="flex flex-wrap gap-x-6 gap-y-2"
        >
          <Link className="transition hover:text-white" href="/terms">
            Terms of Service
          </Link>
          <Link className="transition hover:text-white" href="/privacy">
            Privacy Policy
          </Link>
          <Link className="transition hover:text-white" href="/refund">
            Refund Policy
          </Link>
          <Link className="transition hover:text-white" href="/contact">
            Contact
          </Link>
        </nav>
      </div>
    </footer>
  );
}
