declare global {
    interface Window {
        dataLayer?: Record<string, unknown>[];
    }
}

/**
 * Push a custom event into GTM's dataLayer.
 *
 * Safe to call even when GTM isn't loaded (e.g. local/dev) — the push is just
 * queued on the array and never sent anywhere. In GTM, create a Custom Event
 * trigger whose event name matches `event`, then wire it to a GA4 Event tag.
 * Any `params` become GA4 event parameters via Data Layer Variables.
 *
 * @param event  GA4-style snake_case event name, e.g. `cta_click`, `form_submit`.
 * @param params Extra data for the event, e.g. `{ form: 'club_application' }`.
 */
export function trackEvent(event: string, params: Record<string, unknown> = {}): void {
    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push({ event, ...params });
}
