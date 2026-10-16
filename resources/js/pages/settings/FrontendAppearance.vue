<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { Check, Monitor, Moon, Sun, ToggleRight } from '@lucide/vue';
import { ref } from 'vue';
import { update } from '@/actions/App/Http/Controllers/Settings/FrontendAppearanceController';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { FieldError } from '@/components/ui/field';
import { Spinner } from '@/components/ui/spinner';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { edit } from '@/routes/frontend-appearance';
import type { FrontendAppearanceMode } from '@/types';

const props = defineProps<{
    settings: {
        mode: FrontendAppearanceMode;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Apparence publique', href: edit() }],
    },
});

const modes = [
    {
        value: 'toggle',
        label: 'Au choix du visiteur',
        description:
            'Affiche le bouton clair/sombre. Le site suit le système jusqu’au premier choix.',
        Icon: ToggleRight,
    },
    {
        value: 'system',
        label: 'Système',
        description:
            'Masque le bouton et suit la préférence du système du visiteur.',
        Icon: Monitor,
    },
    {
        value: 'light',
        label: 'Toujours clair',
        description: 'Masque le bouton et force le mode clair.',
        Icon: Sun,
    },
    {
        value: 'dark',
        label: 'Toujours sombre',
        description: 'Masque le bouton et force le mode sombre.',
        Icon: Moon,
    },
] as const;

const mode = ref<FrontendAppearanceMode>(props.settings.mode);

function selectMode(value: unknown): void {
    if (modes.some((option) => option.value === value)) {
        mode.value = value as FrontendAppearanceMode;
    }
}
</script>

<template>
    <div class="space-y-6">
        <Heading
            variant="small"
            title="Apparence publique"
            description="Laissez les visiteurs choisir entre le mode clair et sombre, ou imposez un mode sur tout le site public. L’espace d’administration n’est pas concerné."
        />
        <Form
            v-bind="update.form()"
            v-slot="{ errors, processing, recentlySuccessful }"
            class="space-y-6"
        >
            <section
                aria-labelledby="frontend-appearance-mode-label"
                class="flex flex-col gap-3"
            >
                <h2
                    id="frontend-appearance-mode-label"
                    class="text-sm font-medium"
                >
                    Mode d’affichage
                </h2>
                <input type="hidden" name="mode" :value="mode" />
                <ToggleGroup
                    type="single"
                    variant="outline"
                    :spacing="3"
                    :model-value="mode"
                    class="grid w-full grid-cols-1 items-stretch sm:grid-cols-2"
                    aria-labelledby="frontend-appearance-mode-label"
                    @update:model-value="selectMode"
                >
                    <ToggleGroupItem
                        v-for="option in modes"
                        :key="option.value"
                        :value="option.value"
                        :aria-label="`${option.label} : ${option.description}`"
                        class="h-auto min-w-0 flex-col items-stretch justify-start p-4 text-left whitespace-normal"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <div class="flex items-center gap-2">
                                <component :is="option.Icon" />
                                <span>{{ option.label }}</span>
                            </div>
                            <Check v-if="mode === option.value" />
                        </div>
                        <p class="text-muted-foreground text-xs">
                            {{ option.description }}
                        </p>
                    </ToggleGroupItem>
                </ToggleGroup>
                <FieldError>{{ errors.mode }}</FieldError>
            </section>
            <div class="flex items-center gap-4">
                <Button type="submit" :disabled="processing"
                    ><Spinner v-if="processing" />Enregistrer</Button
                >
                <p
                    v-if="recentlySuccessful"
                    role="status"
                    class="text-muted-foreground text-sm"
                >
                    Enregistré.
                </p>
            </div>
        </Form>
    </div>
</template>
