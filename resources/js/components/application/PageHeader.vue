<script setup lang="ts">
defineProps<{
    title: string;
    description?: string;
}>();
</script>

<template>
    <header
        class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
    >
        <div class="flex min-w-0 flex-col gap-2">
            <div class="flex flex-wrap items-center gap-2">
                <h1
                    class="min-w-0 text-2xl font-semibold tracking-tight break-words sm:text-3xl"
                >
                    {{ title }}
                </h1>
                <slot name="badge" />
            </div>
            <p
                v-if="description"
                class="text-sm text-muted-foreground sm:text-base"
            >
                {{ description }}
            </p>
            <slot name="meta" />
        </div>

        <div v-if="$slots.actions" class="flex shrink-0 flex-wrap gap-2">
            <slot name="actions" />
        </div>
    </header>
</template>
