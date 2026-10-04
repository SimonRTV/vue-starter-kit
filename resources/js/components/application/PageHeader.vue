<script setup lang="ts">
defineProps<{ title: string; description?: string }>();
</script>

<template>
    <header
        class="admin-page-header flex flex-col gap-5 border-b pb-7 sm:flex-row sm:items-end sm:justify-between"
    >
        <div class="flex min-w-0 flex-col gap-3">
            <div
                class="text-primary flex items-center gap-2 text-[10px] font-semibold tracking-[0.2em] uppercase"
            >
                <span
                    class="bg-primary size-1.5 rounded-full"
                    aria-hidden="true"
                />
                Espace de gestion
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <h1
                    class="min-w-0 text-3xl font-medium tracking-[-0.045em] break-words sm:text-4xl lg:text-5xl"
                >
                    {{ title }}
                </h1>
                <slot name="badge" />
            </div>
            <p
                v-if="description"
                class="text-muted-foreground max-w-2xl text-sm leading-relaxed"
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
