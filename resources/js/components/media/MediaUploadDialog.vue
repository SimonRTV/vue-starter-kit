<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import {
    CircleAlert,
    CircleCheck,
    FileText,
    UploadCloud,
    X,
} from '@lucide/vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { store } from '@/actions/App/Http/Controllers/MediaController';
import MediaFolderSelect from '@/components/media/MediaFolderSelect.vue';
import MediaTagInput from '@/components/media/MediaTagInput.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Field,
    FieldDescription,
    FieldError,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useFileDropzone } from '@/composables/useFileDropzone';
import { formatFileSize } from '@/lib/media';
import type { MediaFolder } from '@/types/media';

type QueuedFile = {
    key: number;
    file: File;
    previewUrl: string | null;
    status: 'pending' | 'uploading' | 'done' | 'error';
    error: string;
};

const MAX_FILES = 20;
const OPTIMIZABLE = ['image/jpeg', 'image/png'];

const props = defineProps<{
    canPublish: boolean;
    extensions: string[];
    maxUploadKb: number;
    maxTags: number;
    tags: string[];
    folders: MediaFolder[];
    folderId: number | null;
}>();
const open = defineModel<boolean>('open', { default: false });
const emit = defineEmits<{ uploaded: [count: number] }>();

const queue = ref<QueuedFile[]>([]);
const selectionError = ref('');
const uploading = ref(false);
const http = useHttp<{
    file: File | null;
    title: string;
    alt_text: string;
    visibility: string;
    tags: string[];
    optimize: boolean;
    folder_id: number | null;
}>({
    file: null,
    title: '',
    alt_text: '',
    visibility: 'private',
    tags: [],
    optimize: true,
    folder_id: null,
});
let nextKey = 0;

const { isDragging, selectFiles, dragEnter, dragLeave, dragOver, drop } =
    useFileDropzone({
        disabled: () => uploading.value,
        extensions: () => props.extensions,
        maxSizeKb: () => props.maxUploadKb,
        maxFiles: () => MAX_FILES - queue.value.length,
        onSelect: (file) => enqueue([file]),
        onSelectMany: (files) => enqueue(files),
        onError: (message) => {
            selectionError.value = message;
        },
    });

const isSingle = computed(() => queue.value.length === 1);
const hasImages = computed(() =>
    queue.value.some((item) => item.file.type.startsWith('image/')),
);
const hasOptimizableImages = computed(() =>
    queue.value.some((item) => OPTIMIZABLE.includes(item.file.type)),
);
const remaining = computed(() =>
    queue.value.filter((item) => item.status !== 'done'),
);
const accept = computed(() =>
    props.extensions.map((extension) => '.' + extension).join(','),
);

function enqueue(files: File[]): void {
    selectionError.value = '';
    const available = MAX_FILES - queue.value.length;

    if (available <= 0) {
        selectionError.value = `Vous pouvez importer au maximum ${MAX_FILES} fichiers à la fois.`;

        return;
    }

    queue.value.push(
        ...files.slice(0, available).map((file) => ({
            key: nextKey++,
            file,
            previewUrl: file.type.startsWith('image/')
                ? URL.createObjectURL(file)
                : null,
            status: 'pending' as const,
            error: '',
        })),
    );
}

function addFiles(files: File[]): void {
    selectFiles(files);
}

function chooseFiles(event: Event): void {
    const input = event.target as HTMLInputElement;
    selectFiles(Array.from(input.files ?? []));
    input.value = '';
}

function removeFile(item: QueuedFile): void {
    if (item.previewUrl) {
        URL.revokeObjectURL(item.previewUrl);
    }

    queue.value = queue.value.filter((value) => value.key !== item.key);
}

function reset(): void {
    queue.value.forEach((item) => {
        if (item.previewUrl) {
            URL.revokeObjectURL(item.previewUrl);
        }
    });
    queue.value = [];
    selectionError.value = '';
    http.reset();
    http.clearErrors();
}

function updateOpen(value: boolean): void {
    if (uploading.value) {
        return;
    }

    open.value = value;

    if (!value) {
        reset();
    }
}

watch(open, (value) => {
    if (value && !uploading.value) {
        http.folder_id = props.folderId;
    }
});

