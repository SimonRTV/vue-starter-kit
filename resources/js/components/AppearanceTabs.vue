<script setup lang="ts">
import { Monitor, Moon, Sun } from '@lucide/vue';
import { useAppearance } from '@/composables/useAppearance';

const { appearance, updateAppearance } = useAppearance();

const tabs = [
    { value: 'light', Icon: Sun, label: 'Clair' },
    { value: 'dark', Icon: Moon, label: 'Sombre' },
    { value: 'system', Icon: Monitor, label: 'Système' },
] as const;
</script>

<template>
    <div
        class="bg-muted flex w-fit max-w-full flex-wrap gap-1 rounded-full p-1"
        role="group"
        aria-label="Mode d’affichage"
    >
        <button
            v-for="{ value, Icon, label } in tabs"
            :key="value"
            type="button"
            :aria-pressed="appearance === value"
            @click="updateAppearance(value)"
            :class="[
                'focus-visible:outline-ring flex items-center rounded-full px-4 py-2 transition-colors focus-visible:outline-2 focus-visible:outline-offset-2',
                appearance === value
                    ? 'bg-card text-foreground shadow-xs'
                    : 'text-muted-foreground hover:text-foreground',
            ]"
        >
            <component :is="Icon" class="-ml-1 h-4 w-4" />
            <span class="ml-1.5 text-sm">{{ label }}</span>
        </button>
    </div>
</template>
