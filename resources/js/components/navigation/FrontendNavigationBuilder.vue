<script setup lang="ts">
import {
    ArrowDown,
    ArrowUp,
    ArrowUpToLine,
    ChevronDown,
    ChevronsUpDown,
    Copy,
    ExternalLink,
    FileText,
    FolderInput,
    FolderTree,
    GripVertical,
    Hash,
    Link2,
    ListTree,
    MoreHorizontal,
    Plus,
    Trash2,
} from '@lucide/vue';
import { computed, nextTick, ref, toRaw, watch } from 'vue';
import { toast } from 'vue-sonner';
import EmptyState from '@/components/application/EmptyState.vue';
import FrontendNavigationDestinationField from '@/components/navigation/FrontendNavigationDestinationField.vue';
import FrontendNavigationPreview from '@/components/navigation/FrontendNavigationPreview.vue';
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
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuSub,
    DropdownMenuSubContent,
    DropdownMenuSubTrigger,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Field,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Separator } from '@/components/ui/separator';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import {
    FRONTEND_NAVIGATION_MAX_CHILDREN,
    FRONTEND_NAVIGATION_MAX_ITEMS,
    frontendNavigationDestinationKind,
    frontendNavigationDestinationLabel,
    isUnknownFrontendNavigationPage,
} from '@/lib/frontendNavigation';
import { cn } from '@/lib/utils';
import type {
    FrontendNavigationChild,
    FrontendNavigationDestination,
    FrontendNavigationDestinations,
    FrontendNavigationItem,
} from '@/types';

type DragSource =
    | { kind: 'item'; index: number }
    | { kind: 'child'; itemIndex: number; childIndex: number };

type DropTarget =
    | { kind: 'item'; index: number; position: 'before' | 'after' }
    | {
          kind: 'child';
          itemIndex: number;
          childIndex: number;
          position: 'before' | 'after';
      };

const props = defineProps<{
    errors: Record<string, string | undefined>;
    destinations: FrontendNavigationDestinations;
}>();

const emit = defineEmits<{
    change: [];
}>();

const items = defineModel<FrontendNavigationItem[]>({ required: true });

const entryKeys = new WeakMap<object, string>();
let nextEntryKey = 0;

/**
 * Stable identifier of an item or child, kept across reordering and moves.
 */
function keyOf(entry: object): string {
    const raw = toRaw(entry);
    let key = entryKeys.get(raw);

    if (!key) {
        nextEntryKey += 1;
        key = `navigation-entry-${nextEntryKey}`;
        entryKeys.set(raw, key);
    }

    return key;
}

const openKeys = ref<string[]>([]);
const dragSource = ref<DragSource | null>(null);
const dropTarget = ref<DropTarget | null>(null);

const allItemsOpen = computed(
    () =>
        items.value.length > 0 &&
        items.value.every((item) => openKeys.value.includes(keyOf(item))),
);
const canAddItem = computed(
    () => items.value.length < FRONTEND_NAVIGATION_MAX_ITEMS,
);

watch(
    () => Object.keys(props.errors),
    (errorPaths) => {
        const nextOpenKeys = new Set(openKeys.value);

        errorPaths.forEach((path) => {
            const match = path.match(/^items\.(\d+)(?:\.|$)/);
            const item = match?.[1] ? items.value[Number(match[1])] : undefined;

            if (item) {
                nextOpenKeys.add(keyOf(item));
            }
        });

        openKeys.value = [...nextOpenKeys];
    },
);

function changed(): void {
    emit('change');
}

function emptyChild(): FrontendNavigationChild {
    return {
        label: '',
        url: '',
        description: '',
    };
}

function isOpen(item: FrontendNavigationItem): boolean {
    return openKeys.value.includes(keyOf(item));
}

function setOpen(item: FrontendNavigationItem, open: boolean): void {
    const key = keyOf(item);

    openKeys.value = open
        ? [...new Set([...openKeys.value, key])]
        : openKeys.value.filter((openKey) => openKey !== key);
}

function toggleAllItems(): void {
    openKeys.value = allItemsOpen.value ? [] : items.value.map(keyOf);
}

function focusField(id: string): void {
    void nextTick(() => document.getElementById(id)?.focus());
}

function error(path: string): string | undefined {
    return props.errors[path];
}

function itemHasErrors(index: number): boolean {
    const prefix = `items.${index}`;

    return Object.keys(props.errors).some(
        (path) => path === prefix || path.startsWith(`${prefix}.`),
    );
}

function destinationHasWarning(url: string | null): boolean {
    return (
        frontendNavigationDestinationKind(url) === 'invalid' ||
        isUnknownFrontendNavigationPage(url, props.destinations)
    );
}

