<script setup lang="ts">
import { useAdminTheme } from '@/composables/useAdminTheme';
import { useAppearance } from '@/composables/useAppearance';
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';
import type { BreadcrumbItem } from '@/types';

const { breadcrumbs = [] } = defineProps<{
    breadcrumbs?: BreadcrumbItem[];
}>();

useAppearance();
useAdminTheme();
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <slot />
    </AppLayout>
</template>
