<script setup lang="ts">
import { router, useForm, usePage } from '@inertiajs/vue3';
import {
    ChevronRight,
    FileText,
    Folder,
    FolderPlus,
    FolderTree,
    LayoutGrid,
    List,
    Search,
    UploadCloud,
    X,
} from '@lucide/vue';
import { watchDebounced } from '@vueuse/core';
import { computed, onMounted, ref, watch } from 'vue';
import BulkMediaController from '@/actions/App/Http/Controllers/BulkMediaController';
import { index } from '@/actions/App/Http/Controllers/MediaController';
import { destroy as destroyFolder } from '@/actions/App/Http/Controllers/MediaFolderController';
import { ConfirmationAction, PageHeader } from '@/components/application';
import { DataTable, DataTablePagination } from '@/components/data-table';
import MediaBulkActions from '@/components/media/MediaBulkActions.vue';
import MediaCard from '@/components/media/MediaCard.vue';
import { mediaColumns } from '@/components/media/mediaColumns';
import MediaDetailsSheet from '@/components/media/MediaDetailsSheet.vue';
import MediaFolderDialog from '@/components/media/MediaFolderDialog.vue';
import MediaFolderSelect from '@/components/media/MediaFolderSelect.vue';
import MediaFolderTree from '@/components/media/MediaFolderTree.vue';
import MediaUploadDialog from '@/components/media/MediaUploadDialog.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Field, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { useServerDataTable } from '@/composables/useServerDataTable';
import { formatFileSize, MEDIA_DRAG_TYPE } from '@/lib/media';
import type {
    MediaBulkAction,
    MediaFilters,
    MediaFolder,
    MediaFolderDialogMode,
    MediaItem,
    MediaPagination,
    MediaSort,
    MediaStats,
} from '@/types/media';

type MediaIndexQuery = {
    search?: string;
    type?: MediaFilters['type'];
    visibility?: MediaFilters['visibility'];
    tag?: string;
    folder?: number | 'none';
    sort: MediaSort;
    direction: MediaFilters['direction'];
    per_page: number;
    page: number;
};

const SORT_OPTIONS = [
    { value: 'created_at:desc', label: 'Plus récents' },
    { value: 'created_at:asc', label: 'Plus anciens' },
    { value: 'title:asc', label: 'Titre (A → Z)' },
    { value: 'title:desc', label: 'Titre (Z → A)' },
    { value: 'size:desc', label: 'Plus lourds' },
    { value: 'size:asc', label: 'Plus légers' },
] as const;

const props = defineProps<{
    media: MediaPagination;
    filters: MediaFilters;
    tags: string[];
    stats: MediaStats;
    folders: MediaFolder[];
    maxFolderDepth: number;
    canUpload: boolean;
    canPublish: boolean;
    canBulkUpdate: boolean;
    canManageFolders: boolean;
    maxUploadKb: number;
    extensions: string[];
    perPageOptions: number[];
    maxTags: number;
}>();
defineOptions({
    layout: { breadcrumbs: [{ title: 'Médiathèque', href: index() }] },
});

const page = usePage();
const viewStorageKey = `media-view:v1:${page.props.auth.user.id}`;
const view = ref<'grid' | 'list'>('grid');
const search = ref(props.filters.search ?? '');
const selectedIds = ref<string[]>([]);
const uploadOpen = ref(false);
const uploadDialog = ref<InstanceType<typeof MediaUploadDialog> | null>(null);
const pageDragDepth = ref(0);
const detailsOpen = ref(false);
const activeId = ref<string | null>(null);
const lastActiveItem = ref<MediaItem | null>(null);
const confirmBulk = ref(false);
const moveOpen = ref(false);
const moveTarget = ref<number | null>(null);
const foldersOpen = ref(false);
const folderDialogOpen = ref(false);
const folderDialogMode = ref<MediaFolderDialogMode>({
    type: 'create',
    parentId: null,
});
const deletingFolder = ref<MediaFolder | null>(null);
const deleteFolderOpen = ref(false);
const deletingFolderProcessing = ref(false);
const bulk = useForm<{
    ids: string[];
    action: MediaBulkAction;
    folder_id: number | null;
}>({
    ids: [],
    action: 'private',
    folder_id: null,
});

