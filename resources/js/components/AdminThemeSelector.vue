<script setup lang="ts">
import { Check, Leaf, Palette, Waves } from '@lucide/vue';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { isAdminTheme, useAdminTheme } from '@/composables/useAdminTheme';

const { adminTheme, updateAdminTheme } = useAdminTheme();

const themes = [
    {
        value: 'neutral',
        label: 'Neutre',
        description: 'Un espace de travail monochrome et épuré.',
        Icon: Palette,
    },
    {
        value: 'ocean',
        label: 'Océan',
        description: 'Une palette bleue conçue pour la concentration.',
        Icon: Waves,
    },
    {
        value: 'forest',
        label: 'Forêt',
        description: 'Un espace de travail vert et apaisant.',
        Icon: Leaf,
    },
] as const;

function selectTheme(value: unknown): void {
    if (isAdminTheme(value)) {
        updateAdminTheme(value);
    }
}
</script>

<template>
    <section aria-labelledby="admin-theme-label" class="flex flex-col gap-3">
        <div class="flex flex-col gap-1">
            <h2 id="admin-theme-label" class="text-sm font-medium">
                Thème d’administration
            </h2>
            <p class="text-sm text-muted-foreground">
                Choisissez la palette de couleurs de l’interface
                d’administration.
            </p>
        </div>

        <ToggleGroup
            type="single"
            variant="outline"
            :spacing="3"
            :model-value="adminTheme"
            class="grid w-full grid-cols-1 items-stretch sm:grid-cols-3"
            aria-labelledby="admin-theme-label"
            @update:model-value="selectTheme"
        >
            <ToggleGroupItem
                v-for="theme in themes"
                :key="theme.value"
                :value="theme.value"
                :aria-label="`${theme.label}: ${theme.description}`"
                class="h-auto min-w-0 flex-col items-stretch p-4 text-left whitespace-normal"
            >
                <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <component :is="theme.Icon" />
                        <span>{{ theme.label }}</span>
                    </div>
                    <Check v-if="adminTheme === theme.value" />
                </div>

                <p class="text-xs text-muted-foreground">
                    {{ theme.description }}
                </p>

                <div
                    :data-admin-theme-preview="theme.value"
                    class="admin-theme-preview mt-1 flex h-16 overflow-hidden rounded-md border"
                    aria-hidden="true"
                >
                    <div
                        class="flex w-1/4 flex-col gap-1 bg-[var(--admin-theme-preview-sidebar)] p-2"
                    >
                        <span
                            class="h-1.5 w-full rounded-full bg-[var(--admin-theme-preview-primary)]"
                        />
                        <span
                            class="h-1.5 w-2/3 rounded-full bg-[var(--admin-theme-preview-muted)]"
                        />
                    </div>
                    <div
                        class="flex flex-1 flex-col gap-2 bg-[var(--admin-theme-preview-background)] p-2"
                    >
                        <span
                            class="h-2 w-1/2 rounded-full bg-[var(--admin-theme-preview-foreground)]"
                        />
                        <div class="grid flex-1 grid-cols-2 gap-1">
                            <span
                                class="rounded-sm bg-[var(--admin-theme-preview-card)] shadow-xs"
                            />
                            <span
                                class="rounded-sm bg-[var(--admin-theme-preview-card)] shadow-xs"
                            />
                        </div>
                    </div>
                </div>
            </ToggleGroupItem>
        </ToggleGroup>
    </section>
</template>
