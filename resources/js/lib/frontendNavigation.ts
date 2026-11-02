import type { FrontendNavigationDestinations } from '@/types';

export type FrontendNavigationDestinationKind =
    | 'empty'
    | 'anchor'
    | 'internal'
    | 'external'
    | 'invalid';

/**
 * Mirrors ApplicationSetting::FRONTEND_NAVIGATION_DESTINATION_PATTERN.
 */
const localDestinationPattern =
    /^(?:#[A-Za-z][A-Za-z0-9_:.-]*|\/(?!\/)[^\s\\]*)$/u;

export const FRONTEND_NAVIGATION_MAX_ITEMS = 10;
export const FRONTEND_NAVIGATION_MAX_CHILDREN = 8;

export function frontendNavigationDestinationKind(
    url: string | null | undefined,
): FrontendNavigationDestinationKind {
    const destination = url?.trim() ?? '';

    if (destination === '') {
        return 'empty';
    }

    if (localDestinationPattern.test(destination)) {
        return destination.startsWith('#') ? 'anchor' : 'internal';
    }

    if (!/^https?:\/\//i.test(destination)) {
        return 'invalid';
    }

    try {
        return new URL(destination).hostname ? 'external' : 'invalid';
    } catch {
        return 'invalid';
    }
}

/**
 * Human-readable name of a destination already known by the picker.
 */
export function frontendNavigationDestinationLabel(
    url: string | null | undefined,
    destinations: FrontendNavigationDestinations,
): string | null {
    const destination = url?.trim() ?? '';

    if (destination === destinations.home.url) {
        return destinations.home.label;
    }

    return (
        [...destinations.sections, ...destinations.pages].find(
            (candidate) => candidate.url === destination,
        )?.label ?? null
    );
}

/**
 * Internal content pages that are no longer published (or never were).
 */
export function isUnknownFrontendNavigationPage(
    url: string | null | undefined,
    destinations: FrontendNavigationDestinations,
): boolean {
    const destination = url?.trim() ?? '';
    const path = destination.split(/[?#]/)[0] ?? '';

    return (
        path.startsWith('/content/') &&
        !destinations.pages.some((page) => page.url === path)
    );
}