const {
    pagination,
    processing,
    sorting,
    updatePagination,
    updateSorting,
    visit,
} = useServerDataTable<MediaItem, MediaSort, MediaIndexQuery>({
    pagination: () => props.media,
    sorting: () => props.filters,
    query: () => ({
        search: search.value.trim() || undefined,
        type: props.filters.type ?? undefined,
        visibility: props.filters.visibility ?? undefined,
        tag: props.filters.tag ?? undefined,
        folder: props.filters.folder ?? undefined,
        sort: props.filters.sort,
        direction: props.filters.direction,
        per_page: props.filters.per_page,
        page: props.media.current_page,
    }),
    url: (query) => index.url({ query: canonicalQuery(query) }),
    resetUrl: () => index.url(),
    only: ['media', 'filters', 'tags', 'stats', 'folders'],
});

const foldersById = computed(
    () => new Map(props.folders.map((folder) => [folder.id, folder])),
);
const currentFolder = computed(() =>
    typeof props.filters.folder === 'number'
        ? (foldersById.value.get(props.filters.folder) ?? null)
        : null,
);
const folderTrail = computed(() => {
    const trail: MediaFolder[] = [];

    for (
        let folder = currentFolder.value;
        folder;
        folder = folder.parent_id
            ? (foldersById.value.get(folder.parent_id) ?? null)
            : null
    ) {
        trail.unshift(folder);
    }

    return trail;
});
const subfolders = computed(() =>
    currentFolder.value
        ? props.folders.filter(
              (folder) => folder.parent_id === currentFolder.value?.id,
          )
        : [],
);
const locationTitle = computed(() => {
    if (props.filters.folder === 'none') {
        return 'Non classés';
    }

    return currentFolder.value?.name ?? 'Tous les fichiers';
});
const uploadFolderId = computed(() => currentFolder.value?.id ?? null);
const columns = mediaColumns(openDetails, folderPath);
const activeItem = computed(
    () =>
        props.media.data.find((item) => item.id === activeId.value) ??
        lastActiveItem.value,
);
const sortValue = computed(
    () => `${props.filters.sort}:${props.filters.direction}`,
);
const hasFilters = computed(
    () =>
        props.filters.search !== null ||
        props.filters.type !== null ||
        props.filters.visibility !== null ||
        props.filters.tag !== null,
);
const hasCustomView = computed(
    () =>
        hasFilters.value ||
        sortValue.value !== 'created_at:desc' ||
        props.filters.per_page !== props.perPageOptions[0],
);
const allSelected = computed(
    () =>
        props.media.data.length > 0 &&
        props.media.data.every((item) => selectedIds.value.includes(item.id)),
);
const emptyMessage = computed(() => {
    if (hasFilters.value) {
        return 'Aucun fichier ne correspond à ces filtres.';
    }

    if (props.filters.folder === 'none') {
        return 'Tous vos fichiers sont classés dans des dossiers.';
    }

    return currentFolder.value
        ? 'Ce dossier ne contient aucun fichier.'
        : 'Aucun fichier.';
});
const bulkCopy = computed(() => {
    const count = selectedIds.value.length;
    const files = count === 1 ? '1 fichier' : `${count} fichiers`;

    return {
        public: {
            title: 'Rendre les fichiers publics ?',
            description: `${files} seront accessibles à toute personne disposant du lien.`,
        },
        private: {
            title: 'Rendre les fichiers privés ?',
            description: `${files} ne seront plus accessibles publiquement. Les contenus publics qui les affichent ne pourront plus les charger.`,
        },
        delete: {
            title: 'Supprimer les fichiers sélectionnés ?',
            description: `${files} seront supprimés définitivement. Les fichiers encore joints à un contenu doivent d’abord être détachés.`,
        },
        move: { title: '', description: '' },
    }[bulk.action];
});

