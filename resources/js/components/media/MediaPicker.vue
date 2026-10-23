<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import {
    Check,
    ChevronRight,
    Folder,
    FolderTree,
    Globe,
    Lock,
    Search,
} from '@lucide/vue';
import { watchDebounced } from '@vueuse/core';
import { computed, onUnmounted, ref, watch } from 'vue';
import { index } from '@/actions/App/Http/Controllers/MediaController';
import { index as folderIndex } from '@/actions/App/Http/Controllers/MediaFolderController';
import MediaFolderTree from '@/components/media/MediaFolderTree.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { fileExtension, fileIcon, formatFileSize } from '@/lib/media';
import type {
    MediaFilters,
    MediaFolder,
    MediaItem,
    MediaStats,
    MediaType,
} from '@/types/media';
import type { Pagination } from '@/types/pagination';

type FolderTarget = MediaFilters['folder'];

const props = withDefaults(defineProps<{ attachedIds?: string[] }>(), {
    attachedIds: () => [],
});
const open = defineModel<boolean>('open', { default: false });
const emit = defineEmits<{ select: [media: MediaItem] }>();
const http = useHttp<
    {
        search: string;
        page: number;
        type: MediaType | null;
        visibility: MediaItem['visibility'] | null;
        folder: FolderTarget;
    },
    Pagination<MediaItem>
>({
    search: '',
    page: 1,
    type: null,
    visibility: null,
    folder: null,
});
const folderHttp = useHttp<
    Record<string, never>,
    { folders: MediaFolder[]; stats: MediaStats }
>({});
const results = ref<Pagination<MediaItem> | null>(null);
const folders = ref<MediaFolder[]>([]);
const stats = ref<MediaStats | null>(null);
const foldersOpen = ref(false);
const error = ref('');
let requestId = 0;

const foldersById = computed(
    () => new Map(folders.value.map((folder) => [folder.id, folder])),
);
const folderTrail = computed(() => {
    const trail: MediaFolder[] = [];

    for (
        let folder =
            typeof http.folder === 'number'
                ? foldersById.value.get(http.folder)
                : undefined;
        folder;
        folder = folder.parent_id
            ? foldersById.value.get(folder.parent_id)
            : undefined
    ) {
        trail.unshift(folder);
    }

    return trail;
});
const locationTitle = computed(() => {
    if (http.folder === 'none') {
        return 'Non classés';
    }

    return folderTrail.value.at(-1)?.name ?? 'Tous les fichiers';
});
const attached = computed(() => new Set(props.attachedIds));
const isFiltered = computed(
    () =>
        http.search.trim() !== '' ||
        http.type !== null ||
        http.visibility !== null,
);

async function load(page = 1): Promise<void> {
    const current = ++requestId;
    http.cancel();
    http.page = page;
    error.value = '';

    try {
        const response = await http.get(index.url(), {
            headers: { Accept: 'application/json' },
            onHttpException: () => false,
            onNetworkError: () => false,
        });

        if (current === requestId) {
            results.value = response;
        }
    } catch {
        if (current === requestId) {
            error.value = 'Impossible de charger les fichiers. Réessayez.';
        }
    }
}

async function loadFolders(): Promise<void> {
    folderHttp.cancel();

    try {
        const response = await folderHttp.get(folderIndex.url(), {
            headers: { Accept: 'application/json' },
            onHttpException: () => false,
            onNetworkError: () => false,
        });
        folders.value = response.folders;
        stats.value = response.stats;

        if (
            typeof http.folder === 'number' &&
            !foldersById.value.has(http.folder)
        ) {
            selectFolder(null);
        }
    } catch {
        // The picker still works without the folder tree.
    }
}

function selectFolder(target: FolderTarget): void {
    http.folder = target;
    foldersOpen.value = false;
    void load();
}

function selectVisibility(value: unknown): void {
    const visibility = value === 'public' || value === 'private' ? value : null;

    if (visibility !== http.visibility) {
        http.visibility = visibility;
        void load();
    }
}

function selectType(value: unknown): void {
    const type = value === 'image' || value === 'document' ? value : null;

    if (type !== http.type) {
        http.type = type;
        void load();
    }
}

function choose(item: MediaItem): void {
    if (!attached.value.has(item.id)) {
        emit('select', item);
    }
}

