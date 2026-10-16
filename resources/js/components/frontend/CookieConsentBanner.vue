<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { nextTick, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { useCookieConsent } from '@/composables/useCookieConsent';
import { home } from '@/routes';

const { consent, isBannerVisible, privacyPolicyUrl, updateConsent } =
    useCookieConsent();
const banner = ref<HTMLElement | null>(null);

watch(isBannerVisible, async (isVisible) => {
    if (isVisible && consent.value !== null) {
        await nextTick();
        banner.value?.focus();
    }
});

function isExternal(url: string): boolean {
    return /^https?:\/\//i.test(url);
}

function resolvedUrl(url: string): string {
    return url.startsWith('#') ? `${home.url()}${url}` : url;
}
</script>

<template>
    <div
        v-if="isBannerVisible"
        ref="banner"
        role="region"
        aria-labelledby="cookie-consent-title"
        tabindex="-1"
        class="fixed inset-x-0 bottom-0 z-50 p-4 outline-none sm:p-6"
    >
        <div
            class="bg-background border-border mx-auto flex max-w-3xl flex-col gap-4 rounded-xl border p-5 shadow-lg sm:flex-row sm:items-end sm:justify-between"
        >
            <div class="space-y-1.5">
                <p
                    id="cookie-consent-title"
                    class="text-foreground text-sm font-semibold"
                >
                    Cookies de mesure d’audience
                </p>
                <p class="text-muted-foreground text-sm">
                    Avec votre accord, nous utilisons Google Analytics pour
                    comprendre comment le site est utilisé. Aucun cookie de
                    mesure n’est déposé si vous refusez.
                    <template v-if="privacyPolicyUrl">
                        <a
                            v-if="isExternal(privacyPolicyUrl)"
                            :href="privacyPolicyUrl"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="text-foreground underline underline-offset-4"
                            >Politique de confidentialité</a
                        >
                        <Link
                            v-else
                            :href="resolvedUrl(privacyPolicyUrl)"
                            class="text-foreground underline underline-offset-4"
                            >Politique de confidentialité</Link
                        >
                    </template>
                </p>
            </div>
            <div class="flex shrink-0 gap-2">
                <Button
                    variant="outline"
                    class="flex-1 sm:flex-none"
                    @click="updateConsent('denied')"
                    >Refuser</Button
                >
                <Button
                    class="flex-1 sm:flex-none"
                    @click="updateConsent('granted')"
                    >Accepter</Button
                >
            </div>
        </div>
    </div>
</template>