async function uploadAll(): Promise<void> {
    if (uploading.value || remaining.value.length === 0) {
        return;
    }

    uploading.value = true;
    selectionError.value = '';
    let uploaded = 0;
    const single = isSingle.value;

    for (const item of remaining.value) {
        item.status = 'uploading';
        item.error = '';
        http.file = item.file;

        if (!single) {
            http.title = '';
            http.alt_text = '';
        }

        try {
            const response = await http.post(store.url());

            if (response) {
                item.status = 'done';
                uploaded++;
            } else {
                item.status = 'error';
                item.error = Object.values(http.errors).join(' ');
            }
        } catch {
            item.status = 'error';
            item.error = 'L’importation a échoué. Réessayez.';
        }
    }

    http.file = null;
    uploading.value = false;

    if (uploaded > 0) {
        emit('uploaded', uploaded);
        toast.success(
            uploaded === 1
                ? 'Le fichier a été importé.'
                : `${uploaded} fichiers ont été importés.`,
        );
    }

    queue.value
        .filter((item) => item.status === 'done')
        .forEach((item) => removeFile(item));

    if (queue.value.length === 0) {
        updateOpen(false);
    }
}

onBeforeUnmount(reset);
defineExpose({ addFiles });
</script>

<template>
    <Dialog :open="open" @update:open="updateOpen">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
            <DialogHeader>
                <DialogTitle>Importer des fichiers</DialogTitle>
                <DialogDescription>
                    {{ extensions.join(', ').toUpperCase() }} ·
                    {{ formatFileSize(maxUploadKb * 1024) }} maximum par fichier
                    · jusqu’à {{ MAX_FILES }} fichiers à la fois. Les fichiers
                    sont privés par défaut.
                </DialogDescription>
            </DialogHeader>

            <form
                id="media-upload-form"
                class="flex flex-col gap-5"
                @submit.prevent="uploadAll"
            >
                <div
                    class="focus-within:ring-ring relative flex flex-col items-center gap-2 rounded-xl border-2 border-dashed p-6 text-center transition-colors focus-within:ring-2 focus-within:ring-offset-2"
                    :class="[
                        isDragging
                            ? 'border-primary bg-primary/10'
                            : 'border-border bg-muted/30 hover:bg-muted/60',
                        uploading ? 'opacity-60' : '',
                    ]"
                    @dragenter="dragEnter"
                    @dragleave="dragLeave"
                    @dragover="dragOver"
                    @drop.stop="drop"
                >
                    <UploadCloud
                        aria-hidden="true"
                        class="text-muted-foreground size-8"
                    />
                    <p class="font-medium">
                        {{
                            isDragging
                                ? 'Déposez vos fichiers ici'
                                : 'Glissez-déposez vos fichiers ici'
                        }}
                    </p>
                    <label
                        for="media-upload-files"
                        class="text-primary text-sm font-medium underline underline-offset-4"
                        >ou cliquez pour les choisir</label
                    >
                    <input
                        id="media-upload-files"
                        type="file"
                        multiple
                        :accept="accept"
                        :disabled="uploading"
                        aria-describedby="media-upload-error"
                        class="absolute inset-0 size-full cursor-pointer opacity-0 disabled:cursor-not-allowed"
                        @change="chooseFiles"
                    />
                </div>
                <FieldError
                    v-if="selectionError"
                    id="media-upload-error"
                    role="alert"
                    >{{ selectionError }}</FieldError
                >

                <ul
                    v-if="queue.length"
                    class="flex flex-col divide-y rounded-lg border"
                    aria-label="Fichiers à importer"
                >
                    <li
                        v-for="item in queue"
                        :key="item.key"
                        class="flex items-center gap-3 p-3"
                    >
                        <img
                            v-if="item.previewUrl"
                            :src="item.previewUrl"
                            alt=""
                            class="bg-muted size-10 shrink-0 rounded object-cover"
                        />
                        <div
                            v-else
                            class="bg-muted flex size-10 shrink-0 items-center justify-center rounded"
                        >
                            <FileText
                                class="text-muted-foreground size-5"
                                aria-hidden="true"
                            />
                        </div>
                        <div class="flex min-w-0 flex-1 flex-col gap-1">
                            <p class="truncate text-sm font-medium">
                                {{ item.file.name }}
                            </p>
                            <p
                                v-if="item.status === 'error'"
                                class="text-destructive text-xs"
                                role="alert"
                            >
                                {{ item.error }}
                            </p>
                            <progress
                                v-else-if="item.status === 'uploading'"
                                class="accent-primary h-1.5 w-full"
                                :value="http.progress?.percentage ?? 0"
                                max="100"
                                :aria-label="`Progression de ${item.file.name}`"
                            />
                            <p v-else class="text-muted-foreground text-xs">
                                {{ formatFileSize(item.file.size) }}
                            </p>
                        </div>
                        <CircleCheck
                            v-if="item.status === 'done'"
                            class="size-4 text-emerald-600"
                            aria-label="Importé"
                        />
                        <CircleAlert
                            v-else-if="item.status === 'error'"
                            class="text-destructive size-4"
                            aria-hidden="true"
                        />
                        <Button
                            v-if="item.status !== 'uploading'"
                            type="button"
                            variant="ghost"
                            size="icon"
                            class="size-8"
                            :disabled="uploading"
                            :aria-label="`Retirer ${item.file.name}`"
                            @click="removeFile(item)"
                        >
                            <X class="size-4" aria-hidden="true" />
                        </Button>
                    </li>
                </ul>

                <div v-if="queue.length" class="grid gap-4 sm:grid-cols-2">
                    <template v-if="isSingle">
                        <Field>
                            <FieldLabel for="media-upload-title"
                                >Titre (facultatif)</FieldLabel
                            >
                            <Input
                                id="media-upload-title"
                                v-model="http.title"
                                maxlength="255"
                                :disabled="uploading"
                            />
                        </Field>
                        <Field v-if="hasImages">
                            <FieldLabel for="media-upload-alt"
                                >Texte alternatif</FieldLabel
                            >
                            <Input
                                id="media-upload-alt"
                                v-model="http.alt_text"
                                maxlength="500"
                                :disabled="uploading"
                            />
                            <FieldDescription
                                >Décrit l’image pour les lecteurs
                                d’écran.</FieldDescription
                            >
                        </Field>
                    </template>
                    <Field :class="canPublish ? '' : 'sm:col-span-2'">
                        <FieldLabel for="media-upload-folder"
                            >Dossier</FieldLabel
                        >
                        <MediaFolderSelect
                            id="media-upload-folder"
                            v-model="http.folder_id"
                            :folders="folders"
                            :disabled="uploading"
                        />
                    </Field>
                    <Field class="sm:col-span-2">
                        <FieldLabel for="media-upload-tags"
                            >Étiquettes</FieldLabel
                        >
                        <MediaTagInput
                            id="media-upload-tags"
                            v-model="http.tags"
                            :suggestions="tags"
                            :max="maxTags"
                            :disabled="uploading"
                        />
                        <FieldDescription v-if="!isSingle"
                            >Appliquées à tous les fichiers de cet
                            import.</FieldDescription
                        >
                    </Field>
                    <Field v-if="canPublish">
                        <FieldLabel for="media-upload-visibility"
                            >Accès</FieldLabel
                        >
                        <Select v-model="http.visibility" :disabled="uploading">
                            <SelectTrigger id="media-upload-visibility"
                                ><SelectValue
                            /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="private">Privé</SelectItem>
                                <SelectItem value="public">Public</SelectItem>
                            </SelectContent>
                        </Select>
                        <FieldDescription
                            >Public : accessible à toute personne disposant du
                            lien.</FieldDescription
                        >
                    </Field>
                    <Field
                        v-if="hasOptimizableImages"
                        orientation="horizontal"
                        class="items-start sm:col-span-2"
                    >
                        <Checkbox
                            id="media-upload-optimize"
                            v-model="http.optimize"
                            :disabled="uploading"
                        />
                        <div class="flex flex-col gap-1">
                            <FieldLabel for="media-upload-optimize"
                                >Optimiser les images JPEG et PNG</FieldLabel
                            >
                            <FieldDescription
                                >Convertit en WebP lorsque le fichier obtenu est
                                plus léger. L’orientation de la photo est
                                conservée.</FieldDescription
                            >
                        </div>
                    </Field>
                </div>
            </form>

            <DialogFooter>
                <Button
                    type="button"
                    variant="outline"
                    :disabled="uploading"
                    @click="updateOpen(false)"
                    >Annuler</Button
                >
                <Button
                    type="submit"
                    form="media-upload-form"
                    :disabled="uploading || remaining.length === 0"
                >
                    {{
                        uploading
                            ? 'Importation…'
                            : remaining.length > 1
                              ? `Importer ${remaining.length} fichiers`
                              : 'Importer'
                    }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
