<script setup lang="ts">
import {
    ArrowDown,
    ArrowUp,
    ChevronDown,
    ChevronRight,
    ChevronsUpDown,
    FolderTree,
    Link2,
    ListTree,
    Plus,
    Trash2,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import EmptyState from '@/components/application/EmptyState.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardAction,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    Field,
    FieldDescription,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Separator } from '@/components/ui/separator';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { cn } from '@/lib/utils';
import type { FrontendNavigationChild, FrontendNavigationItem } from '@/types';

const props = defineProps<{
    errors: Record<string, string | undefined>;
}>();

const emit = defineEmits<{
    change: [];
}>();

const items = defineModel<FrontendNavigationItem[]>({ required: true });
let nextItemId = 0;

function createItemId(): string {
    nextItemId += 1;

    return `frontend-navigation-item-${nextItemId}`;
}

const itemIds = ref(items.value.map(() => createItemId()));
const openItemIds = ref<string[]>([]);
const allItemsOpen = computed(
    () =>
        itemIds.value.length > 0 &&
        itemIds.value.every((itemId) => openItemIds.value.includes(itemId)),
);

watch(
    () => Object.keys(props.errors),
    (errorPaths) => {
        const nextOpenItemIds = new Set(openItemIds.value);

        errorPaths.forEach((path) => {
            const match = path.match(/^items\.(\d+)(?:\.|$)/);
            const index = match?.[1] ? Number(match[1]) : null;
            const id = index === null ? undefined : itemIds.value[index];

            if (id) {
                nextOpenItemIds.add(id);
            }
        });

        openItemIds.value = [...nextOpenItemIds];
    },
);

function emptyChild(): FrontendNavigationChild {
    return {
        label: '',
        url: '',
        description: '',
    };
}

function itemId(index: number): string {
    return itemIds.value[index] ?? `frontend-navigation-item-${index}`;
}

function isItemOpen(id: string): boolean {
    return openItemIds.value.includes(id);
}

function setItemOpen(id: string, open: boolean): void {
    if (open) {
        openItemIds.value = [...new Set([...openItemIds.value, id])];

        return;
    }

    openItemIds.value = openItemIds.value.filter((itemId) => itemId !== id);
}

function toggleAllItems(): void {
    openItemIds.value = allItemsOpen.value ? [] : [...itemIds.value];
}

function itemHasErrors(index: number): boolean {
    const prefix = `items.${index}`;

    return Object.keys(props.errors).some(
        (path) => path === prefix || path.startsWith(`${prefix}.`),
    );
}

function itemSummary(item: FrontendNavigationItem): string {
    if (item.type === 'group') {
        const count = item.children.length;

        return `${count} sous-lien${count > 1 ? 's' : ''}`;
    }

    return item.url?.trim() || 'Destination à définir';
}

function addItem(type: FrontendNavigationItem['type']): void {
    if (items.value.length >= 10) {
        return;
    }

    items.value.push({
        type,
        label: '',
        url: type === 'link' ? '' : null,
        children: type === 'group' ? [emptyChild()] : [],
    });

    const id = createItemId();
    itemIds.value.push(id);
    setItemOpen(id, true);
    emit('change');
}

function removeItem(index: number): void {
    const [removedId] = itemIds.value.splice(index, 1);

    items.value.splice(index, 1);

    if (removedId) {
        setItemOpen(removedId, false);
    }

    emit('change');
}

function moveItem(index: number, offset: -1 | 1): void {
    const destination = index + offset;

    if (destination < 0 || destination >= items.value.length) {
        return;
    }

    const [item] = items.value.splice(index, 1);
    const [id] = itemIds.value.splice(index, 1);

    if (item && id) {
        items.value.splice(destination, 0, item);
        itemIds.value.splice(destination, 0, id);
        emit('change');
    }
}

function changeItemType(index: number, value: unknown): void {
    if (value !== 'link' && value !== 'group') {
        return;
    }

    const item = items.value[index];

    if (!item || item.type === value) {
        return;
    }

    item.type = value;
    item.url = value === 'link' ? '' : null;
    item.children = value === 'group' ? [emptyChild()] : [];
    emit('change');
}

