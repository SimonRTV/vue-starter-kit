<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { ImageOff, Trash2, UploadCloud } from '@lucide/vue';
import { computed, onBeforeUnmount, ref } from 'vue';
import ConfirmationAction from '@/components/application/ConfirmationAction.vue';
import { Button } from '@/components/ui/button';
import {
    Field,
    FieldDescription,
    FieldError,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { useFileDropzone } from '@/composables/useFileDropzone';
import { destroy, store } from '@/routes/seo/images';

const EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
const MAX_SIZE_KB = 5 * 1024;
const MIN_DIMENSION = 200;
const MAX_DIMENSION = 4096;
const RECOMMENDED = { width: 1200, height: 630 };

const props = defineProps<{
    target: string;
    field: 'og_image' | 'twitter_image';
    label: string;
    hint?: string;
    uploadedUrl: string | null;
    inheritedUrl: string;
    inheritedLabel: string;
    urlError?: string;
}>();

const emit = defineEmits<{
    uploaded: [];
}>();

const url = defineModel<string>('url', { required: true });

const upload = useForm<{ image: File | null }>({ image: null });
const localPreview = ref<string | null>(null);
const dimensionWarning = ref('');
const removeOpen = ref(false);
const removing = ref(false);

const currentImage = computed(
    () =>
        localPreview.value ||
        url.value ||
        props.uploadedUrl ||
        props.inheritedUrl,
);
const status = computed(() => {
    if (upload.processing) {
        return 'Téléversement en cours…';
    }

    if (url.value) {
        return props.uploadedUrl
            ? 'L’URL saisie remplacera l’image téléversée à l’enregistrement.'
            : 'Image externe définie par son URL.';
    }

    if (props.uploadedUrl) {
        return 'Image téléversée.';
    }

    return props.inheritedUrl ? props.inheritedLabel : 'Aucune image définie.';
});

const { isDragging, selectFiles, dragEnter, dragLeave, dragOver, drop } =
    useFileDropzone({
        disabled: () => upload.processing || removing.value,
        extensions: () => EXTENSIONS,
        maxSizeKb: () => MAX_SIZE_KB,
        onSelect: (file) => void send(file),
        onError: (message) => upload.setError('image', message),
    });

function readDimensions(
    file: File,
): Promise<{ width: number; height: number } | null> {
    return new Promise((resolve) => {
        const image = new Image();
        const objectUrl = URL.createObjectURL(file);
        image.onload = () => {
            resolve({ width: image.naturalWidth, height: image.naturalHeight });
            URL.revokeObjectURL(objectUrl);
        };
        image.onerror = () => {
            resolve(null);
            URL.revokeObjectURL(objectUrl);
        };
        image.src = objectUrl;
    });
}

function clearPreview(): void {
    if (localPreview.value) {
        URL.revokeObjectURL(localPreview.value);
        localPreview.value = null;
    }
}

async function send(file: File): Promise<void> {
    upload.clearErrors('image');
    dimensionWarning.value = '';
    const dimensions = await readDimensions(file);

    if (!dimensions) {
        upload.setError('image', 'Ce fichier ne peut pas être lu comme image.');

        return;
    }

    const { width, height } = dimensions;

    if (
        Math.min(width, height) < MIN_DIMENSION ||
        Math.max(width, height) > MAX_DIMENSION
    ) {
        upload.setError(
            'image',
            `L’image mesure ${width} × ${height} pixels. Elle doit mesurer entre ${MIN_DIMENSION} × ${MIN_DIMENSION} et ${MAX_DIMENSION} × ${MAX_DIMENSION} pixels.`,
        );

        return;
    }

    if (
        width < RECOMMENDED.width ||
        height < RECOMMENDED.height ||
        Math.abs(width / height - RECOMMENDED.width / RECOMMENDED.height) > 0.1
    ) {
        dimensionWarning.value = `Image de ${width} × ${height} pixels : un format de ${RECOMMENDED.width} × ${RECOMMENDED.height} pixels donne des aperçus larges plus nets.`;
    }

    clearPreview();
    localPreview.value = URL.createObjectURL(file);
    upload.image = file;
    upload.post(store.url({ target: props.target, field: props.field }), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => emit('uploaded'),
        onFinish: () => {
            upload.reset();
            clearPreview();
        },
    });
}

function chooseFile(event: Event): void {
    const input = event.target as HTMLInputElement;
    selectFiles(Array.from(input.files ?? []));
    input.value = '';
}

function remove(): void {
    removing.value = true;
    router.delete(destroy.url({ target: props.target, field: props.field }), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            removeOpen.value = false;
            dimensionWarning.value = '';
        },
        onFinish: () => {
            removing.value = false;
        },
    });
}

