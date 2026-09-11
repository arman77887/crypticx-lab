"use client";

import { MapContainer, TileLayer } from "react-leaflet";
import "leaflet/dist/leaflet.css";

export default function WorldActivityMap() {
  return (
    <div className="relative h-[420px] w-full overflow-hidden rounded-[28px] border border-white/10 bg-[#070708]">
      <MapContainer
        center={[20, 0]}
        zoom={2}
        minZoom={2}
        maxZoom={5}
        scrollWheelZoom={false}
        zoomControl={true}
        className="cx-soc-map h-full w-full"
        worldCopyJump
      >
        <TileLayer
          attribution="&copy; OpenStreetMap contributors"
          url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
        />
      </MapContainer>

      <div className="pointer-events-none absolute left-4 top-4 z-[1000] rounded-xl border border-white/10 bg-black/75 px-4 py-3 backdrop-blur">
        <div className="text-[10px] font-semibold uppercase tracking-[0.2em] text-white/50">
          Global Activity
        </div>

        <div className="mt-1 text-sm font-semibold text-white">
          Live intelligence map
        </div>

        <div className="mt-1 text-xs text-white/40">
          Awaiting authorized activity data
        </div>
      </div>

      <div className="pointer-events-none absolute bottom-4 left-4 z-[1000] rounded-xl border border-white/10 bg-black/75 px-3 py-2 backdrop-blur">
        <div className="flex items-center gap-2 text-[10px] uppercase tracking-wider text-white/50">
          <span className="h-2 w-2 rounded-full bg-white/30" />
          No active events
        </div>
      </div>
    </div>
  );
}
