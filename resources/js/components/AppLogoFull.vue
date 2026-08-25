<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { cn } from '@/lib/utils';

defineOptions({
    inheritAttrs: false,
});

const page = usePage();
const props = withDefaults(
    defineProps<{
        background?: 'auto' | 'light' | 'dark';
    }>(),
    {
        background: 'auto',
    },
);
const fullLogoUrl = computed(() => page.props.branding.fullLogoUrl);
const darkFullLogoUrl = computed(() => page.props.branding.darkFullLogoUrl);
const hasDarkFullLogo = computed(() => darkFullLogoUrl.value !== null);
const lightLogoClass = computed(() =>
    cn(
        'object-contain',
        hasDarkFullLogo.value && props.background === 'auto' && 'dark:hidden',
        hasDarkFullLogo.value && props.background === 'dark' && 'hidden',
    ),
);
const darkLogoClass = computed(() =>
    cn('object-contain', props.background === 'auto' && 'hidden dark:block'),
);
</script>

<template>
    <span class="contents">
        <img
            v-if="fullLogoUrl"
            :src="fullLogoUrl"
            alt=""
            aria-hidden="true"
            :class="lightLogoClass"
            v-bind="$attrs"
        />
        <AppLogoIcon v-else :class="lightLogoClass" v-bind="$attrs" />
        <img
            v-if="hasDarkFullLogo && background !== 'light'"
            :src="darkFullLogoUrl ?? undefined"
            alt=""
            aria-hidden="true"
            :class="darkLogoClass"
            v-bind="$attrs"
        />
    </span>
</template>
