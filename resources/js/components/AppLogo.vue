<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { cn } from '@/lib/utils';

const page = usePage();
const name = computed(() => page.props.name);
const hasCustomIcon = computed(() => Boolean(page.props.branding.iconUrl));
</script>

<template>
    <div
        :class="
            cn('flex size-8 items-center justify-center', {
                'rounded-md bg-sidebar-primary text-sidebar-primary-foreground':
                    !hasCustomIcon,
            })
        "
    >
        <AppLogoIcon
            :class="
                cn({
                    'size-8': hasCustomIcon,
                    'size-5 fill-current': !hasCustomIcon,
                })
            "
        />
    </div>
    <div class="ml-1 grid flex-1 text-left text-sm">
        <span class="mb-0.5 truncate leading-tight font-semibold">{{
            name
        }}</span>
    </div>
</template>
