<script setup lang="ts">
import {
    AlertTriangle,
    ChevronDown,
    ExternalLink,
    FileText,
    Hash,
    House,
    Link2,
} from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Field,
    FieldDescription,
    FieldError,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import {
    frontendNavigationDestinationKind,
    frontendNavigationDestinationLabel,
    isUnknownFrontendNavigationPage,
} from '@/lib/frontendNavigation';
import { cn } from '@/lib/utils';
import type {
    FrontendNavigationDestination,
    FrontendNavigationDestinations,
} from '@/types';

const props = withDefaults(
    defineProps<{
        id: string;
        destinations: FrontendNavigationDestinations;
        error?: string;
        placeholder?: string;
    }>(),
    {
        error: undefined,
        placeholder: 'Ex. /a-propos ou https://exemple.com',
    },
);

const emit = defineEmits<{
    pick: [destination: FrontendNavigationDestination];
}>();

const url = defineModel<string>({ required: true });

const kind = computed(() => frontendNavigationDestinationKind(url.value));
const knownLabel = computed(() =>
    frontendNavigationDestinationLabel(url.value, props.destinations),
);
const isUnknownPage = computed(() =>
    isUnknownFrontendNavigationPage(url.value, props.destinations),
);
const warning = computed(() => {
    if (kind.value === 'invalid') {
        return 'Utilisez une ancre (#section), un chemin commençant par / ou une URL HTTP(S).';
    }

    if (isUnknownPage.value) {
        return 'Aucune page publiée ne correspond à ce chemin : les visiteurs verront une erreur 404.';
    }

    return null;
});
const hint = computed(() => {
    const name = knownLabel.value ? ` · ${knownLabel.value}` : '';

    switch (kind.value) {
        case 'anchor':
            return `Section de la page d’accueil${name}`;
        case 'internal':
            return `Page du site${name}`;
        case 'external':
            return 'Site externe, ouvert dans un nouvel onglet';
        default:
            return 'Choisissez une page ou saisissez une adresse.';
    }
});
const hintIcon = computed(() => {
    switch (kind.value) {
        case 'anchor':
            return Hash;
        case 'internal':
            return url.value.trim() === props.destinations.home.url
                ? House
                : FileText;
        case 'external':
            return ExternalLink;
        default:
            return Link2;
    }
});

function pick(destination: FrontendNavigationDestination): void {
    url.value = destination.url;
    emit('pick', destination);
}
</script>

<template>
    <Field class="gap-2" :data-invalid="Boolean(error)">
        <FieldLabel :for="id">Destination</FieldLabel>
        <div class="flex gap-2">
            <Input
                :id="id"
                v-model="url"
                type="text"
                inputmode="url"
                maxlength="2048"
                autocomplete="off"
                autocapitalize="none"
                spellcheck="false"
                class="min-w-0 flex-1"
                :aria-invalid="Boolean(error) || kind === 'invalid'"
                :aria-describedby="`${id}-hint`"
                :placeholder="placeholder"
            />
            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <Button
                        type="button"
                        variant="outline"
                        class="shrink-0"
                        data-test="navigation-destination-picker"
                    >
                        Choisir
                        <ChevronDown data-icon="inline-end" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" class="max-h-80 w-72">
                    <DropdownMenuItem @select="pick(destinations.home)">
                        <House />
                        {{ destinations.home.label }}
                    </DropdownMenuItem>
                    <template v-if="destinations.pages.length > 0">
                        <DropdownMenuSeparator />
                        <DropdownMenuLabel
                            class="text-muted-foreground text-xs font-normal"
                        >
                            Pages publiées
                        </DropdownMenuLabel>
                        <DropdownMenuGroup>
                            <DropdownMenuItem
                                v-for="page in destinations.pages"
                                :key="page.url"
                                @select="pick(page)"
                            >
                                <FileText />
                                <span class="truncate">{{ page.label }}</span>
                            </DropdownMenuItem>
                        </DropdownMenuGroup>
                    </template>
                    <template v-if="destinations.sections.length > 0">
                        <DropdownMenuSeparator />
                        <DropdownMenuLabel
                            class="text-muted-foreground text-xs font-normal"
                        >
                            Sections de l’accueil
                        </DropdownMenuLabel>
                        <DropdownMenuGroup>
                            <DropdownMenuItem
                                v-for="section in destinations.sections"
                                :key="section.url"
                                @select="pick(section)"
                            >
                                <Hash />
                                <span class="truncate">
                                    {{ section.label }}
                                </span>
                                <span
                                    class="text-muted-foreground ml-auto text-xs"
                                >
                                    {{ section.url }}
                                </span>
                            </DropdownMenuItem>
                        </DropdownMenuGroup>
                    </template>
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
        <FieldDescription
            :id="`${id}-hint`"
            :class="
                cn(
                    'flex items-start gap-1.5 text-xs',
                    warning && 'text-amber-700 dark:text-amber-400',
                )
            "
            data-test="navigation-destination-hint"
        >
            <component
                :is="warning ? AlertTriangle : hintIcon"
                class="mt-px size-3.5 shrink-0"
                aria-hidden="true"
            />
            <span>{{ warning ?? hint }}</span>
        </FieldDescription>
        <FieldError :errors="[error]" />
    </Field>
</template>
