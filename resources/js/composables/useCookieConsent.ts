import { usePage } from '@inertiajs/vue3';
import type { ComputedRef, Ref } from 'vue';
import { computed, onMounted, ref } from 'vue';
import {
    readAnalyticsConsent,
    storeAnalyticsConsent,
    syncGoogleAnalytics,
} from '@/lib/googleAnalytics';
import type { AnalyticsConsent } from '@/types';

export type UseCookieConsentReturn = {
    consent: Ref<AnalyticsConsent | null>;
    isConsentRequired: ComputedRef<boolean>;
    isBannerVisible: ComputedRef<boolean>;
    privacyPolicyUrl: ComputedRef<string | null>;
    updateConsent: (value: AnalyticsConsent) => void;
    openPreferences: () => void;
};

const consent = ref<AnalyticsConsent | null>(null);
const isPreferencesOpen = ref(false);
const isHydrated = ref(false);

export function useCookieConsent(): UseCookieConsentReturn {
    const page = usePage();
    const configuration = computed(() => page.props.analytics);
    const isConsentRequired = computed(
        () =>
            configuration.value.measurementId !== null &&
            configuration.value.requiresConsent,
    );
    const isBannerVisible = computed(
        () =>
            isHydrated.value &&
            isConsentRequired.value &&
            (consent.value === null || isPreferencesOpen.value),
    );

    onMounted(() => {
        consent.value = readAnalyticsConsent();
        isHydrated.value = true;
    });

    function updateConsent(value: AnalyticsConsent): void {
        storeAnalyticsConsent(value);
        consent.value = value;
        isPreferencesOpen.value = false;
        syncGoogleAnalytics(configuration.value);
    }

    function openPreferences(): void {
        isPreferencesOpen.value = true;
    }

    return {
        consent,
        isConsentRequired,
        isBannerVisible,
        privacyPolicyUrl: computed(() => configuration.value.privacyPolicyUrl),
        updateConsent,
        openPreferences,
    };
}
