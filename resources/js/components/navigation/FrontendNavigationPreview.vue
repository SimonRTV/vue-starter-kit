<script setup lang="ts">
import {
    ChevronDown,
    ExternalLink,
    Menu,
    Monitor,
    Smartphone,
} from '@lucide/vue';
import { ref } from 'vue';
import AppLogoFull from '@/components/AppLogoFull.vue';
import FrontendNavigationChildContent from '@/components/frontend/FrontendNavigationChildContent.vue';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { frontendNavigationDestinationKind } from '@/lib/frontendNavigation';
import type { FrontendNavigationItem } from '@/types';

defineProps<{
    items: FrontendNavigationItem[];
}>();

const device = ref<'desktop' | 'mobile'>('desktop');

function setDevice(value: unknown): void {
    if (value === 'desktop' || value === 'mobile') {
        device.value = value;
    }
}

function isExternal(url: string | null): boolean {
    return frontendNavigationDestinationKind(url) === 'external';
}
</script>

<template>
    <section
        aria-labelledby="frontend-navigation-preview-title"
        class="flex flex-col gap-3"
        data-test="navigation-preview"
    >
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div class="flex flex-col gap-0.5">
                <h2
                    id="frontend-navigation-preview-title"
                    class="text-sm font-medium"
                >
                    Aperçu en direct
                </h2>
                <p class="text-muted-foreground text-xs">
                    Reflète vos modifications avant l’enregistrement.
                </p>
            </div>
            <ToggleGroup
                type="single"
                variant="outline"
                size="sm"
                :model-value="device"
                aria-label="Format de l’aperçu"
                @update:model-value="setDevice"
            >
                <ToggleGroupItem value="desktop" aria-label="Ordinateur">
                    <Monitor />
                    <span class="hidden sm:inline">Ordinateur</span>
                </ToggleGroupItem>
                <ToggleGroupItem value="mobile" aria-label="Mobile">
                    <Smartphone />
                    <span class="hidden sm:inline">Mobile</span>
                </ToggleGroupItem>
            </ToggleGroup>
        </div>

        <div class="bg-muted/40 overflow-hidden rounded-lg border p-3">
            <div
                v-if="device === 'desktop'"
                class="bg-background flex min-h-14 items-center gap-4 overflow-x-auto rounded-md border px-4 py-2 shadow-xs"
            >
                <AppLogoFull
                    class="text-foreground h-6 w-auto max-w-28 shrink-0"
                />
                <p
                    v-if="items.length === 0"
                    class="text-muted-foreground text-xs"
                >
                    Aucun lien affiché.
                </p>
                <nav
                    v-else
                    aria-label="Aperçu de la navigation"
                    class="flex items-center gap-1"
                >
                    <template
                        v-for="(item, index) in items"
                        :key="`${index}-${item.type}`"
                    >
                        <DropdownMenu v-if="item.type === 'group'">
                            <DropdownMenuTrigger
                                class="hover:bg-accent focus-visible:ring-ring inline-flex h-8 shrink-0 items-center gap-1 rounded-md px-2.5 text-sm font-medium whitespace-nowrap outline-none focus-visible:ring-2"
                            >
                                {{ item.label || `Menu ${index + 1}` }}
                                <ChevronDown class="size-3.5 opacity-60" />
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="start" class="w-80 p-2">
                                <p
                                    v-if="item.children.length === 0"
                                    class="text-muted-foreground px-2 py-1.5 text-xs"
                                >
                                    Ajoutez au moins un sous-lien.
                                </p>
                                <DropdownMenuItem
                                    v-for="(child, childIndex) in item.children"
                                    :key="childIndex"
                                    class="p-0"
                                >
                                    <FrontendNavigationChildContent
                                        :label="
                                            child.label ||
                                            `Sous-lien ${childIndex + 1}`
                                        "
                                        :description="child.description"
                                    />
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                        <span
                            v-else
                            class="inline-flex h-8 shrink-0 items-center gap-1 rounded-md px-2.5 text-sm font-medium whitespace-nowrap"
                        >
                            {{ item.label || `Lien ${index + 1}` }}
                            <ExternalLink
                                v-if="isExternal(item.url)"
                                class="size-3 opacity-60"
                                aria-label="(lien externe)"
                            />
                        </span>
                    </template>
                </nav>
            </div>

            <div
                v-else
                class="bg-background mx-auto flex max-w-xs flex-col overflow-hidden rounded-xl border shadow-xs"
            >
                <div
                    class="flex items-center justify-between border-b px-4 py-3"
                >
                    <AppLogoFull class="text-foreground h-6 w-auto max-w-28" />
                    <Menu class="text-muted-foreground size-4" />
                </div>
                <p
                    v-if="items.length === 0"
                    class="text-muted-foreground px-4 py-6 text-xs"
                >
                    Aucun lien affiché.
                </p>
                <nav
                    v-else
                    aria-label="Aperçu de la navigation mobile"
                    class="flex flex-col gap-1 p-2"
                >
                    <template
                        v-for="(item, index) in items"
                        :key="`${index}-${item.type}`"
                    >
                        <Collapsible
                            v-if="item.type === 'group'"
                            class="group/collapsible"
                        >
                            <CollapsibleTrigger
                                class="hover:bg-accent flex w-full items-center justify-between rounded-md px-3 py-2 text-sm font-medium"
                            >
                                {{ item.label || `Menu ${index + 1}` }}
                                <ChevronDown
                                    class="size-4 transition-transform group-data-[state=open]/collapsible:rotate-180"
                                />
                            </CollapsibleTrigger>
                            <CollapsibleContent>
                                <div class="flex flex-col pl-3 text-sm">
                                    <FrontendNavigationChildContent
                                        v-for="(
                                            child, childIndex
                                        ) in item.children"
                                        :key="childIndex"
                                        compact
                                        :label="
                                            child.label ||
                                            `Sous-lien ${childIndex + 1}`
                                        "
                                        :description="child.description"
                                    />
                                </div>
                            </CollapsibleContent>
                        </Collapsible>
                        <span
                            v-else
                            class="flex items-center gap-1 rounded-md px-3 py-2 text-sm font-medium"
                        >
                            {{ item.label || `Lien ${index + 1}` }}
                            <ExternalLink
                                v-if="isExternal(item.url)"
                                class="size-3 opacity-60"
                                aria-label="(lien externe)"
                            />
                        </span>
                    </template>
                </nav>
            </div>
        </div>
    </section>
</template>
