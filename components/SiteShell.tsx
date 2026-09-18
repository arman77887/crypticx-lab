"use client";

import { usePathname } from "next/navigation";
import Navbar from "@/components/Navbar";

export default function SiteShell({
  children,
}: {
  children: React.ReactNode;
}) {
  const pathname = usePathname();

  const isAdmin =
    pathname === "/admin" ||
    pathname.startsWith("/admin/");

  if (isAdmin) {
    return <>{children}</>;
  }

  return (
    <div className="relative min-h-screen">
      {/* Global CrypticX brand watermark */}
      <div
        aria-hidden="true"
        className="pointer-events-none fixed inset-0 z-0 overflow-hidden"
      >
        <div className="absolute inset-0 flex items-center justify-center">
          <img
            src="/brand/crypticx2.png"
            alt=""
            className="h-[52vw] w-[52vw] max-h-[620px] max-w-[620px] select-none object-contain opacity-[0.045] sm:h-[44vw] sm:w-[44vw] lg:h-[36vw] lg:w-[36vw]"
          />
        </div>
      </div>

      <div className="relative z-40">
        <Navbar />
      </div>

      <div className="relative z-10">
        {children}
      </div>
    </div>
  );
}
