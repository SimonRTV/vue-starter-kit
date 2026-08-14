<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';

defineOptions({
    inheritAttrs: false,
});

const page = usePage();
const fullLogoUrl = computed(() => page.props.branding.fullLogoUrl);
</script>

<template>
    <img
        v-if="fullLogoUrl"
        :src="fullLogoUrl"
        alt=""
        aria-hidden="true"
        class="object-contain"
        v-bind="$attrs"
    />
    <AppLogoIcon v-else v-bind="$attrs" />
</template>
