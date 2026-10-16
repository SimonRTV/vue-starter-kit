import { router } from '@inertiajs/vue3';
import type { AnalyticsConfiguration, AnalyticsConsent } from '@/types';

declare global {
    interface Window {
        dataLayer?: unknown[];
        gtag?: (...args: unknown[]) => void;
        [disableKey: `ga-disable-${string}`]: boolean | undefined;
    }
}

const consentCookieName = 'analytics_consent';
const consentMaxAge = 180 * 24 * 60 * 60;
const loadedMeasurementIds = new Set<string>();

export function readAnalyticsConsent(): AnalyticsConsent | null {
    if (typeof document === 'undefined') {
        return null;
    }

    const match = document.cookie.match(
        new RegExp(`(?:^|;\\s*)${consentCookieName}=(granted|denied)(?:;|$)`),
    );

    return match ? (match[1] as AnalyticsConsent) : null;
}

export function storeAnalyticsConsent(consent: AnalyticsConsent): void {
    if (typeof document === 'undefined') {
        return;
    }

    const secure = window.location.protocol === 'https:' ? ';Secure' : '';

    document.cookie = `${consentCookieName}=${consent};path=/;max-age=${consentMaxAge};SameSite=Lax${secure}`;

    if (consent === 'denied') {
        removeGoogleAnalyticsCookies();
    }
}

/**
 * Load, re-enable or disable Google Analytics for the current page,
 * honouring the visitor's consent when the site requires it.
 */
export function syncGoogleAnalytics(
    configuration: AnalyticsConfiguration | undefined,
): void {
    if (typeof window === 'undefined') {
        return;
    }

    const measurementId = configuration?.measurementId ?? null;
    const isAllowed =
        measurementId !== null &&
        (!configuration?.requiresConsent ||
            readAnalyticsConsent() === 'granted');

    for (const loadedMeasurementId of loadedMeasurementIds) {
        window[`ga-disable-${loadedMeasurementId}`] = !(
            isAllowed && loadedMeasurementId === measurementId
        );
    }

    if (isAllowed && measurementId !== null) {
        window[`ga-disable-${measurementId}`] = false;
        loadGoogleAnalytics(measurementId);
    }
}

export function initializeGoogleAnalytics(): void {
    router.on('navigate', (event) => {
        syncGoogleAnalytics(event.detail.page.props.analytics);
    });
}

function loadGoogleAnalytics(measurementId: string): void {
    if (loadedMeasurementIds.has(measurementId)) {
        return;
    }

    if (loadedMeasurementIds.size === 0) {
        const script = document.createElement('script');
        script.async = true;
        script.src = `https://www.googletagmanager.com/gtag/js?id=${encodeURIComponent(measurementId)}`;
        document.head.appendChild(script);

        window.dataLayer = window.dataLayer ?? [];
        window.gtag = function gtag() {
            // gtag.js expects the native arguments object, not an array.
            // eslint-disable-next-line prefer-rest-params
            window.dataLayer?.push(arguments);
        };
        window.gtag('js', new Date());
    }

    loadedMeasurementIds.add(measurementId);
    window.gtag?.('config', measurementId);
}

function removeGoogleAnalyticsCookies(): void {
    const hostnameParts = window.location.hostname.split('.');
    const domainAttributes = [''];

    for (let index = 0; index < hostnameParts.length - 1; index++) {
        domainAttributes.push(
            `;domain=.${hostnameParts.slice(index).join('.')}`,
        );
    }

    document.cookie
        .split(';')
        .map((cookie) => cookie.split('=')[0].trim())
        .filter((name) => name === '_ga' || name.startsWith('_ga_'))
        .forEach((name) => {
            domainAttributes.forEach((domainAttribute) => {
                document.cookie = `${name}=;path=/;max-age=0${domainAttribute}`;
            });
        });
}