function folderPath(folderId: number | null): string | null {
    return folderId === null
        ? null
        : (foldersById.value.get(folderId)?.path ?? null);
}

function canonicalQuery(
    query: MediaIndexQuery,
): Record<string, string | number> {
    const canonical: Record<string, string | number> = {};

    if (query.folder) {
        canonical.folder = query.folder;
    }

    if (query.search) {
        canonical.search = query.search;
    }

    if (query.type) {
        canonical.type = query.type;
    }

    if (query.visibility) {
        canonical.visibility = query.visibility;
    }

    if (query.tag) {
        canonical.tag = query.tag;
    }

    if (query.sort !== 'created_at') {
        canonical.sort = query.sort;
    }

    if (query.direction !== 'desc') {
        canonical.direction = query.direction;
    }

    if (query.per_page !== props.perPageOptions[0]) {
        canonical.per_page = query.per_page;
    }

    if (query.page > 1) {
        canonical.page = query.page;
    }

    return canonical;
}

function updateFilter(
    key: 'type' | 'visibility' | 'tag',
    value: unknown,
): void {
    visit({
        [key]: value === 'all' || typeof value !== 'string' ? undefined : value,
        page: 1,
    });
}

function updateSort(value: unknown): void {
    const option = SORT_OPTIONS.find((sort) => sort.value === value);

    if (!option) {
        return;
    }

    const [sort, direction] = option.value.split(':') as [
        MediaSort,
        MediaFilters['direction'],
    ];
    visit({ sort, direction, page: 1 });
}

function selectFolder(target: number | 'none' | null): void {
    foldersOpen.value = false;
    visit({ folder: target ?? undefined, page: 1 }, false);
}

function resetView(): void {
    search.value = '';
    visit({
        search: undefined,
        type: undefined,
        visibility: undefined,
        tag: undefined,
        sort: 'created_at',
        direction: 'desc',
        per_page: props.perPageOptions[0],
        page: 1,
    });
}

function updateView(value: unknown): void {
    if (value !== 'grid' && value !== 'list') {
        return;
    }

    view.value = value;

    try {
        localStorage.setItem(viewStorageKey, value);
    } catch {
        // Storage can be unavailable (private browsing); the choice then lasts for this visit.
    }
}

function openDetails(item: MediaItem): void {
    activeId.value = item.id;
    lastActiveItem.value = item;
    detailsOpen.value = true;
}

function toggleSelection(id: string): void {
    selectedIds.value = selectedIds.value.includes(id)
        ? selectedIds.value.filter((value) => value !== id)
        : [...selectedIds.value, id];
}

function toggleAll(): void {
    selectedIds.value = allSelected.value
        ? []
        : props.media.data.map((item) => item.id);
}

function requestBulk(action: MediaBulkAction): void {
    bulk.action = action;
    bulk.clearErrors();

    if (action === 'move') {
        moveTarget.value = currentFolder.value?.id ?? null;
        moveOpen.value = true;

        return;
    }

    confirmBulk.value = true;
}

function submitBulk(): void {
    bulk.ids = [...selectedIds.value];
    bulk.folder_id = bulk.action === 'move' ? moveTarget.value : null;
    bulk.patch(BulkMediaController.url(), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            selectedIds.value = [];
            confirmBulk.value = false;
            moveOpen.value = false;
        },
        onError: () => {
            confirmBulk.value = false;
            moveOpen.value = false;
        },
    });
}

function startDrag(event: DragEvent, item: MediaItem): void {
    if (!event.dataTransfer) {
        return;
    }

    const ids = selectedIds.value.includes(item.id)
        ? [...selectedIds.value]
        : [item.id];
    event.dataTransfer.effectAllowed = 'move';
    event.dataTransfer.setData(MEDIA_DRAG_TYPE, JSON.stringify(ids));
    event.dataTransfer.setData(
        'text/plain',
        ids.length === 1 ? item.title : `${ids.length} fichiers`,
    );
}

