<script setup lang="ts">
import type { Component } from 'vue';
import {
    Empty,
    EmptyContent,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';

defineProps<{
    title: string;
    description?: string;
    icon?: Component;
}>();
</script>

<template>
    <Empty>
        <EmptyHeader>
            <EmptyMedia v-if="icon || $slots.icon" variant="icon">
                <slot name="icon">
                    <component :is="icon" v-if="icon" />
                </slot>
            </EmptyMedia>
            <EmptyTitle>{{ title }}</EmptyTitle>
            <EmptyDescription v-if="description">
                {{ description }}
            </EmptyDescription>
        </EmptyHeader>
        <EmptyContent v-if="$slots.actions">
            <slot name="actions" />
        </EmptyContent>
    </Empty>
</template>