function updateItemUrl(index: number, value: string | number): void {
    const item = items.value[index];

    if (!item || item.type !== 'link') {
        return;
    }

    item.url = String(value);
    emit('change');
}

function addChild(index: number): void {
    const item = items.value[index];

    if (!item || item.type !== 'group' || item.children.length >= 8) {
        return;
    }

    item.children.push(emptyChild());
    emit('change');
}

function removeChild(itemIndex: number, childIndex: number): void {
    items.value[itemIndex]?.children.splice(childIndex, 1);
    emit('change');
}

function moveChild(
    itemIndex: number,
    childIndex: number,
    offset: -1 | 1,
): void {
    const children = items.value[itemIndex]?.children;

    if (!children) {
        return;
    }

    const destination = childIndex + offset;

    if (destination < 0 || destination >= children.length) {
        return;
    }

    const [child] = children.splice(childIndex, 1);

    if (child) {
        children.splice(destination, 0, child);
        emit('change');
    }
}

function error(path: string): string | undefined {
    return props.errors[path];
}
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="flex flex-col gap-2">
            <p class="text-sm text-muted-foreground">
                Jusqu’à 10 éléments principaux et 8 sous-liens par menu.
            </p>
            <div class="flex flex-wrap gap-2">
                <Button
                    v-if="items.length > 1"
                    type="button"
                    variant="ghost"
                    size="sm"
                    @click="toggleAllItems"
                >
                    <ChevronsUpDown data-icon="inline-start" />
                    {{ allItemsOpen ? 'Tout réduire' : 'Tout développer' }}
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    :disabled="items.length >= 10"
                    @click="addItem('link')"
                >
                    <Link2 data-icon="inline-start" />
                    Ajouter un lien
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    :disabled="items.length >= 10"
                    @click="addItem('group')"
                >
                    <FolderTree data-icon="inline-start" />
                    Ajouter un menu
                </Button>
            </div>
        </div>

        <FieldError :errors="[error('items')]" />

        <EmptyState
            v-if="items.length === 0"
            title="Navigation vide"
            description="Le site public n’affichera aucun lien entre le logo et l’accès au compte."
            :icon="ListTree"
        >
            <template #actions>
                <Button
                    type="button"
                    variant="outline"
                    @click="addItem('link')"
                >
                    <Plus data-icon="inline-start" />
                    Ajouter le premier lien
                </Button>
            </template>
        </EmptyState>

        <div v-else class="flex flex-col gap-2">
            <Collapsible
                v-for="(item, index) in items"
                :key="itemId(index)"
                :open="isItemOpen(itemId(index))"
                data-test="navigation-item-collapsible"
                @update:open="setItemOpen(itemId(index), $event)"
            >
                <Card class="gap-0 overflow-hidden py-0">
                    <CardHeader class="gap-1 px-4 py-3 sm:px-5">
                        <CardTitle
                            class="flex min-w-0 flex-wrap items-center gap-2 text-sm"
                        >
                            <span class="truncate">
                                {{ index + 1 }}.
                                {{ item.label || `Élément ${index + 1}` }}
                            </span>
                            <Badge
                                :variant="
                                    itemHasErrors(index)
                                        ? 'destructive'
                                        : 'outline'
                                "
                            >
                                {{
                                    itemHasErrors(index)
                                        ? 'À corriger'
                                        : item.type === 'group'
                                          ? 'Menu'
                                          : 'Lien'
                                }}
                            </Badge>
                        </CardTitle>
                        <CardDescription
                            class="truncate text-xs"
                            data-test="navigation-item-summary"
                        >
                            {{ itemSummary(item) }}
                        </CardDescription>
                        <CardAction class="flex gap-1">
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon-sm"
                                :disabled="index === 0"
                                :aria-label="`Monter l’élément ${index + 1}`"
                                :title="`Monter l’élément ${index + 1}`"
                                @click="moveItem(index, -1)"
                            >
                                <ArrowUp />
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon-sm"
                                :disabled="index === items.length - 1"
                                :aria-label="`Descendre l’élément ${index + 1}`"
                                :title="`Descendre l’élément ${index + 1}`"
                                @click="moveItem(index, 1)"
                            >
                                <ArrowDown />
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon-sm"
                                :aria-label="`Retirer l’élément ${index + 1}`"
                                :title="`Retirer l’élément ${index + 1}`"
                                @click="removeItem(index)"
                            >
                                <Trash2 />
                            </Button>
                            <CollapsibleTrigger as-child>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon-sm"
                                    :aria-label="
                                        isItemOpen(itemId(index))
                                            ? `Réduire l’élément ${index + 1}`
                                            : `Développer l’élément ${index + 1}`
                                    "
                                    :title="
                                        isItemOpen(itemId(index))
                                            ? 'Réduire'
                                            : 'Développer'
                                    "
                                >
                                    <ChevronDown
                                        :class="
                                            cn(
                                                'transition-transform',
                                                isItemOpen(itemId(index)) &&
                                                    'rotate-180',
                                            )
                                        "
                                    />
                                </Button>
                            </CollapsibleTrigger>
                        </CardAction>
                    </CardHeader>

                    <CollapsibleContent data-test="navigation-item-editor">
                        <Separator />
                        <CardContent
                            class="flex flex-col gap-4 px-4 py-4 sm:px-5"
                        >
                            <FieldGroup class="gap-4 sm:grid sm:grid-cols-2">
                                <Field
                                    class="gap-2 sm:col-span-2"
                                    :data-invalid="
                                        Boolean(error(`items.${index}.type`))
                                    "
                                >
                                    <FieldLabel
                                        :id="`item-${index}-type-label`"
                                    >
                                        Type d’élément
                                    </FieldLabel>
                                    <ToggleGroup
                                        type="single"
                                        variant="outline"
                                        size="sm"
                                        :model-value="item.type"
                                        :aria-labelledby="`item-${index}-type-label`"
                                        @update:model-value="
                                            changeItemType(index, $event)
                                        "
                                    >
                                        <ToggleGroupItem value="link">
                                            <Link2 />
                                            Lien direct
                                        </ToggleGroupItem>
                                        <ToggleGroupItem value="group">
                                            <FolderTree />
                                            Menu déroulant
                                        </ToggleGroupItem>
                                    </ToggleGroup>
                                    <FieldError
                                        :errors="[error(`items.${index}.type`)]"
                                    />
                                </Field>

                                <Field
                                    :class="
                                        cn(
                                            'gap-2',
                                            item.type === 'group' &&
                                                'sm:col-span-2',
                                        )
                                    "
                                    :data-invalid="
                                        Boolean(error(`items.${index}.label`))
                                    "
                                >
                                    <FieldLabel :for="`item-${index}-label`">
                                        Libellé
                                    </FieldLabel>
                                    <Input
                                        :id="`item-${index}-label`"
                                        v-model="item.label"
                                        maxlength="80"
                                        autocomplete="off"
                                        :aria-invalid="
                                            Boolean(
                                                error(`items.${index}.label`),
                                            )
                                        "
                                        placeholder="Ex. Découvrir"
                                        @update:model-value="emit('change')"
                                    />
                                    <FieldError
                                        :errors="[
                                            error(`items.${index}.label`),
                                        ]"
                                    />
                                </Field>

                                <Field
                                    v-if="item.type === 'link'"
                                    class="gap-2"
                                    :data-invalid="
                                        Boolean(error(`items.${index}.url`))
                                    "
                                >
                                    <FieldLabel :for="`item-${index}-url`">
                                        Destination
                                    </FieldLabel>
                                    <Input
                                        :id="`item-${index}-url`"
                                        :model-value="item.url ?? ''"
                                        type="text"
                                        inputmode="url"
                                        maxlength="2048"
                                        autocomplete="url"
                                        autocapitalize="none"
                                        spellcheck="false"
                                        :aria-invalid="
                                            Boolean(error(`items.${index}.url`))
                                        "
                                        placeholder="Ex. #contact, /a-propos ou https://exemple.com"
                                        @update:model-value="
                                            updateItemUrl(index, $event)
                                        "
                                    />
                                    <FieldDescription class="text-xs">
                                        Ancre d’accueil, chemin interne ou URL
                                        HTTP(S).
                                    </FieldDescription>
                                    <FieldError
                                        :errors="[error(`items.${index}.url`)]"
                                    />
                                </Field>
                            </FieldGroup>

                            <section
                                v-if="item.type === 'group'"
                                :aria-labelledby="`item-${index}-children-title`"
                                class="flex flex-col gap-3"
                            >
                                <div
                                    class="flex flex-wrap items-center justify-between gap-3"
                                >
                                    <div class="flex flex-col gap-0.5">
                                        <h3
                                            :id="`item-${index}-children-title`"
                                            class="text-sm font-medium"
                                        >
                                            Sous-liens
                                        </h3>
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            Affichés dans le panneau déroulant.
                                        </p>
                                    </div>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        :disabled="item.children.length >= 8"
                                        @click="addChild(index)"
                                    >
                                        <Plus data-icon="inline-start" />
                                        Ajouter
                                    </Button>
                                </div>

                                <FieldError
                                    :errors="[error(`items.${index}.children`)]"
                                />

                                <div class="flex flex-col gap-2">
                                    <Card
                                        v-for="(
                                            child, childIndex
                                        ) in item.children"
                                        :key="childIndex"
                                        class="gap-0 py-0 shadow-none"
                                    >
                                        <CardHeader
                                            class="gap-0.5 px-3 py-2.5 sm:px-4"
                                        >
                                            <CardTitle class="truncate text-sm">
                                                {{
                                                    child.label ||
                                                    `Sous-lien ${childIndex + 1}`
                                                }}
                                            </CardTitle>
                                            <CardDescription
                                                class="truncate text-xs"
                                            >
                                                {{
                                                    child.url ||
                                                    `Position ${childIndex + 1}`
                                                }}
                                            </CardDescription>
                                            <CardAction class="flex gap-1">
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon-sm"
                                                    :disabled="childIndex === 0"
                                                    :aria-label="`Monter le sous-lien ${childIndex + 1}`"
                                                    @click="
                                                        moveChild(
                                                            index,
                                                            childIndex,
                                                            -1,
                                                        )
                                                    "
                                                >
                                                    <ArrowUp />
                                                </Button>
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon-sm"
                                                    :disabled="
                                                        childIndex ===
                                                        item.children.length - 1
                                                    "
                                                    :aria-label="`Descendre le sous-lien ${childIndex + 1}`"
                                                    @click="
                                                        moveChild(
                                                            index,
                                                            childIndex,
                                                            1,
                                                        )
                                                    "
                                                >
                                                    <ArrowDown />
                                                </Button>
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon-sm"
                                                    :aria-label="`Retirer le sous-lien ${childIndex + 1}`"
                                                    @click="
                                                        removeChild(
                                                            index,
                                                            childIndex,
                                                        )
                                                    "
                                                >
                                                    <Trash2 />
                                                </Button>
                                            </CardAction>
                                        </CardHeader>
                                        <Separator />
                                        <CardContent class="px-3 py-3 sm:px-4">
                                            <FieldGroup
                                                class="gap-3 sm:grid sm:grid-cols-2"
                                            >
                                                <Field
                                                    class="gap-2"
                                                    :data-invalid="
                                                        Boolean(
                                                            error(
                                                                `items.${index}.children.${childIndex}.label`,
                                                            ),
                                                        )
                                                    "
                                                >
                                                    <FieldLabel
                                                        :for="`item-${index}-child-${childIndex}-label`"
                                                    >
                                                        Libellé
                                                    </FieldLabel>
                                                    <Input
                                                        :id="`item-${index}-child-${childIndex}-label`"
                                                        v-model="child.label"
                                                        maxlength="80"
                                                        autocomplete="off"
                                                        :aria-invalid="
                                                            Boolean(
                                                                error(
                                                                    `items.${index}.children.${childIndex}.label`,
                                                                ),
                                                            )
                                                        "
                                                        placeholder="Ex. Notre équipe"
                                                        @update:model-value="
                                                            emit('change')
                                                        "
                                                    />
                                                    <FieldError
                                                        :errors="[
                                                            error(
                                                                `items.${index}.children.${childIndex}.label`,
                                                            ),
                                                        ]"
                                                    />
                                                </Field>

                                                <Field
                                                    class="gap-2"
                                                    :data-invalid="
                                                        Boolean(
                                                            error(
                                                                `items.${index}.children.${childIndex}.url`,
                                                            ),
                                                        )
                                                    "
                                                >
                                                    <FieldLabel
                                                        :for="`item-${index}-child-${childIndex}-url`"
                                                    >
                                                        Destination
                                                    </FieldLabel>
                                                    <Input
                                                        :id="`item-${index}-child-${childIndex}-url`"
                                                        v-model="child.url"
                                                        type="text"
                                                        inputmode="url"
                                                        maxlength="2048"
                                                        autocomplete="url"
                                                        autocapitalize="none"
                                                        spellcheck="false"
                                                        :aria-invalid="
                                                            Boolean(
                                                                error(
                                                                    `items.${index}.children.${childIndex}.url`,
                                                                ),
                                                            )
                                                        "
                                                        placeholder="Ex. /equipe"
                                                        @update:model-value="
                                                            emit('change')
                                                        "
                                                    />
                                                    <FieldError
                                                        :errors="[
                                                            error(
                                                                `items.${index}.children.${childIndex}.url`,
                                                            ),
                                                        ]"
                                                    />
                                                </Field>

                                                <Field
                                                    class="gap-2 sm:col-span-2"
                                                    :data-invalid="
                                                        Boolean(
                                                            error(
                                                                `items.${index}.children.${childIndex}.description`,
                                                            ),
                                                        )
                                                    "
                                                >
                                                    <FieldLabel
                                                        :for="`item-${index}-child-${childIndex}-description`"
                                                    >
                                                        Description
                                                    </FieldLabel>
                                                    <Input
                                                        :id="`item-${index}-child-${childIndex}-description`"
                                                        v-model="
                                                            child.description
                                                        "
                                                        maxlength="160"
                                                        autocomplete="off"
                                                        :aria-invalid="
                                                            Boolean(
                                                                error(
                                                                    `items.${index}.children.${childIndex}.description`,
                                                                ),
                                                            )
                                                        "
                                                        placeholder="Facultatif"
                                                        @update:model-value="
                                                            emit('change')
                                                        "
                                                    />
                                                    <FieldError
                                                        :errors="[
                                                            error(
                                                                `items.${index}.children.${childIndex}.description`,
                                                            ),
                                                        ]"
                                                    />
                                                </Field>
                                            </FieldGroup>
                                        </CardContent>
                                    </Card>
                                </div>
                            </section>
                        </CardContent>
                    </CollapsibleContent>
                </Card>
            </Collapsible>
        </div>

        <Card v-if="items.length > 0" class="gap-3 py-4">
            <CardHeader class="px-4 sm:px-5">
                <CardTitle class="text-sm">Aperçu de la structure</CardTitle>
                <CardDescription class="text-xs">
                    L’ordre repris sur ordinateur et mobile.
                </CardDescription>
            </CardHeader>
            <CardContent class="px-4 sm:px-5">
                <div class="flex flex-wrap items-center gap-2">
                    <template v-for="(item, index) in items" :key="index">
                        <Badge variant="secondary">
                            {{ item.label || `Élément ${index + 1}` }}
                            <ChevronRight v-if="item.type === 'group'" />
                        </Badge>
                    </template>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
