<script setup lang="ts">
import SplitLayout from '@/layouts/auth/AuthSplitLayout.vue';

const { title = '', description = '' } = defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <SplitLayout :title="title" :description="description">
        <slot />
    </SplitLayout>
</template>