function itemNeedsAttention(item: FrontendNavigationItem): boolean {
    if (item.type === 'link') {
        return destinationHasWarning(item.url);
    }

    return (
        item.children.length === 0 ||
        item.children.some((child) => destinationHasWarning(child.url))
    );
}

function itemSummary(item: FrontendNavigationItem): string {
    if (item.type === 'group') {
        const count = item.children.length;

        if (count === 0) {
            return 'Aucun sous-lien';
        }

        const labels = item.children
            .map((child) => child.label.trim())
            .filter(Boolean)
            .join(', ');

        return `${count} sous-lien${count > 1 ? 's' : ''}${labels ? ` · ${labels}` : ''}`;
    }

    return destinationSummary(item.url);
}

function destinationSummary(url: string | null): string {
    const destination = url?.trim() ?? '';

    if (destination === '') {
        return 'Destination à définir';
    }

    const label = frontendNavigationDestinationLabel(
        destination,
        props.destinations,
    );

    return label ? `${label} · ${destination}` : destination;
}

function destinationIcon(url: string | null) {
    switch (frontendNavigationDestinationKind(url)) {
        case 'anchor':
            return Hash;
        case 'internal':
            return FileText;
        case 'external':
            return ExternalLink;
        default:
            return Link2;
    }
}

function copyItem(item: FrontendNavigationItem): FrontendNavigationItem {
    return {
        ...item,
        children: item.children.map((child) => ({ ...child })),
    };
}

function addItem(type: FrontendNavigationItem['type']): void {
    if (!canAddItem.value) {
        return;
    }

    const item: FrontendNavigationItem = {
        type,
        label: '',
        url: type === 'link' ? '' : null,
        children: type === 'group' ? [emptyChild()] : [],
    };

    items.value.push(item);
    setOpen(item, true);
    focusField(`${keyOf(item)}-label`);
    changed();
}

function duplicateItem(index: number): void {
    const item = items.value[index];

    if (!item || !canAddItem.value) {
        return;
    }

    const copy = copyItem(item);
    copy.label = item.label ? `${item.label} (copie)`.slice(0, 80) : '';

    items.value.splice(index + 1, 0, copy);
    setOpen(copy, true);
    focusField(`${keyOf(copy)}-label`);
    changed();
}

function removeItem(index: number): void {
    const [removed] = items.value.splice(index, 1);

    if (!removed) {
        return;
    }

    changed();
    toast(`« ${removed.label || `Élément ${index + 1}`} » retiré du menu.`, {
        action: {
            label: 'Annuler',
            onClick: () => {
                if (items.value.length >= FRONTEND_NAVIGATION_MAX_ITEMS) {
                    return;
                }

                items.value.splice(
                    Math.min(index, items.value.length),
                    0,
                    removed,
                );
                changed();
            },
        },
    });
}