function dropOnFolder(folderId: number | null, ids: string[]): void {
    bulk.clearErrors();
    bulk.ids = ids;
    bulk.action = 'move';
    bulk.folder_id = folderId;
    bulk.patch(BulkMediaController.url(), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            selectedIds.value = [];
        },
    });
}

function openCreateFolder(parentId: number | null): void {
    folderDialogMode.value = { type: 'create', parentId };
    folderDialogOpen.value = true;
}

function openEditFolder(folder: MediaFolder): void {
    folderDialogMode.value = { type: 'edit', folder };
    folderDialogOpen.value = true;
}

function requestDeleteFolder(folder: MediaFolder): void {
    deletingFolder.value = folder;
    deleteFolderOpen.value = true;
}

function deleteFolder(): void {
    const folder = deletingFolder.value;

    if (!folder || deletingFolderProcessing.value) {
        return;
    }

    const viewingDeletedFolder = folderTrail.value.some(
        (ancestor) => ancestor.id === folder.id,
    );
    deletingFolderProcessing.value = true;
    router.delete(destroyFolder.url(folder.id), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            deleteFolderOpen.value = false;

            if (viewingDeletedFolder) {
                visit({ folder: folder.parent_id ?? undefined, page: 1 });
            }
        },
        onFinish: () => {
            deletingFolderProcessing.value = false;
        },
    });
}

function reloadLibrary(): void {
    router.reload({ only: ['media', 'tags', 'stats', 'folders'] });
}

function hasDraggedFiles(event: DragEvent): boolean {
    const types = event.dataTransfer?.types ?? [];

    return (
        props.canUpload &&
        !uploadOpen.value &&
        types.includes('Files') &&
        !types.includes(MEDIA_DRAG_TYPE)
    );
}

function pageDragEnter(event: DragEvent): void {
    if (hasDraggedFiles(event)) {
        event.preventDefault();
        pageDragDepth.value++;
    }
}

function pageDragLeave(): void {
    pageDragDepth.value = Math.max(0, pageDragDepth.value - 1);
}

function pageDragOver(event: DragEvent): void {
    if (hasDraggedFiles(event)) {
        event.preventDefault();
    }
}

function pageDrop(event: DragEvent): void {
    if (pageDragDepth.value === 0) {
        return;
    }

    event.preventDefault();
    pageDragDepth.value = 0;
    uploadOpen.value = true;
    uploadDialog.value?.addFiles(Array.from(event.dataTransfer?.files ?? []));
}

watch(
    () => props.media,
    (media) => {
        selectedIds.value = selectedIds.value.filter((id) =>
            media.data.some((item) => item.id === id),
        );

        const active = media.data.find((item) => item.id === activeId.value);

        if (active) {
            lastActiveItem.value = active;
        }

        if (
            media.data.length === 0 &&
            media.last_page > 0 &&
            media.current_page > media.last_page
        ) {
            visit({ page: media.last_page });
        }
    },
);

watch(
    () => props.filters.search,
    (value) => {
        if (search.value.trim() !== (value ?? '')) {
            search.value = value ?? '';
        }
    },
);

watchDebounced(
    search,
    (value) => {
        const normalized = value.trim();

        if (normalized === (props.filters.search ?? '')) {
            return;
        }

        visit({ search: normalized || undefined, page: 1 });
    },
    { debounce: 300, maxWait: 1000 },
);

onMounted(() => {
    try {
        const stored = localStorage.getItem(viewStorageKey);

        if (stored === 'grid' || stored === 'list') {
            view.value = stored;
        }
    } catch {
        // Storage can be unavailable; keep the default grid view.
    }
});
</script>