watchDebounced(
    () => http.search,
    () => {
        if (open.value) {
            void load();
        }
    },
    { debounce: 300, maxWait: 1000 },
);
watch(open, (value) => {
    if (value) {
        results.value = null;
        void load();
        void loadFolders();
    } else {
        requestId++;
        http.cancel();
        folderHttp.cancel();
    }
});
onUnmounted(() => {
    http.cancel();
    folderHttp.cancel();
});
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent
            class="flex max-h-[90vh] flex-col gap-4 overflow-hidden sm:max-w-5xl"
        >
            <DialogHeader
                ><DialogTitle>Choisir un fichier</DialogTitle
                ><DialogDescription
                    >Parcourez vos dossiers ou recherchez un fichier de votre
                    médiathèque.</DialogDescription
                ></DialogHeader
            >
            <div
                class="grid min-h-0 flex-1 gap-4 lg:grid-cols-[14rem_minmax(0,1fr)]"
            >
                <aside class="flex min-h-0 min-w-0 flex-col gap-2">
                    <Button
                        variant="outline"
                        class="justify-between lg:hidden"
                        :aria-expanded="foldersOpen"
                        aria-controls="media-picker-folders"
                        @click="foldersOpen = !foldersOpen"
                    >
                        <span class="flex min-w-0 items-center gap-2"
                            ><FolderTree
                                class="size-4 shrink-0"
                                aria-hidden="true"
                            /><span class="truncate">{{
                                locationTitle
                            }}</span></span
                        >
                        <ChevronRight
                            class="size-4 transition-transform"
                            :class="foldersOpen ? 'rotate-90' : ''"
                            aria-hidden="true"
                        />
                    </Button>
                    <div
                        id="media-picker-folders"
                        class="max-h-60 overflow-y-auto rounded-xl border p-2 lg:block lg:max-h-none"
                        :class="foldersOpen ? 'block' : 'hidden'"
                    >
                        <div
                            v-if="!stats && folderHttp.processing"
                            class="flex flex-col gap-2"
                            aria-label="Chargement des dossiers"
                        >
                            <Skeleton v-for="i in 4" :key="i" class="h-7" />
                        </div>
                        <MediaFolderTree
                            v-else
                            :folders="folders"
                            :active="http.folder"
                            :total-count="stats?.count ?? 0"
                            :unfiled-count="stats?.unfiled ?? 0"
                            :can-manage="false"
                            :max-depth="0"
                            :accepts-drops="false"
                            @select="selectFolder"
                        />
                    </div>
                </aside>

                <section
                    class="flex min-h-0 min-w-0 flex-col gap-3"
                    aria-label="Fichiers"
                >
                    <div class="flex flex-wrap items-center gap-2">
                        <form
                            class="relative min-w-48 flex-1"
                            role="search"
                            @submit.prevent="load()"
                        >
                            <Search
                                class="text-muted-foreground pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2"
                                aria-hidden="true"
                            />
                            <Input
                                v-model="http.search"
                                type="search"
                                class="pl-8"
                                maxlength="100"
                                placeholder="Titre, nom de fichier ou texte alternatif"
                                aria-label="Rechercher un fichier"
                            />
                        </form>
                        <ToggleGroup
                            type="single"
                            variant="outline"
                            :model-value="http.type ?? 'all'"
                            aria-label="Type de fichier"
                            @update:model-value="selectType"
                        >
                            <ToggleGroupItem value="all" class="px-3"
                                >Tous</ToggleGroupItem
                            >
                            <ToggleGroupItem value="image" class="px-3"
                                >Images</ToggleGroupItem
                            >
                            <ToggleGroupItem value="document" class="px-3"
                                >Documents</ToggleGroupItem
                            >
                        </ToggleGroup>
                        <ToggleGroup
                            type="single"
                            variant="outline"
                            :model-value="http.visibility ?? 'all'"
                            aria-label="Visibilité"
                            @update:model-value="selectVisibility"
                        >
                            <ToggleGroupItem value="all" class="px-3"
                                >Tous</ToggleGroupItem
                            >
                            <ToggleGroupItem value="public" class="px-3"
                                ><Globe
                                    class="size-3.5"
                                    aria-hidden="true"
                                />Public</ToggleGroupItem
                            >
                            <ToggleGroupItem value="private" class="px-3"
                                ><Lock
                                    class="size-3.5"
                                    aria-hidden="true"
                                />Privé</ToggleGroupItem
                            >
                        </ToggleGroup>
                    </div>

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

                    <p
                        v-if="error"
                        role="alert"
                        class="text-destructive text-sm"
                    >
                        {{ error }}
                    </p>

                    <div class="min-h-0 flex-1 overflow-y-auto">
                        <div
                            v-if="http.processing && !results"
                            class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4"
                            aria-label="Chargement"
                        >
                            <Skeleton
                                v-for="i in 8"
                                :key="i"
                                class="aspect-[4/3]"
                            />
                        </div>
                        <div
                            v-else-if="results?.data.length"
                            class="grid grid-cols-2 gap-3 transition-opacity sm:grid-cols-3 xl:grid-cols-4"
                            :class="http.processing ? 'opacity-60' : ''"
                            :aria-busy="http.processing"
                        >
                            <button
                                v-for="item in results.data"
                                :key="item.id"
                                type="button"
                                class="group bg-card focus-visible:ring-ring flex min-w-0 flex-col overflow-hidden rounded-xl border text-left transition-shadow focus-visible:ring-2 focus-visible:outline-none enabled:hover:shadow-md disabled:cursor-not-allowed disabled:opacity-60"
                                :disabled="attached.has(item.id)"
                                :title="
                                    attached.has(item.id)
                                        ? 'Ce fichier est déjà joint à la page.'
                                        : undefined
                                "
                                @click="choose(item)"
                            >
                                <span
                                    class="bg-muted relative flex aspect-[4/3] items-center justify-center overflow-hidden"
                                >
                                    <img
                                        v-if="item.thumbnail_url"
                                        :src="item.thumbnail_url"
                                        :alt="item.alt_text ?? ''"
                                        loading="lazy"
                                        class="size-full object-contain transition-transform group-enabled:group-hover:scale-[1.02]"
                                    />
                                    <span
                                        v-else
                                        class="flex flex-col items-center gap-2"
                                    >
                                        <component
                                            :is="fileIcon(item)"
                                            class="text-muted-foreground size-10"
                                            aria-hidden="true"
                                        />
                                        <span
                                            class="text-muted-foreground text-xs font-semibold tracking-wider"
                                            >{{ fileExtension(item) }}</span
                                        >
                                    </span>
                                    <span
                                        v-if="attached.has(item.id)"
                                        class="bg-background/90 absolute top-2 right-2 flex items-center gap-1 rounded-md border px-1.5 py-0.5 text-xs font-medium shadow-sm"
                                        ><Check
                                            class="size-3"
                                            aria-hidden="true"
                                        />Déjà joint</span
                                    >
                                </span>
                                <span class="flex min-w-0 flex-col gap-1 p-3">
                                    <span
                                        class="truncate text-sm font-medium"
                                        >{{ item.title }}</span
                                    >
                                    <span
                                        class="text-muted-foreground flex items-center gap-1.5 text-xs"
                                    >
                                        <Globe
                                            v-if="item.visibility === 'public'"
                                            class="size-3 shrink-0"
                                            aria-hidden="true"
                                        />
                                        <Lock
                                            v-else
                                            class="size-3 shrink-0"
                                            aria-hidden="true"
                                        />
                                        <span class="truncate"
                                            >{{
                                                item.visibility === 'public'
                                                    ? 'Public'
                                                    : 'Privé'
                                            }}
                                            · {{ fileExtension(item) }} ·
                                            {{
                                                formatFileSize(item.size)
                                            }}</span
                                        >
                                    </span>
                                    <span
                                        v-if="
                                            http.folder === null &&
                                            item.folder_id !== null &&
                                            foldersById.has(item.folder_id)
                                        "
                                        class="text-muted-foreground flex items-center gap-1.5 text-xs"
                                    >
                                        <Folder
                                            class="size-3 shrink-0"
                                            aria-hidden="true"
                                        />
                                        <span class="truncate">{{
                                            foldersById.get(item.folder_id)
                                                ?.path
                                        }}</span>
                                    </span>
                                </span>
                            </button>
                        </div>
                        <p
                            v-else-if="!error && results"
                            class="text-muted-foreground py-10 text-center text-sm"
                        >
                            {{
                                isFiltered
                                    ? 'Aucun fichier ne correspond à votre recherche.'
                                    : http.folder === null
                                      ? 'Aucun fichier trouvé. Importez vos fichiers dans la médiathèque.'
                                      : 'Ce dossier ne contient aucun fichier.'
                            }}
                        </p>
                    </div>

                    <div
                        v-if="results && results.last_page > 1"
                        class="flex items-center justify-between gap-3"
                    >
                        <Button
                            variant="outline"
                            :disabled="
                                http.processing || results.current_page <= 1
                            "
                            @click="load(results.current_page - 1)"
                            >Précédent</Button
                        >
                        <span class="text-sm tabular-nums"
                            >{{ results.current_page }} /
                            {{ results.last_page }}</span
                        >
                        <Button
                            variant="outline"
                            :disabled="
                                http.processing ||
                                results.current_page >= results.last_page
                            "
                            @click="load(results.current_page + 1)"
                            >Suivant</Button
                        >
                    </div>
                </section>
            </div>
        </DialogContent>
    </Dialog>
</template>