function moveItem(from: number, to: number): void {
    if (
        from === to ||
        to < 0 ||
        to >= items.value.length ||
        !items.value[from]
    ) {
        return;
    }

    const [item] = items.value.splice(from, 1);

    if (item) {
        items.value.splice(to, 0, item);
        changed();
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

    if (value === 'group') {
        const url = item.url?.trim() ?? '';

        item.type = 'group';
        item.children = [
            url !== ''
                ? { label: item.label, url, description: '' }
                : emptyChild(),
        ];
        item.url = null;
        changed();

        return;
    }

    const previousChildren = item.children;
    const discarded = previousChildren.filter(
        (child) => child.label || child.url || child.description,
    );

    item.type = 'link';
    item.url = previousChildren[0]?.url ?? '';
    item.children = [];
    changed();

    if (discarded.length > 0) {
        toast(
            `${discarded.length} sous-lien${discarded.length > 1 ? 's retirés' : ' retiré'} en transformant le menu en lien.`,
            {
                action: {
                    label: 'Annuler',
                    onClick: () => {
                        item.type = 'group';
                        item.url = null;
                        item.children = previousChildren;
                        changed();
                    },
                },
            },
        );
    }
}

function updateItemUrl(item: FrontendNavigationItem, value: string): void {
    item.url = value;
    changed();
}

function applyDestination(
    entry: FrontendNavigationItem | FrontendNavigationChild,
    destination: FrontendNavigationDestination,
): void {
    if (entry.label.trim() === '') {
        entry.label = destination.label.slice(0, 80);
    }

    changed();
}

function addChild(index: number): void {
    const item = items.value[index];

    if (
        !item ||
        item.type !== 'group' ||
        item.children.length >= FRONTEND_NAVIGATION_MAX_CHILDREN
    ) {
        return;
    }

    const child = emptyChild();

    item.children.push(child);
    focusField(`${keyOf(child)}-label`);
    changed();
}

function removeChild(itemIndex: number, childIndex: number): void {
    const children = items.value[itemIndex]?.children;
    const [removed] = children?.splice(childIndex, 1) ?? [];

    if (!children || !removed) {
        return;
    }

    changed();
    toast(
        `« ${removed.label || `Sous-lien ${childIndex + 1}`} » retiré du menu.`,
        {
            action: {
                label: 'Annuler',
                onClick: () => {
                    if (children.length >= FRONTEND_NAVIGATION_MAX_CHILDREN) {
                        return;
                    }

                    children.splice(
                        Math.min(childIndex, children.length),
                        0,
                        removed,
                    );
                    changed();
                },
            },
        },
    );
}

function moveChild(
    fromItemIndex: number,
    fromChildIndex: number,
    toItemIndex: number,
    toChildIndex: number,
): void {
    const source = items.value[fromItemIndex];
    const target = items.value[toItemIndex];

    if (!source || !target || target.type !== 'group') {
        return;
    }

    const sameGroup = fromItemIndex === toItemIndex;

    if (
        !sameGroup &&
        target.children.length >= FRONTEND_NAVIGATION_MAX_CHILDREN
    ) {
        return;
    }

    if (sameGroup && fromChildIndex === toChildIndex) {
        return;
    }

    const [child] = source.children.splice(fromChildIndex, 1);

    if (!child) {
        return;
    }

    target.children.splice(
        Math.max(0, Math.min(toChildIndex, target.children.length)),
        0,
        child,
    );

    if (!sameGroup) {
        setOpen(target, true);
    }

    changed();
}

function moveChildToGroup(
    itemIndex: number,
    childIndex: number,
    targetIndex: number,
): void {
    const target = items.value[targetIndex];

    if (target) {
        moveChild(itemIndex, childIndex, targetIndex, target.children.length);
    }
}

function promoteChild(itemIndex: number, childIndex: number): void {
    const group = items.value[itemIndex];

    if (!group || !canAddItem.value) {
        return;
    }

    const [child] = group.children.splice(childIndex, 1);

    if (!child) {
        return;
    }

    const link: FrontendNavigationItem = {
        type: 'link',
        label: child.label,
        url: child.url,
        children: [],
    };

    items.value.splice(itemIndex + 1, 0, link);
    setOpen(link, true);
    changed();
}

function otherGroups(itemIndex: number): { index: number; label: string }[] {
    return items.value
        .map((item, index) => ({ item, index }))
        .filter(
            ({ item, index }) =>
                item.type === 'group' &&
                index !== itemIndex &&
                item.children.length < FRONTEND_NAVIGATION_MAX_CHILDREN,
        )
        .map(({ item, index }) => ({
            index,
            label: item.label || `Menu ${index + 1}`,
        }));
}

/*
 * Drag and drop
 */
function dropPosition(event: DragEvent): 'before' | 'after' {
    const element = event.currentTarget as HTMLElement;
    const rect = element.getBoundingClientRect();

    return event.clientY < rect.top + rect.height / 2 ? 'before' : 'after';
}

function startDrag(event: DragEvent, source: DragSource): void {
    const handle = event.currentTarget as HTMLElement;
    const card = handle.closest<HTMLElement>('[data-drag-preview]');

    dragSource.value = source;

    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', '');

        if (card) {
            event.dataTransfer.setDragImage(card, 24, 24);
        }
    }
}

function endDrag(): void {
    dragSource.value = null;
    dropTarget.value = null;
}

function dragOverItem(event: DragEvent, index: number): void {
    if (dragSource.value?.kind !== 'item') {
        return;
    }

    event.preventDefault();
    dropTarget.value = { kind: 'item', index, position: dropPosition(event) };
}

function dropOnItem(): void {
    const source = dragSource.value;
    const target = dropTarget.value;

    if (source?.kind === 'item' && target?.kind === 'item') {
        let destination =
            target.position === 'after' ? target.index + 1 : target.index;

        if (source.index < destination) {
            destination -= 1;
        }

        moveItem(source.index, destination);
    }

    endDrag();
}

function canDropChildInto(itemIndex: number): boolean {
    const source = dragSource.value;
    const target = items.value[itemIndex];

    return (
        source?.kind === 'child' &&
        target?.type === 'group' &&
        (source.itemIndex === itemIndex ||
            target.children.length < FRONTEND_NAVIGATION_MAX_CHILDREN)
    );
}

function dragOverChild(
    event: DragEvent,
    itemIndex: number,
    childIndex: number,
): void {
    if (!canDropChildInto(itemIndex)) {
        return;
    }

    event.preventDefault();
    event.stopPropagation();
    dropTarget.value = {
        kind: 'child',
        itemIndex,
        childIndex,
        position: dropPosition(event),
    };
}