<template>
    <div
        class="admin-page relative flex w-full min-w-0 flex-1 flex-col gap-6 p-4 md:p-6 lg:p-8"
        @dragenter="pageDragEnter"
        @dragleave="pageDragLeave"
        @dragover="pageDragOver"
        @drop="pageDrop"
    >
        <PageHeader
            title="Médiathèque"
            description="Importez, organisez et réutilisez vos images et documents."
        >
            <template #meta>
                <p class="text-muted-foreground text-sm tabular-nums">
                    {{
                        stats.count === 1
                            ? '1 fichier'
                            : `${stats.count} fichiers`
                    }}
                    · {{ formatFileSize(stats.size) }} utilisés
                </p>
            </template>
            <template v-if="canUpload" #actions>
                <Button @click="uploadOpen = true"
                    ><UploadCloud data-icon="inline-start" />Importer des
                    fichiers</Button
                >
            </template>
        </PageHeader>

        <div
            class="grid min-w-0 items-start gap-6 lg:grid-cols-[15rem_minmax(0,1fr)]"
        >
            <aside class="flex min-w-0 flex-col gap-2 lg:sticky lg:top-4">
                <Button
                    variant="outline"
                    class="justify-between lg:hidden"
                    :aria-expanded="foldersOpen"
                    aria-controls="media-folder-tree"
                    @click="foldersOpen = !foldersOpen"
                >
                    <span class="flex items-center gap-2"
                        ><FolderTree class="size-4" aria-hidden="true" />{{
                            locationTitle
                        }}</span
                    >
                    <ChevronRight
                        class="size-4 transition-transform"
                        :class="foldersOpen ? 'rotate-90' : ''"
                        aria-hidden="true"
                    />
                </Button>
                <div
                    id="media-folder-tree"
                    class="rounded-xl border p-2 lg:block"
                    :class="foldersOpen ? 'block' : 'hidden'"
                >
                    <MediaFolderTree
                        :folders="folders"
                        :active="filters.folder"
                        :total-count="stats.count"
                        :unfiled-count="stats.unfiled"
                        :can-manage="canManageFolders"
                        :max-depth="maxFolderDepth"
                        :accepts-drops="canBulkUpdate"
                        @select="selectFolder"
                        @create="openCreateFolder"
                        @edit="openEditFolder"
                        @delete="requestDeleteFolder"
                        @drop="dropOnFolder"
                    />
                </div>
            </aside>

            <section
                class="flex min-w-0 flex-col gap-4"
                aria-label="Bibliothèque de fichiers"
            >
                <div class="flex flex-col gap-3">
                    <nav
                        v-if="folderTrail.length"
                        aria-label="Emplacement"
                        class="text-muted-foreground flex flex-wrap items-center gap-1 text-sm"
                    >
                        <button
                            type="button"
                            class="hover:text-foreground hover:underline"
                            @click="selectFolder(null)"
                        >
                            Tous les fichiers
                        </button>
                        <template
                            v-for="(folder, position) in folderTrail"
                            :key="folder.id"
                        >
                            <ChevronRight class="size-3.5" aria-hidden="true" />
                            <span
                                v-if="position === folderTrail.length - 1"
                                class="text-foreground"
                                aria-current="location"
                                >{{ folder.name }}</span
                            >
                            <button
                                v-else
                                type="button"
                                class="hover:text-foreground hover:underline"
                                @click="selectFolder(folder.id)"
                            >
                                {{ folder.name }}
                            </button>
                        </template>
                    </nav>
                    <h2 class="text-xl font-medium tracking-tight">
                        {{ locationTitle }}
                    </h2>
                    <div
                        v-if="
                            subfolders.length ||
                            (currentFolder &&
                                canManageFolders &&
                                currentFolder.depth < maxFolderDepth)
                        "
                        class="flex flex-wrap gap-2"
                    >
                        <Button
                            v-for="folder in subfolders"
                            :key="folder.id"
                            variant="outline"
                            size="sm"
                            @click="selectFolder(folder.id)"
                            ><Folder
                                data-icon="inline-start"
                                class="text-muted-foreground"
                            />{{ folder.name }}
                            <span
                                class="text-muted-foreground text-xs tabular-nums"
                                >{{ folder.files_count }}</span
                            ></Button
                        >
                        <Button
                            v-if="
                                currentFolder &&
                                canManageFolders &&
                                currentFolder.depth < maxFolderDepth
                            "
                            variant="ghost"
                            size="sm"
                            @click="openCreateFolder(currentFolder.id)"
                            ><FolderPlus data-icon="inline-start" />Nouveau
                            sous-dossier</Button
                        >
                    </div>
                </div>

                <div
                    v-if="stats.count === 0"
                    class="admin-panel text-muted-foreground flex min-h-80 flex-col items-center justify-center gap-4 rounded-2xl border border-dashed p-8 text-center text-sm"
                >
                    <FileText
                        class="text-primary/40 size-10"
                        aria-hidden="true"
                    />
                    <p>Votre médiathèque est vide.</p>
                    <p v-if="canUpload">
                        Glissez-déposez des fichiers n’importe où sur cette page
                        ou utilisez le bouton ci-dessous.
                    </p>
                    <Button v-if="canUpload" @click="uploadOpen = true"
                        ><UploadCloud data-icon="inline-start" />Importer des
                        fichiers</Button
                    >
                </div>

                <template v-else>
                    <div class="flex flex-wrap items-end gap-3">
                        <Field class="w-full sm:w-72">
                            <FieldLabel for="media-search" class="sr-only"
                                >Rechercher un fichier</FieldLabel
                            >
                            <div class="relative">
                                <Search
                                    class="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2"
                                    aria-hidden="true"
                                />
                                <Input
                                    id="media-search"
                                    v-model="search"
                                    class="pl-9"
                                    placeholder="Titre, nom de fichier, texte alternatif…"
                                    autocomplete="off"
                                    maxlength="100"
                                />
                            </div>
                        </Field>
                        <Select
                            :model-value="filters.type ?? 'all'"
                            @update:model-value="updateFilter('type', $event)"
                        >
                            <SelectTrigger
                                class="w-40"
                                aria-label="Type de fichier"
                                ><SelectValue
                            /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all"
                                    >Tous les types</SelectItem
                                >
                                <SelectItem value="image">Images</SelectItem>
                                <SelectItem value="document"
                                    >Documents</SelectItem
                                >
                            </SelectContent>
                        </Select>
                        <Select
                            :model-value="filters.visibility ?? 'all'"
                            @update:model-value="
                                updateFilter('visibility', $event)
                            "
                        >
                            <SelectTrigger class="w-40" aria-label="Accès"
                                ><SelectValue
                            /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all"
                                    >Tous les accès</SelectItem
                                >
                                <SelectItem value="private">Privés</SelectItem>
                                <SelectItem value="public">Publics</SelectItem>
                            </SelectContent>
                        </Select>
                        <Select
                            v-if="tags.length || filters.tag"
                            :model-value="filters.tag ?? 'all'"
                            @update:model-value="updateFilter('tag', $event)"
                        >
                            <SelectTrigger class="w-52" aria-label="Étiquette"
                                ><SelectValue
                            /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all"
                                    >Toutes les étiquettes</SelectItem
                                >
                                <SelectItem
                                    v-for="tag in tags"
                                    :key="tag"
                                    :value="tag"
                                    >{{ tag }}</SelectItem
                                >
                            </SelectContent>
                        </Select>
                        <Select
                            :model-value="sortValue"
                            @update:model-value="updateSort"
                        >
                            <SelectTrigger class="w-44" aria-label="Trier par"
                                ><SelectValue
                            /></SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="option in SORT_OPTIONS"
                                    :key="option.value"
                                    :value="option.value"
                                    >{{ option.label }}</SelectItem
                                >
                            </SelectContent>
                        </Select>
                        <Button
                            v-if="hasCustomView"
                            variant="ghost"
                            :disabled="processing"
                            @click="resetView"
                            ><X data-icon="inline-start" />Réinitialiser</Button
                        >
                        <ToggleGroup
                            type="single"
                            variant="outline"
                            class="ml-auto"
                            :model-value="view"
                            aria-label="Affichage"
                            @update:model-value="updateView"
                        >
                            <ToggleGroupItem value="grid" aria-label="Grille"
                                ><LayoutGrid class="size-4"
                            /></ToggleGroupItem>
                            <ToggleGroupItem value="list" aria-label="Liste"
                                ><List class="size-4"
                            /></ToggleGroupItem>
                        </ToggleGroup>
                    </div>

                    <p
                        v-if="bulk.errors.ids || bulk.errors.folder_id"
                        role="alert"
                        class="text-destructive text-sm"
                    >
                        {{ bulk.errors.ids ?? bulk.errors.folder_id }}
                    </p>

                    <div
                        class="flex flex-col gap-4 transition-opacity"
                        :class="processing ? 'opacity-60' : ''"
                        :aria-busy="processing"
                    >
                        <template v-if="view === 'grid'">
                            <div
                                v-if="canBulkUpdate && media.data.length"
                                class="flex min-h-12 flex-wrap items-center gap-3 rounded-md border px-3 py-2"
                                :class="selectedIds.length ? 'bg-muted/30' : ''"
                            >
                                <label class="flex items-center gap-2 text-sm">
                                    <input
                                        type="checkbox"
                                        class="accent-primary size-4"
                                        :checked="allSelected"
                                        :indeterminate="
                                            selectedIds.length > 0 &&
                                            !allSelected
                                        "
                                        :disabled="processing"
                                        @change="toggleAll"
                                    />
                                    {{
                                        selectedIds.length
                                            ? `${selectedIds.length} sélectionné(s) sur cette page`
                                            : 'Tout sélectionner sur cette page'
                                    }}
                                </label>
                                <MediaBulkActions
                                    v-if="selectedIds.length"
                                    :can-publish="canPublish"
                                    :disabled="processing || bulk.processing"
                                    @action="requestBulk"
                                    @clear="selectedIds = []"
                                />
                                <span
                                    v-else-if="folders.length"
                                    class="text-muted-foreground hidden text-xs lg:inline"
                                    >Astuce : glissez des fichiers sur un
                                    dossier pour les y déplacer.</span
                                >
                            </div>

                            <div
                                v-if="media.data.length"
                                class="grid grid-cols-[repeat(auto-fill,minmax(13rem,1fr))] gap-4"
                            >
                                <MediaCard
                                    v-for="item in media.data"
                                    :key="item.id"
                                    :item="item"
                                    :selectable="canBulkUpdate"
                                    :selected="selectedIds.includes(item.id)"
                                    :disabled="processing || bulk.processing"
                                    :draggable="
                                        canBulkUpdate && item.can.update
                                    "
                                    :folder-path="
                                        filters.folder === null
                                            ? folderPath(item.folder_id)
                                            : null
                                    "
                                    @open="openDetails(item)"
                                    @toggle="toggleSelection(item.id)"
                                    @dragstart="startDrag($event, item)"
                                />
                            </div>
                            <div
                                v-else
                                class="text-muted-foreground flex min-h-60 flex-col items-center justify-center gap-3 rounded-xl border border-dashed p-8 text-center text-sm"
                            >
                                {{ emptyMessage }}
                                <Button
                                    v-if="hasFilters"
                                    variant="outline"
                                    size="sm"
                                    @click="resetView"
                                    >Réinitialiser les filtres</Button
                                >
                                <Button
                                    v-else-if="canUpload && currentFolder"
                                    variant="outline"
                                    size="sm"
                                    @click="uploadOpen = true"
                                    ><UploadCloud
                                        data-icon="inline-start"
                                    />Importer dans ce dossier</Button
                                >
                            </div>
                            <DataTablePagination
                                :pagination="pagination"
                                :row-count="media.total"
                                :processing="processing"
                                :page-size-options="perPageOptions"
                                item-label="fichier"
                                items-label="fichiers"
                                empty-label="Aucun fichier trouvé"
                                @update:pagination="updatePagination"
                            />
                        </template>

                        <DataTable
                            v-else
                            v-model:selected-ids="selectedIds"
                            :columns="columns"
                            :data="media.data"
                            :pagination="pagination"
                            :sorting="sorting"
                            :row-count="media.total"
                            :processing="processing || bulk.processing"
                            :selectable="canBulkUpdate"
                            :page-size-options="perPageOptions"
                            item-label="fichier"
                            items-label="fichiers"
                            empty-label="Aucun fichier trouvé"
                            :empty-message="emptyMessage"
                            @update:pagination="updatePagination"
                            @update:sorting="updateSorting"
                        >
                            <template #bulk-actions>
                                <MediaBulkActions
                                    :can-publish="canPublish"
                                    :disabled="processing || bulk.processing"
                                    @action="requestBulk"
                                    @clear="selectedIds = []"
                                />
                            </template>
                        </DataTable>
                    </div>
                </template>
            </section>
        </div>

        <div
            v-if="pageDragDepth > 0"
            class="border-primary bg-primary/10 pointer-events-none fixed inset-4 z-40 flex flex-col items-center justify-center gap-3 rounded-2xl border-2 border-dashed backdrop-blur-sm"
            aria-hidden="true"
        >
            <UploadCloud class="text-primary size-10" />
            <p class="text-lg font-medium">
                Déposez vos fichiers pour les importer{{
                    currentFolder ? ` dans « ${currentFolder.name} »` : ''
                }}
            </p>
        </div>

        <MediaUploadDialog
            v-if="canUpload"
            ref="uploadDialog"
            v-model:open="uploadOpen"
            :can-publish="canPublish"
            :extensions="extensions"
            :max-upload-kb="maxUploadKb"
            :max-tags="maxTags"
            :tags="tags"
            :folders="folders"
            :folder-id="uploadFolderId"
            @uploaded="reloadLibrary"
        />
        <MediaDetailsSheet
            v-model:open="detailsOpen"
            :item="activeItem"
            :can-publish="canPublish"
            :extensions="extensions"
            :max-upload-kb="maxUploadKb"
            :max-tags="maxTags"
            :tags="tags"
            :folders="folders"
        />
        <MediaFolderDialog
            v-if="canManageFolders"
            v-model:open="folderDialogOpen"
            :mode="folderDialogMode"
            :folders="folders"
            :max-depth="maxFolderDepth"
        />
        <ConfirmationAction
            v-model:open="deleteFolderOpen"
            :title="`Supprimer le dossier « ${deletingFolder?.name ?? ''} » ?`"
            description="Les fichiers et sous-dossiers qu’il contient seront déplacés dans le dossier parent. Aucun fichier ne sera supprimé."
            confirm-label="Supprimer le dossier"
            :processing="deletingFolderProcessing"
            @confirm="deleteFolder"
        />
        <ConfirmationAction
            v-model:open="confirmBulk"
            :title="bulkCopy.title"
            :description="bulkCopy.description"
            :confirm-label="
                bulk.action === 'delete' ? 'Supprimer' : 'Confirmer'
            "
            :processing="bulk.processing"
            @confirm="submitBulk"
        />
        <Dialog v-model:open="moveOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Déplacer les fichiers</DialogTitle>
                    <DialogDescription>
                        {{
                            selectedIds.length === 1
                                ? '1 fichier sélectionné'
                                : `${selectedIds.length} fichiers sélectionnés`
                        }}
                    </DialogDescription>
                </DialogHeader>
                <form
                    id="media-move-form"
                    class="flex flex-col gap-2"
                    @submit.prevent="submitBulk"
                >
                    <FieldLabel for="media-move-folder">Destination</FieldLabel>
                    <MediaFolderSelect
                        id="media-move-folder"
                        v-model="moveTarget"
                        :folders="folders"
                    />
                </form>
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="bulk.processing"
                        @click="moveOpen = false"
                        >Annuler</Button
                    >
                    <Button
                        type="submit"
                        form="media-move-form"
                        :disabled="bulk.processing"
                        >Déplacer</Button
                    >
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
