/*
 * SPDX-License-Identifier: MIT
 */

// Counts a page view without cookies, storage or anything that tells
// visitors apart (decision 0229). A view starts a visit when the visitor
// comes from another website or none, not on a reload or with the back
// button; only then go along the referring host and the kind of device.
(() => {
    const script = document.currentScript;

    if (!script || !navigator.sendBeacon) {
        return;
    }

    const navigation = performance.getEntriesByType('navigation')[0];
    const type = navigation ? navigation.type : 'navigate';
    let host = '';

    try {
        host = document.referrer ? new URL(document.referrer).hostname : '';
    } catch {
        host = '';
    }

    const visit = type === 'navigate' && host !== location.hostname;
    const hit = { target: script.dataset.target, language: script.dataset.language, visit };

    if (visit) {
        // A campaign link names its source, e.g. ?utm_source=newsletter.
        hit.source = new URLSearchParams(location.search).get('utm_source') || host;
        const width = Math.min(screen.width, screen.height);
        hit.device = width < 600 ? 'mobile' : (width < 1000 && matchMedia('(pointer: coarse)').matches ? 'tablet' : 'desktop');
    }

    navigator.sendBeacon(script.dataset.url, JSON.stringify(hit));
})();