onBeforeUnmount(clearPreview);
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="grid gap-4 md:grid-cols-[minmax(0,240px)_minmax(0,1fr)]">
            <div
                class="bg-muted relative self-start overflow-hidden rounded-lg border"
                :class="{ 'opacity-60': upload.processing }"
            >
                <img
                    v-if="currentImage"
                    :src="currentImage"
                    :alt="`Aperçu : ${label}`"
                    referrerpolicy="no-referrer"
                    class="aspect-[1200/630] w-full object-cover"
                />
                <div
                    v-else
                    class="text-muted-foreground flex aspect-[1200/630] items-center justify-center"
                >
                    <ImageOff class="size-8 opacity-40" aria-hidden="true" />
                </div>
                <Spinner
                    v-if="upload.processing"
                    class="absolute inset-0 m-auto size-6"
                />
            </div>
            <div class="flex min-w-0 flex-col gap-3">
                <p class="font-medium">{{ label }}</p>
                <p
                    :id="`${field}-status`"
                    role="status"
                    aria-live="polite"
                    class="text-muted-foreground text-sm"
                >
                    {{ status }}
                    <span v-if="upload.progress" class="tabular-nums"
                        >{{ upload.progress.percentage }} %</span
                    >
                </p>
                <div
                    class="focus-within:ring-ring relative flex items-center gap-3 rounded-lg border-2 border-dashed p-3 text-sm transition-colors focus-within:ring-2 focus-within:ring-offset-2"
                    :class="
                        isDragging
                            ? 'border-primary bg-primary/10'
                            : 'border-border bg-muted/30 hover:bg-muted/60'
                    "
                    @dragenter="dragEnter"
                    @dragleave="dragLeave"
                    @dragover="dragOver"
                    @drop.stop="drop"
                >
                    <UploadCloud
                        aria-hidden="true"
                        class="text-muted-foreground size-5 shrink-0"
                    />
                    <label :for="`${field}-file`" class="font-medium">
                        {{
                            isDragging
                                ? 'Déposez l’image ici'
                                : uploadedUrl
                                  ? 'Remplacer l’image téléversée'
                                  : 'Téléverser une image'
                        }}
                    </label>
                    <input
                        :id="`${field}-file`"
                        type="file"
                        :accept="
                            EXTENSIONS.map((extension) => '.' + extension).join(
                                ',',
                            )
                        "
                        :disabled="upload.processing || removing"
                        :aria-invalid="Boolean(upload.errors.image)"
                        :aria-describedby="`${field}-file-help ${field}-file-error`"
                        class="absolute inset-0 size-full cursor-pointer opacity-0 disabled:cursor-not-allowed"
                        @change="chooseFile"
                    />
                </div>
                <p
                    :id="`${field}-file-help`"
                    class="text-muted-foreground text-xs"
                >
                    PNG, JPG, GIF ou WebP · 5 Mo maximum · 1200 × 630 pixels
                    recommandé
                </p>
                <FieldError
                    v-if="upload.errors.image"
                    :id="`${field}-file-error`"
                    >{{ upload.errors.image }}</FieldError
                >
                <p
                    v-else-if="dimensionWarning"
                    class="text-sm text-amber-700 dark:text-amber-400"
                >
                    {{ dimensionWarning }}
                </p>
                <ConfirmationAction
                    v-if="uploadedUrl"
                    v-model:open="removeOpen"
                    title="Supprimer l’image téléversée ?"
                    :description="`Le fichier sera supprimé. ${inheritedUrl ? 'L’image de secours sera utilisée à la place.' : 'Aucune image ne sera partagée tant que vous n’en définissez pas une autre.'}`"
                    confirm-label="Supprimer l’image"
                    pending-label="Suppression…"
                    :processing="removing"
                    @confirm="remove"
                >
                    <template #trigger>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="self-start"
                            :disabled="upload.processing"
                            ><Trash2 class="size-4" />Supprimer l’image</Button
                        >
                    </template>
                </ConfirmationAction>
            </div>
        </div>
        <Field>
            <FieldLabel :for="field">Ou collez l’URL d’une image</FieldLabel>
            <Input
                :id="field"
                v-model="url"
                type="url"
                :maxlength="2048"
                placeholder="https://"
                :aria-invalid="Boolean(urlError)"
                :aria-describedby="`${field}-help ${field}-error`"
            />
            <FieldDescription :id="`${field}-help`">{{
                hint
            }}</FieldDescription>
            <FieldError v-if="urlError" :id="`${field}-error`">{{
                urlError
            }}</FieldError>
        </Field>
    </div>
</template>
