<script setup lang="ts">
import { Moon, Sun } from '@lucide/vue';
import { computed } from 'vue';
import { Switch } from '@/components/ui/switch';
import { useFrontendAppearance } from '@/composables/useFrontendAppearance';

const { resolvedFrontendAppearance, updateFrontendAppearance } =
    useFrontendAppearance();

const isDark = computed(() => resolvedFrontendAppearance.value === 'dark');
const label = computed(() =>
    isDark.value ? 'Use light mode' : 'Use dark mode',
);

function updateMode(value: boolean): void {
    updateFrontendAppearance(value ? 'dark' : 'light');
}
</script>

<template>
    <div class="inline-flex items-center gap-2 text-muted-foreground">
        <Sun class="size-4" aria-hidden="true" />
        <Switch
            :model-value="isDark"
            :aria-label="label"
            :title="label"
            @update:model-value="updateMode"
        />
        <Moon class="size-4" aria-hidden="true" />
    </div>
</template>
