type AnalyticsValue = string | number | boolean;

type AnalyticsParams = Record<string, AnalyticsValue>;

declare global {
  interface Window {
    gtag?: (
      command: "event",
      eventName: string,
      params?: AnalyticsParams,
    ) => void;
  }
}

export function trackEvent(
  eventName: string,
  params?: AnalyticsParams,
): void {
  if (typeof window === "undefined") {
    return;
  }

  if (typeof window.gtag !== "function") {
    return;
  }

  window.gtag("event", eventName, params);
}