function dragOverEmptyChildren(event: DragEvent, itemIndex: number): void {
    if (!canDropChildInto(itemIndex)) {
        return;
    }

    event.preventDefault();
    dropTarget.value = {
        kind: 'child',
        itemIndex,
        childIndex: 0,
        position: 'before',
    };
}

function dropOnChild(): void {
    const source = dragSource.value;
    const target = dropTarget.value;

    if (source?.kind === 'child' && target?.kind === 'child') {
        let destination =
            target.position === 'after'
                ? target.childIndex + 1
                : target.childIndex;

        if (
            source.itemIndex === target.itemIndex &&
            source.childIndex < destination
        ) {
            destination -= 1;
        }

        moveChild(
            source.itemIndex,
            source.childIndex,
            target.itemIndex,
            destination,
        );
    }

    endDrag();
}

function isDropIndicator(target: DropTarget): boolean {
    const current = dropTarget.value;

    if (!current || current.kind !== target.kind) {
        return false;
    }

    if (current.kind === 'item' && target.kind === 'item') {
        return (
            current.index === target.index &&
            current.position === target.position
        );
    }

    return (
        current.kind === 'child' &&
        target.kind === 'child' &&
        current.itemIndex === target.itemIndex &&
        current.childIndex === target.childIndex &&
        current.position === target.position
    );
}

function isDragged(source: DragSource): boolean {
    const current = dragSource.value;

    if (!current || current.kind !== source.kind) {
        return false;
    }

    if (current.kind === 'item' && source.kind === 'item') {
        return current.index === source.index;
    }

    return (
        current.kind === 'child' &&
        source.kind === 'child' &&
        current.itemIndex === source.itemIndex &&
        current.childIndex === source.childIndex
    );
}

/**
 * Keyboard alternative to dragging: arrow keys on the handle move the entry.
 */
function moveItemWithKeyboard(index: number, offset: -1 | 1): void {
    const item = items.value[index];

    moveItem(index, index + offset);

    if (item) {
        focusField(`${keyOf(item)}-handle`);
    }
}

function moveChildWithKeyboard(
    itemIndex: number,
    childIndex: number,
    offset: -1 | 1,
): void {
    const child = items.value[itemIndex]?.children[childIndex];
    const destination = childIndex + offset;

    if (
        !child ||
        destination < 0 ||
        destination >= (items.value[itemIndex]?.children.length ?? 0)
    ) {
        return;
    }

    moveChild(itemIndex, childIndex, itemIndex, destination);
    focusField(`${keyOf(child)}-handle`);
}
</script>

