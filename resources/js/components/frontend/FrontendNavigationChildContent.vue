<script setup lang="ts">
import { cn } from '@/lib/utils';

withDefaults(
    defineProps<{
        label: string;
        description: string;
        compact?: boolean;
    }>(),
    {
        compact: false,
    },
);
</script>

<template>
    <span
        :class="
            cn(
                'flex w-full flex-col justify-center gap-0.5',
                compact ? 'px-3 py-2' : 'px-3 py-2.5',
            )
        "
        data-test="frontend-navigation-child-content"
    >
        <span class="leading-5 font-medium">{{ label }}</span>
        <span
            v-if="description"
            class="text-sm leading-5 text-muted-foreground"
        >
            {{ description }}
        </span>
    </span>
</template>
