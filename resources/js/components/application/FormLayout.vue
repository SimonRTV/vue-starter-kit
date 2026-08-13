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
    <Card>
        <CardHeader>
            <CardTitle>{{ title }}</CardTitle>
            <CardDescription v-if="description">
                {{ description }}
            </CardDescription>
            <CardAction v-if="$slots.headerAction">
                <slot name="headerAction" />
            </CardAction>
        </CardHeader>
        <CardContent>
            <slot />
        </CardContent>
        <CardFooter
            v-if="$slots.actions"
            class="flex flex-col-reverse items-stretch gap-2 sm:flex-row sm:items-center sm:justify-end"
        >
            <slot name="actions" />
        </CardFooter>
    </Card>
</template>