<template>
    <div class="flex flex-col gap-6">
        <FrontendNavigationPreview :items="items" />

        <Separator />

        <div class="flex flex-col gap-4">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div class="flex flex-col gap-0.5">
                    <h2 class="text-sm font-medium">Éléments du menu</h2>
                    <p
                        class="text-muted-foreground text-xs"
                        data-test="navigation-item-count"
                    >
                        {{ items.length }} / {{ FRONTEND_NAVIGATION_MAX_ITEMS }}
                        éléments · jusqu’à
                        {{ FRONTEND_NAVIGATION_MAX_CHILDREN }} sous-liens par
                        menu. Glissez la poignée pour réorganiser.
                    </p>
                </div>
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
                        :disabled="!canAddItem"
                        @click="addItem('link')"
                    >
                        <Link2 data-icon="inline-start" />
                        Ajouter un lien
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        :disabled="!canAddItem"
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

            <ol
                v-else
                class="flex flex-col gap-2"
                aria-label="Éléments du menu"
            >
                <li
                    v-for="(item, index) in items"
                    :key="keyOf(item)"
                    class="relative"
                    data-test="navigation-item"
                    @dragover="dragOverItem($event, index)"
                    @drop.prevent="dropOnItem"
                >
                    <div
                        v-if="
                            isDropIndicator({
                                kind: 'item',
                                index,
                                position: 'before',
                            })
                        "
                        class="bg-primary absolute inset-x-0 -top-1.5 h-0.5 rounded-full"
                        aria-hidden="true"
                    />
                    <Collapsible
                        :open="isOpen(item)"
                        data-test="navigation-item-collapsible"
                        @update:open="setOpen(item, $event)"
                    >
                        <Card
                            data-drag-preview
                            :class="
                                cn(
                                    'gap-0 overflow-hidden py-0 transition-opacity',
                                    isDragged({ kind: 'item', index }) &&
                                        'opacity-50',
                                    itemHasErrors(index) &&
                                        'border-destructive/60',
                                )
                            "
                        >
                            <CardHeader
                                class="flex items-center gap-2 px-2 py-2.5 sm:px-3"
                            >
                                <button
                                    :id="`${keyOf(item)}-handle`"
                                    type="button"
                                    draggable="true"
                                    class="text-muted-foreground hover:bg-accent hover:text-foreground focus-visible:ring-ring flex size-8 shrink-0 cursor-grab items-center justify-center rounded-md outline-none focus-visible:ring-2 active:cursor-grabbing"
                                    :aria-label="`Réorganiser « ${item.label || `Élément ${index + 1}`} » (position ${index + 1} sur ${items.length}). Utilisez les flèches haut et bas.`"
                                    title="Glisser pour réorganiser"
                                    data-test="navigation-item-handle"
                                    @dragstart="
                                        startDrag($event, {
                                            kind: 'item',
                                            index,
                                        })
                                    "
                                    @dragend="endDrag"
                                    @keydown.up.prevent="
                                        moveItemWithKeyboard(index, -1)
                                    "
                                    @keydown.down.prevent="
                                        moveItemWithKeyboard(index, 1)
                                    "
                                >
                                    <GripVertical class="size-4" />
                                </button>

                                <CollapsibleTrigger
                                    class="focus-visible:ring-ring flex min-w-0 flex-1 flex-col items-start gap-0.5 rounded-md py-0.5 text-left outline-none focus-visible:ring-2"
                                >
                                    <CardTitle
                                        class="flex w-full min-w-0 items-center gap-2 text-sm"
                                    >
                                        <component
                                            :is="
                                                item.type === 'group'
                                                    ? FolderTree
                                                    : destinationIcon(item.url)
                                            "
                                            class="text-muted-foreground size-4 shrink-0"
                                            aria-hidden="true"
                                        />
                                        <span class="truncate">
                                            {{
                                                item.label ||
                                                (item.type === 'group'
                                                    ? 'Nouveau menu'
                                                    : 'Nouveau lien')
                                            }}
                                        </span>
                                        <Badge
                                            v-if="itemHasErrors(index)"
                                            variant="destructive"
                                        >
                                            À corriger
                                        </Badge>
                                        <Badge
                                            v-else-if="itemNeedsAttention(item)"
                                            variant="outline"
                                            class="border-amber-500/50 text-amber-700 dark:text-amber-400"
                                        >
                                            À vérifier
                                        </Badge>
                                        <Badge v-else variant="outline">
                                            {{
                                                item.type === 'group'
                                                    ? 'Menu'
                                                    : 'Lien'
                                            }}
                                        </Badge>
                                    </CardTitle>
                                    <CardDescription
                                        class="w-full truncate pl-6 text-xs"
                                        data-test="navigation-item-summary"
                                    >
                                        {{ itemSummary(item) }}
                                    </CardDescription>
                                </CollapsibleTrigger>

                                <CardAction
                                    class="flex shrink-0 items-center gap-1 self-center"
                                >
                                    <DropdownMenu>
                                        <DropdownMenuTrigger as-child>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon-sm"
                                                :aria-label="`Actions pour « ${item.label || `Élément ${index + 1}`} »`"
                                                data-test="navigation-item-actions"
                                            >
                                                <MoreHorizontal />
                                            </Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent
                                            align="end"
                                            class="w-52"
                                        >
                                            <DropdownMenuItem
                                                :disabled="index === 0"
                                                @select="
                                                    moveItem(index, index - 1)
                                                "
                                            >
                                                <ArrowUp />
                                                Monter
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                :disabled="
                                                    index === items.length - 1
                                                "
                                                @select="
                                                    moveItem(index, index + 1)
                                                "
                                            >
                                                <ArrowDown />
                                                Descendre
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                :disabled="!canAddItem"
                                                @select="duplicateItem(index)"
                                            >
                                                <Copy />
                                                Dupliquer
                                            </DropdownMenuItem>
                                            <DropdownMenuSeparator />
                                            <DropdownMenuItem
                                                variant="destructive"
                                                @select="removeItem(index)"
                                            >
                                                <Trash2 />
                                                Supprimer
                                            </DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                    <CollapsibleTrigger as-child>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon-sm"
                                            :aria-label="
                                                isOpen(item)
                                                    ? `Réduire « ${item.label || `Élément ${index + 1}`} »`
                                                    : `Modifier « ${item.label || `Élément ${index + 1}`} »`
                                            "
                                        >
                                            <ChevronDown
                                                :class="
                                                    cn(
                                                        'transition-transform',
                                                        isOpen(item) &&
                                                            'rotate-180',
                                                    )
                                                "
                                            />
                                        </Button>
                                    </CollapsibleTrigger>
                                </CardAction>
                            </CardHeader>

                            <CollapsibleContent
                                data-test="navigation-item-editor"
                            >
                                <Separator />
                                <CardContent
                                    class="flex flex-col gap-4 px-4 py-4 sm:px-5"
                                >
                                    <FieldGroup
                                        class="gap-4 sm:grid sm:grid-cols-2"
                                    >
                                        <Field
                                            class="gap-2 sm:col-span-2"
                                            :data-invalid="
                                                Boolean(
                                                    error(
                                                        `items.${index}.type`,
                                                    ),
                                                )
                                            "
                                        >
                                            <FieldLabel
                                                :id="`${keyOf(item)}-type-label`"
                                            >
                                                Type d’élément
                                            </FieldLabel>
                                            <ToggleGroup
                                                type="single"
                                                variant="outline"
                                                size="sm"
                                                class="w-fit"
                                                :model-value="item.type"
                                                :aria-labelledby="`${keyOf(item)}-type-label`"
                                                @update:model-value="
                                                    changeItemType(
                                                        index,
                                                        $event,
                                                    )
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
                                                :errors="[
                                                    error(
                                                        `items.${index}.type`,
                                                    ),
                                                ]"
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
                                                Boolean(
                                                    error(
                                                        `items.${index}.label`,
                                                    ),
                                                )
                                            "
                                        >
                                            <FieldLabel
                                                :for="`${keyOf(item)}-label`"
                                            >
                                                Libellé
                                            </FieldLabel>
                                            <Input
                                                :id="`${keyOf(item)}-label`"
                                                v-model="item.label"
                                                maxlength="80"
                                                autocomplete="off"
                                                :aria-invalid="
                                                    Boolean(
                                                        error(
                                                            `items.${index}.label`,
                                                        ),
                                                    )
                                                "
                                                :placeholder="
                                                    item.type === 'group'
                                                        ? 'Ex. Découvrir'
                                                        : 'Ex. Contact'
                                                "
                                                @update:model-value="changed"
                                            />
                                            <FieldError
                                                :errors="[
                                                    error(
                                                        `items.${index}.label`,
                                                    ),
                                                ]"
                                            />
                                        </Field>

                                        <FrontendNavigationDestinationField
                                            v-if="item.type === 'link'"
                                            :id="`${keyOf(item)}-url`"
                                            :model-value="item.url ?? ''"
                                            :destinations="destinations"
                                            :error="error(`items.${index}.url`)"
                                            @update:model-value="
                                                updateItemUrl(item, $event)
                                            "
                                            @pick="
                                                applyDestination(item, $event)
                                            "
                                        />
                                    </FieldGroup>

                                    <section
                                        v-if="item.type === 'group'"
                                        :aria-labelledby="`${keyOf(item)}-children-title`"
                                        class="flex flex-col gap-3"
                                    >
                                        <div
                                            class="flex flex-wrap items-center justify-between gap-3"
                                        >
                                            <div class="flex flex-col gap-0.5">
                                                <h3
                                                    :id="`${keyOf(item)}-children-title`"
                                                    class="text-sm font-medium"
                                                >
                                                    Sous-liens
                                                    <span
                                                        class="text-muted-foreground font-normal"
                                                    >
                                                        ({{
                                                            item.children
                                                                .length
                                                        }}/{{
                                                            FRONTEND_NAVIGATION_MAX_CHILDREN
                                                        }})
                                                    </span>
                                                </h3>
                                                <p
                                                    class="text-muted-foreground text-xs"
                                                >
                                                    Affichés dans le panneau
                                                    déroulant. Glissez-les vers
                                                    un autre menu pour les
                                                    déplacer.
                                                </p>
                                            </div>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                :disabled="
                                                    item.children.length >=
                                                    FRONTEND_NAVIGATION_MAX_CHILDREN
                                                "
                                                @click="addChild(index)"
                                            >
                                                <Plus
                                                    data-icon="inline-start"
                                                />
                                                Ajouter un sous-lien
                                            </Button>
                                        </div>

                                        <FieldError
                                            :errors="[
                                                error(
                                                    `items.${index}.children`,
                                                ),
                                            ]"
                                        />

                                        <div
                                            v-if="item.children.length === 0"
                                            :class="
                                                cn(
                                                    'text-muted-foreground rounded-lg border border-dashed px-4 py-6 text-center text-xs',
                                                    dropTarget?.kind ===
                                                        'child' &&
                                                        dropTarget.itemIndex ===
                                                            index &&
                                                        'border-primary bg-primary/5',
                                                )
                                            "
                                            @dragover="
                                                dragOverEmptyChildren(
                                                    $event,
                                                    index,
                                                )
                                            "
                                            @drop.prevent.stop="dropOnChild"
                                        >
                                            Ce menu est vide. Ajoutez un
                                            sous-lien ou déposez-en un ici.
                                        </div>

                                        <ol v-else class="flex flex-col gap-2">
                                            <li
                                                v-for="(
                                                    child, childIndex
                                                ) in item.children"
                                                :key="keyOf(child)"
                                                class="relative"
                                                data-test="navigation-child"
                                                @dragover="
                                                    dragOverChild(
                                                        $event,
                                                        index,
                                                        childIndex,
                                                    )
                                                "
                                                @drop.prevent.stop="dropOnChild"
                                            >
                                                <div
                                                    v-if="
                                                        isDropIndicator({
                                                            kind: 'child',
                                                            itemIndex: index,
                                                            childIndex,
                                                            position: 'before',
                                                        })
                                                    "
                                                    class="bg-primary absolute inset-x-0 -top-1.5 h-0.5 rounded-full"
                                                    aria-hidden="true"
                                                />
                                                <Card
                                                    data-drag-preview
                                                    :class="
                                                        cn(
                                                            'gap-0 py-0 shadow-none transition-opacity',
                                                            isDragged({
                                                                kind: 'child',
                                                                itemIndex:
                                                                    index,
                                                                childIndex,
                                                            }) && 'opacity-50',
                                                        )
                                                    "
                                                >
                                                    <CardHeader
                                                        class="flex items-center gap-2 px-2 py-2 sm:px-3"
                                                    >
                                                        <button
                                                            :id="`${keyOf(child)}-handle`"
                                                            type="button"
                                                            draggable="true"
                                                            class="text-muted-foreground hover:bg-accent hover:text-foreground focus-visible:ring-ring flex size-7 shrink-0 cursor-grab items-center justify-center rounded-md outline-none focus-visible:ring-2 active:cursor-grabbing"
                                                            :aria-label="`Réorganiser « ${child.label || `Sous-lien ${childIndex + 1}`} » (position ${childIndex + 1} sur ${item.children.length}). Utilisez les flèches haut et bas.`"
                                                            title="Glisser pour réorganiser"
                                                            @dragstart="
                                                                startDrag(
                                                                    $event,
                                                                    {
                                                                        kind: 'child',
                                                                        itemIndex:
                                                                            index,
                                                                        childIndex,
                                                                    },
                                                                )
                                                            "
                                                            @dragend="endDrag"
                                                            @keydown.up.prevent="
                                                                moveChildWithKeyboard(
                                                                    index,
                                                                    childIndex,
                                                                    -1,
                                                                )
                                                            "
                                                            @keydown.down.prevent="
                                                                moveChildWithKeyboard(
                                                                    index,
                                                                    childIndex,
                                                                    1,
                                                                )
                                                            "
                                                        >
                                                            <GripVertical
                                                                class="size-4"
                                                            />
                                                        </button>
                                                        <div
                                                            class="flex min-w-0 flex-1 flex-col gap-0.5"
                                                        >
                                                            <CardTitle
                                                                class="truncate text-sm"
                                                            >
                                                                {{
                                                                    child.label ||
                                                                    `Sous-lien ${childIndex + 1}`
                                                                }}
                                                            </CardTitle>
                                                            <CardDescription
                                                                class="truncate text-xs"
                                                            >
                                                                {{
                                                                    destinationSummary(
                                                                        child.url,
                                                                    )
                                                                }}
                                                            </CardDescription>
                                                        </div>
                                                        <CardAction
                                                            class="self-center"
                                                        >
                                                            <DropdownMenu>
                                                                <DropdownMenuTrigger
                                                                    as-child
                                                                >
                                                                    <Button
                                                                        type="button"
                                                                        variant="ghost"
                                                                        size="icon-sm"
                                                                        :aria-label="`Actions pour « ${child.label || `Sous-lien ${childIndex + 1}`} »`"
                                                                    >
                                                                        <MoreHorizontal />
                                                                    </Button>
                                                                </DropdownMenuTrigger>
                                                                <DropdownMenuContent
                                                                    align="end"
                                                                    class="w-60"
                                                                >
                                                                    <DropdownMenuItem
                                                                        :disabled="
                                                                            childIndex ===
                                                                            0
                                                                        "
                                                                        @select="
                                                                            moveChild(
                                                                                index,
                                                                                childIndex,
                                                                                index,
                                                                                childIndex -
                                                                                    1,
                                                                            )
                                                                        "
                                                                    >
                                                                        <ArrowUp />
                                                                        Monter
                                                                    </DropdownMenuItem>
                                                                    <DropdownMenuItem
                                                                        :disabled="
                                                                            childIndex ===
                                                                            item
                                                                                .children
                                                                                .length -
                                                                                1
                                                                        "
                                                                        @select="
                                                                            moveChild(
                                                                                index,
                                                                                childIndex,
                                                                                index,
                                                                                childIndex +
                                                                                    1,
                                                                            )
                                                                        "
                                                                    >
                                                                        <ArrowDown />
                                                                        Descendre
                                                                    </DropdownMenuItem>
                                                                    <DropdownMenuSub
                                                                        v-if="
                                                                            otherGroups(
                                                                                index,
                                                                            )
                                                                                .length >
                                                                            0
                                                                        "
                                                                    >
                                                                        <DropdownMenuSubTrigger>
                                                                            <FolderInput />
                                                                            Déplacer
                                                                            vers
                                                                        </DropdownMenuSubTrigger>
                                                                        <DropdownMenuSubContent
                                                                            class="w-52"
                                                                        >
                                                                            <DropdownMenuItem
                                                                                v-for="group in otherGroups(
                                                                                    index,
                                                                                )"
                                                                                :key="
                                                                                    group.index
                                                                                "
                                                                                @select="
                                                                                    moveChildToGroup(
                                                                                        index,
                                                                                        childIndex,
                                                                                        group.index,
                                                                                    )
                                                                                "
                                                                            >
                                                                                <FolderTree />
                                                                                <span
                                                                                    class="truncate"
                                                                                >
                                                                                    {{
                                                                                        group.label
                                                                                    }}
                                                                                </span>
                                                                            </DropdownMenuItem>
                                                                        </DropdownMenuSubContent>
                                                                    </DropdownMenuSub>
                                                                    <DropdownMenuItem
                                                                        :disabled="
                                                                            !canAddItem
                                                                        "
                                                                        @select="
                                                                            promoteChild(
                                                                                index,
                                                                                childIndex,
                                                                            )
                                                                        "
                                                                    >
                                                                        <ArrowUpToLine />
                                                                        Placer
                                                                        dans le
                                                                        menu
                                                                        principal
                                                                    </DropdownMenuItem>
                                                                    <DropdownMenuSeparator />
                                                                    <DropdownMenuItem
                                                                        variant="destructive"
                                                                        @select="
                                                                            removeChild(
                                                                                index,
                                                                                childIndex,
                                                                            )
                                                                        "
                                                                    >
                                                                        <Trash2 />
                                                                        Supprimer
                                                                    </DropdownMenuItem>
                                                                </DropdownMenuContent>
                                                            </DropdownMenu>
                                                        </CardAction>
                                                    </CardHeader>
                                                    <Separator />
                                                    <CardContent
                                                        class="px-3 py-3 sm:px-4"
                                                    >
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
                                                                    :for="`${keyOf(child)}-label`"
                                                                >
                                                                    Libellé
                                                                </FieldLabel>
                                                                <Input
                                                                    :id="`${keyOf(child)}-label`"
                                                                    v-model="
                                                                        child.label
                                                                    "
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
                                                                        changed
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

                                                            <FrontendNavigationDestinationField
                                                                :id="`${keyOf(child)}-url`"
                                                                v-model="
                                                                    child.url
                                                                "
                                                                :destinations="
                                                                    destinations
                                                                "
                                                                :error="
                                                                    error(
                                                                        `items.${index}.children.${childIndex}.url`,
                                                                    )
                                                                "
                                                                placeholder="Ex. /equipe"
                                                                @update:model-value="
                                                                    changed
                                                                "
                                                                @pick="
                                                                    applyDestination(
                                                                        child,
                                                                        $event,
                                                                    )
                                                                "
                                                            />

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
                                                                    :for="`${keyOf(child)}-description`"
                                                                >
                                                                    Description
                                                                    <span
                                                                        class="text-muted-foreground font-normal"
                                                                    >
                                                                        (facultatif)
                                                                    </span>
                                                                </FieldLabel>
                                                                <Input
                                                                    :id="`${keyOf(child)}-description`"
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
                                                                    placeholder="Une phrase affichée sous le libellé"
                                                                    @update:model-value="
                                                                        changed
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
                                                <div
                                                    v-if="
                                                        isDropIndicator({
                                                            kind: 'child',
                                                            itemIndex: index,
                                                            childIndex,
                                                            position: 'after',
                                                        })
                                                    "
                                                    class="bg-primary absolute inset-x-0 -bottom-1.5 h-0.5 rounded-full"
                                                    aria-hidden="true"
                                                />
                                            </li>
                                        </ol>
                                    </section>
                                </CardContent>
                            </CollapsibleContent>
                        </Card>
                    </Collapsible>
                    <div
                        v-if="
                            isDropIndicator({
                                kind: 'item',
                                index,
                                position: 'after',
                            })
                        "
                        class="bg-primary absolute inset-x-0 -bottom-1.5 h-0.5 rounded-full"
                        aria-hidden="true"
                    />
                </li>
            </ol>
        </div>
    </div>
</template>
