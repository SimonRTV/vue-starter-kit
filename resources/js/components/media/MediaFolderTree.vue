<script setup lang="ts">
import {
    ChevronRight,
    Folder,
    FolderOpen,
    FolderPlus,
    Inbox,
    Library,
    MoreHorizontal,
    Pencil,
    Trash2,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { MEDIA_DRAG_TYPE } from '@/lib/media';
import type { MediaFolder } from '@/types/media';

type FolderTarget = number | 'none' | null;

const props = defineProps<{
    folders: MediaFolder[];
    active: FolderTarget;
    totalCount: number;
    unfiledCount: number;
    canManage: boolean;
    maxDepth: number;
    acceptsDrops: boolean;
}>();
const emit = defineEmits<{
    select: [target: FolderTarget];
    create: [parentId: number | null];
    edit: [folder: MediaFolder];
    delete: [folder: MediaFolder];
    drop: [folderId: number | null, ids: string[]];
}>();

const expanded = ref(new Set<number>());
const dropTarget = ref<FolderTarget | undefined>(undefined);
const byId = computed(
    () => new Map(props.folders.map((folder) => [folder.id, folder])),
);
const parentIds = computed(
    () =>
        new Set(
            props.folders
                .map((folder) => folder.parent_id)
                .filter((id): id is number => id !== null),
        ),
);
const visibleFolders = computed(() =>
    props.folders.filter((folder) =>
        ancestorsOf(folder).every((ancestor) =>
            expanded.value.has(ancestor.id),
        ),
    ),
);

function ancestorsOf(folder: MediaFolder): MediaFolder[] {
    const ancestors: MediaFolder[] = [];

    for (
        let parent = folder.parent_id
            ? byId.value.get(folder.parent_id)
            : undefined;
        parent;
        parent = parent.parent_id ? byId.value.get(parent.parent_id) : undefined
    ) {
        ancestors.push(parent);
    }

    return ancestors;
}

function toggle(folder: MediaFolder): void {
    const next = new Set(expanded.value);

    if (next.has(folder.id)) {
        next.delete(folder.id);
    } else {
        next.add(folder.id);
    }

    expanded.value = next;
}

function acceptsDrag(event: DragEvent): boolean {
    return (
        props.acceptsDrops &&
        !!event.dataTransfer?.types.includes(MEDIA_DRAG_TYPE)
    );
}

function dragOver(event: DragEvent, target: FolderTarget): void {
    if (!acceptsDrag(event)) {
        return;
    }

    event.preventDefault();
    event.dataTransfer!.dropEffect = 'move';
    dropTarget.value = target;
}

function dragLeave(target: FolderTarget): void {
    if (dropTarget.value === target) {
        dropTarget.value = undefined;
    }
}

function drop(event: DragEvent, folderId: number | null): void {
    if (!acceptsDrag(event)) {
        return;
    }

    event.preventDefault();
    dropTarget.value = undefined;

    try {
        const ids: unknown = JSON.parse(
            event.dataTransfer!.getData(MEDIA_DRAG_TYPE),
        );

        if (
            Array.isArray(ids) &&
            ids.length &&
            ids.every((id) => typeof id === 'string')
        ) {
            emit('drop', folderId, ids);
        }
    } catch {
        // Ignore drags that did not originate from a media card.
    }
}

function rowClass(target: FolderTarget): string {
    if (dropTarget.value === target) {
        return 'bg-primary/15 ring-primary ring-2';
    }

    return props.active === target
        ? 'bg-accent text-accent-foreground font-medium'
        : 'hover:bg-accent/60';
}

watch(
    () => props.active,
    (active) => {
        const folder =
            typeof active === 'number' ? byId.value.get(active) : null;

        if (folder) {
            expanded.value = new Set([
                ...expanded.value,
                folder.id,
                ...ancestorsOf(folder).map((ancestor) => ancestor.id),
            ]);
        }
    },
    { immediate: true },
);
</script>

<template>
    <nav class="flex flex-col gap-1 text-sm" aria-label="Dossiers">
        <button
            type="button"
            class="focus-visible:ring-ring flex items-center gap-2 rounded-md px-2 py-1.5 text-left transition-colors focus-visible:ring-2 focus-visible:outline-none"
            :class="rowClass(null)"
            :aria-current="active === null ? 'page' : undefined"
            @click="emit('select', null)"
        >
            <Library class="size-4 shrink-0" aria-hidden="true" />
            <span class="min-w-0 flex-1 truncate">Tous les fichiers</span>
            <span class="text-muted-foreground text-xs tabular-nums">{{
                totalCount
            }}</span>
        </button>
        <button
            type="button"
            class="focus-visible:ring-ring flex items-center gap-2 rounded-md px-2 py-1.5 text-left transition-colors focus-visible:ring-2 focus-visible:outline-none"
            :class="rowClass('none')"
            :aria-current="active === 'none' ? 'page' : undefined"
            @click="emit('select', 'none')"
            @dragover="dragOver($event, 'none')"
            @dragleave="dragLeave('none')"
            @drop="drop($event, null)"
        >
            <Inbox class="size-4 shrink-0" aria-hidden="true" />
            <span class="min-w-0 flex-1 truncate">Non classés</span>
            <span class="text-muted-foreground text-xs tabular-nums">{{
                unfiledCount
            }}</span>
        </button>

        <div class="mt-3 flex items-center justify-between gap-2 px-2">
            <span
                class="text-muted-foreground text-xs font-medium tracking-wide uppercase"
                >Dossiers</span
            >
            <Button
                v-if="canManage"
                variant="ghost"
                size="icon"
                class="size-7"
                aria-label="Nouveau dossier"
                @click="emit('create', null)"
            >
                <FolderPlus class="size-4" aria-hidden="true" />
            </Button>
        </div>

        <p
            v-if="!folders.length"
            class="text-muted-foreground px-2 py-1 text-xs leading-relaxed"
        >
            {{
                canManage
                    ? 'Créez un dossier pour classer vos fichiers.'
                    : 'Aucun dossier pour le moment.'
            }}
        </p>

        <ul v-else class="flex flex-col gap-0.5">
            <li
                v-for="folder in visibleFolders"
                :key="folder.id"
                class="group flex items-center gap-0.5 rounded-md transition-colors"
                :class="rowClass(folder.id)"
                :style="{ paddingLeft: `${(folder.depth - 1) * 0.875}rem` }"
                @dragover="dragOver($event, folder.id)"
                @dragleave="dragLeave(folder.id)"
                @drop="drop($event, folder.id)"
            >
                <button
                    v-if="parentIds.has(folder.id)"
                    type="button"
                    class="text-muted-foreground hover:text-foreground focus-visible:ring-ring flex size-6 shrink-0 items-center justify-center rounded focus-visible:ring-2 focus-visible:outline-none"
                    :aria-label="`${expanded.has(folder.id) ? 'Replier' : 'Déplier'} ${folder.name}`"
                    :aria-expanded="expanded.has(folder.id)"
                    @click="toggle(folder)"
                >
                    <ChevronRight
                        class="size-3.5 transition-transform"
                        :class="expanded.has(folder.id) ? 'rotate-90' : ''"
                        aria-hidden="true"
                    />
                </button>
                <span v-else class="size-6 shrink-0" aria-hidden="true" />
                <button
                    type="button"
                    class="focus-visible:ring-ring flex min-w-0 flex-1 items-center gap-2 rounded-md py-1.5 pr-1 text-left focus-visible:ring-2 focus-visible:outline-none"
                    :aria-current="active === folder.id ? 'page' : undefined"
                    :title="folder.path"
                    @click="emit('select', folder.id)"
                >
                    <FolderOpen
                        v-if="active === folder.id"
                        class="text-primary size-4 shrink-0"
                        aria-hidden="true"
                    />
                    <Folder
                        v-else
                        class="text-muted-foreground size-4 shrink-0"
                        aria-hidden="true"
                    />
                    <span class="min-w-0 flex-1 truncate">{{
                        folder.name
                    }}</span>
                    <span class="text-muted-foreground text-xs tabular-nums">{{
                        folder.files_count
                    }}</span>
                </button>
                <DropdownMenu v-if="canManage">
                    <DropdownMenuTrigger as-child>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="size-7 shrink-0 opacity-0 group-focus-within:opacity-100 group-hover:opacity-100 data-[state=open]:opacity-100 max-lg:opacity-100"
                            :aria-label="`Actions du dossier ${folder.name}`"
                        >
                            <MoreHorizontal class="size-4" aria-hidden="true" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuItem
                            v-if="folder.depth < maxDepth"
                            @select="emit('create', folder.id)"
                            ><FolderPlus />Nouveau
                            sous-dossier</DropdownMenuItem
                        >
                        <DropdownMenuItem @select="emit('edit', folder)"
                            ><Pencil />Renommer ou déplacer</DropdownMenuItem
                        >
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            variant="destructive"
                            @select="emit('delete', folder)"
                            ><Trash2 />Supprimer</DropdownMenuItem
                        >
                    </DropdownMenuContent>
                </DropdownMenu>
            </li>
        </ul>
    </nav>
</template>
