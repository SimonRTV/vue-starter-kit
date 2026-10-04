<script setup lang="ts">
import {
    Card,
    CardAction,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

defineProps<{
    title: string;
    description?: string;
}>();
</script>

<template>
    <Card class="admin-form gap-0 overflow-hidden py-0">
        <div class="grid lg:grid-cols-[190px_minmax(0,1fr)]">
            <CardHeader class="bg-muted/35 flex flex-col items-start gap-3 p-6">
                <p class="admin-eyebrow">Configuration</p>
                <CardTitle class="leading-snug">{{ title }}</CardTitle>
                <CardDescription v-if="description">
                    {{ description }}
                </CardDescription>
                <CardAction v-if="$slots.headerAction">
                    <slot name="headerAction" />
                </CardAction>
            </CardHeader>
            <CardContent class="min-w-0 py-6 lg:p-8">
                <slot />
            </CardContent>
        </div>
        <CardFooter
            v-if="$slots.actions"
            class="bg-muted/20 flex flex-col-reverse items-stretch gap-2 py-5 sm:flex-row sm:items-center sm:justify-end"
        >
            <slot name="actions" />
        </CardFooter>
    </Card>
</template>
