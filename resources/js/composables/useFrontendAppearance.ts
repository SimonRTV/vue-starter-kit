import type { ComputedRef, Ref } from 'vue';
import { computed, onMounted, ref } from 'vue';
import { applyAdminTheme } from '@/composables/useAdminTheme';
import {
    applyAppearance,
    isAppearance,
    resolveAppearance,
} from '@/composables/useAppearance';
import type { Appearance, ResolvedAppearance } from '@/types';

export type UseFrontendAppearanceReturn = {
    frontendAppearance: Ref<Appearance>;
    resolvedFrontendAppearance: ComputedRef<ResolvedAppearance>;
    updateFrontendAppearance: (value: ResolvedAppearance) => void;
};

const storageKey = 'frontend_appearance';
const frontendAppearance = ref<Appearance>('system');

function setCookie(value: Appearance, days = 365): void {
    if (typeof document === 'undefined') {
        return;
    }

    const maxAge = days * 24 * 60 * 60;

    document.cookie = `${storageKey}=${value};path=/;max-age=${maxAge};SameSite=Lax`;
}

function getStoredFrontendAppearance(): Appearance {
    if (typeof window === 'undefined') {
        return 'system';
    }

    const storedAppearance = localStorage.getItem(storageKey);

    if (isAppearance(storedAppearance)) {
        return storedAppearance;
    }

    const documentAppearance = document.documentElement.dataset.appearance;

    if (
        document.documentElement.dataset.appearanceSurface === 'frontend' &&
        isAppearance(documentAppearance)
    ) {
        return documentAppearance;
    }

    return 'system';
}

function applyFrontendAppearance(value: Appearance): void {
    applyAdminTheme('neutral');
    applyAppearance(value, 'frontend');
}

export function useFrontendAppearance(): UseFrontendAppearanceReturn {
    onMounted(() => {
        frontendAppearance.value = getStoredFrontendAppearance();
        applyFrontendAppearance(frontendAppearance.value);
    });

    const resolvedFrontendAppearance = computed<ResolvedAppearance>(() =>
        resolveAppearance(frontendAppearance.value),
    );

    function updateFrontendAppearance(value: ResolvedAppearance): void {
        frontendAppearance.value = value;
        localStorage.setItem(storageKey, value);
        setCookie(value);
        applyFrontendAppearance(value);
    }

    return {
        frontendAppearance,
        resolvedFrontendAppearance,
        updateFrontendAppearance,
    };
}
