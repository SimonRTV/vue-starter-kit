export type AnalyticsConsent = 'granted' | 'denied';

export type AnalyticsConfiguration = {
    measurementId: string | null;
    requiresConsent: boolean;
    privacyPolicyUrl: string | null;
};
