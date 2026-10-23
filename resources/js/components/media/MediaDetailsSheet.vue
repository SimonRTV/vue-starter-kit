<script setup lang="ts">
import { Link, router, useForm } from '@inertiajs/vue3';
import {
    Copy,
    Download,
    Music,
    RefreshCw,
    Trash2,
    TriangleAlert,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import {
    destroy,
    replace,
    update,
} from '@/actions/App/Http/Controllers/MediaController';
import { ConfirmationAction } from '@/components/application';
import MediaFolderSelect from '@/components/media/MediaFolderSelect.vue';
import MediaTagInput from '@/components/media/MediaTagInput.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import { Separator } from '@/components/ui/separator';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import {
    fileExtension,
    fileIcon,
    formatFileSize,
    formatMediaDate,
    isMissingAltText,
} from '@/lib/media';
import type { MediaFolder, MediaItem } from '@/types/media';

const props = defineProps<{
    item: MediaItem | null;
    canPublish: boolean;
    extensions: string[];
    maxUploadKb: number;
    maxTags: number;
    tags: string[];
    folders: MediaFolder[];
}>();
const open = defineModel<boolean>('open', { default: false });

const form = useForm<{
    title: string;
    alt_text: string;
    visibility: MediaItem['visibility'];
    tags: string[];
    folder_id: number | null;
}>({
    title: '',
    alt_text: '',
    visibility: 'private',
    tags: [],
    folder_id: null,
});
const replacement = useForm<{ file: File | null; optimize: boolean }>({
    file: null,
    optimize: true,
});
const replaceInputKey = ref(0);
const confirmDelete = ref(false);
const deleting = ref(false);
const deleteError = ref('');

const canMakePublic = computed(
    () => props.canPublish || props.item?.visibility === 'public',
);
const replaceAccept = computed(() =>
    props.item?.is_image
        ? '.jpg,.jpeg,.png,.webp'
        : props.extensions.map((extension) => '.' + extension).join(','),
);
const isUsed = computed(() => (props.item?.usages.length ?? 0) > 0);
const playerKey = computed(() =>
    props.item
        ? [props.item.id, props.item.original_name, props.item.size].join(':')
        : '',
);

watch(
    () => props.item,
    (item, previous) => {
        if (!item) {
            return;
        }

        const values = {
            title: item.title,
            alt_text: item.alt_text ?? '',
            visibility: item.visibility,
            tags: [...item.tags],
            folder_id: item.folder_id,
        };

        if (item.id !== previous?.id || !form.isDirty) {
            form.defaults(values);
            form.reset();
            form.clearErrors();
        }

        if (item.id !== previous?.id) {
            replacement.reset();
            replaceInputKey.value++;
            replacement.clearErrors();
            deleteError.value = '';
        }
    },
    { immediate: true },
);

function save(): void {
    if (!props.item) {
        return;
    }

    form.put(update.url(props.item.id), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => form.defaults(),
    });
}

function chooseReplacement(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;
    replacement.clearErrors();

    if (file && file.size > props.maxUploadKb * 1024) {
        replacement.setError(
            'file',
            'Ce fichier dépasse la taille maximale autorisée.',
        );
        input.value = '';

        return;
    }

    replacement.file = file;
}

function submitReplacement(): void {
    if (!props.item || !replacement.file) {
        return;
    }

    replacement.post(replace.url(props.item.id), {
        forceFormData: true,
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            replacement.reset();
            replaceInputKey.value++;
        },
    });
}

function remove(): void {
    if (!props.item || deleting.value) {
        return;
    }

    deleting.value = true;
    deleteError.value = '';
    router.delete(destroy.url(props.item.id), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            confirmDelete.value = false;
            open.value = false;
        },
        onError: (errors) => {
            deleteError.value = String(
                errors.media ?? 'Impossible de supprimer le fichier.',
            );
            confirmDelete.value = false;
        },
        onFinish: () => {
            deleting.value = false;
        },
    });
}

async function copyLink(): Promise<void> {
    if (!props.item) {
        return;
    }

    try {
        await navigator.clipboard.writeText(props.item.download_url);
        toast.success('Le lien public a été copié.');
    } catch {
        toast.error('Impossible de copier le lien. Copiez-le manuellement.');
    }
}
</script>

<template>
    <Sheet v-model:open="open">
        <SheetContent
            class="w-full gap-0 overflow-y-auto sm:max-w-lg"
            :aria-describedby="item ? 'media-details-description' : undefined"
        >
            <template v-if="item">
                <SheetHeader class="border-b">
                    <SheetTitle class="pr-6 break-words">{{
                        item.title
                    }}</SheetTitle>
                    <SheetDescription id="media-details-description">
                        {{ fileExtension(item) }} ·
                        {{ formatFileSize(item.size) }} · importé le
                        {{ formatMediaDate(item.created_at) }}
                        <template v-if="item.uploader">
                            par {{ item.uploader }}</template
                        >
                    </SheetDescription>
                </SheetHeader>

                <div class="flex flex-col gap-6 p-4">
                    <div
                        class="bg-muted flex aspect-video items-center justify-center overflow-hidden rounded-lg"
                    >
                        <video
                            v-if="item.is_video && item.stream_url"
                            :key="playerKey"
                            :src="item.stream_url"
                            :poster="item.thumbnail_url ?? undefined"
                            :aria-label="'Lecteur vidéo : ' + item.title"
                            controls
                            preload="metadata"
                            class="size-full bg-black object-contain"
                        />
                        <div
                            v-else-if="item.is_audio && item.stream_url"
                            class="flex w-full flex-col items-center gap-4 px-4"
                        >
                            <Music
                                class="text-muted-foreground size-14"
                                aria-hidden="true"
                            />
                            <audio
                                :key="playerKey"
                                :src="item.stream_url"
                                :aria-label="'Lecteur audio : ' + item.title"
                                controls
                                preload="metadata"
                                class="w-full"
                            />
                        </div>
                        <img
                            v-else-if="item.thumbnail_url"
                            :src="item.thumbnail_url"
                            :alt="item.alt_text ?? ''"
                            class="size-full object-contain"
                        />
                        <component
                            :is="fileIcon(item)"
                            v-else
                            class="text-muted-foreground size-14"
                            aria-hidden="true"
                        />
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <Button size="sm" variant="outline" as-child>
                            <a :href="item.download_url"
                                ><Download
                                    data-icon="inline-start"
                                />Télécharger</a
                            >
                        </Button>
                        <Button
                            v-if="item.visibility === 'public'"
                            size="sm"
                            variant="outline"
                            @click="copyLink"
                            ><Copy data-icon="inline-start" />Copier le lien
                            public</Button
                        >
                    </div>

                    <dl
                        class="grid grid-cols-[auto_minmax(0,1fr)] gap-x-4 gap-y-2 text-sm"
                    >
                        <dt class="text-muted-foreground">Nom du fichier</dt>
                        <dd class="break-all">{{ item.original_name }}</dd>
                        <dt class="text-muted-foreground">Type</dt>
                        <dd>{{ item.mime_type }}</dd>
                        <dt class="text-muted-foreground">Accès</dt>
                        <dd>
                            <Badge
                                :variant="
                                    item.visibility === 'public'
                                        ? 'default'
                                        : 'secondary'
                                "
                                >{{
                                    item.visibility === 'public'
                                        ? 'Public'
                                        : 'Privé'
                                }}</Badge
                            >
                        </dd>
                    </dl>

                    <section
                        class="flex flex-col gap-2"
                        aria-labelledby="media-usages-title"
                    >
                        <h3 id="media-usages-title" class="text-sm font-medium">
                            Utilisé dans
                        </h3>
                        <ul
                            v-if="item.usages.length"
                            class="flex flex-col gap-1.5 text-sm"
                        >
                            <li
                                v-for="usage in item.usages"
                                :key="usage.id"
                                class="flex items-center justify-between gap-3 rounded-md border px-3 py-2"
                            >
                                <Link
                                    v-if="usage.url"
                                    :href="usage.url"
                                    class="truncate font-medium hover:underline"
                                    >{{ usage.title }}</Link
                                >
                                <span v-else class="truncate">{{
                                    usage.title
                                }}</span>
                                <Badge
                                    v-if="usage.trashed"
                                    variant="outline"
                                    class="shrink-0"
                                    >Corbeille</Badge
                                >
                            </li>
                        </ul>
                        <p v-else class="text-muted-foreground text-sm">
                            Ce fichier n’est joint à aucun contenu.
                        </p>
                    </section>

                    <template v-if="item.can.update">
                        <Separator />
                        <form
                            class="flex flex-col gap-4"
                            aria-label="Informations du fichier"
                            @submit.prevent="save"
                        >
                            <Field>
                                <FieldLabel for="media-details-title"
                                    >Titre</FieldLabel
                                >
                                <Input
                                    id="media-details-title"
                                    v-model="form.title"
                                    required
                                    maxlength="255"
                                />
                                <FieldError>{{ form.errors.title }}</FieldError>
                            </Field>
                            <Field v-if="item.is_image">
                                <FieldLabel for="media-details-alt"
                                    >Texte alternatif</FieldLabel
                                >
                                <Input
                                    id="media-details-alt"
                                    v-model="form.alt_text"
                                    maxlength="500"
                                    :aria-invalid="
                                        isMissingAltText({
                                            is_image: true,
                                            alt_text: form.alt_text,
                                        })
                                    "
                                />
                                <FieldDescription
                                    v-if="
                                        isMissingAltText({
                                            is_image: true,
                                            alt_text: form.alt_text,
                                        })
                                    "
                                    class="flex items-center gap-1.5 text-amber-700 dark:text-amber-400"
                                    ><TriangleAlert
                                        class="size-4 shrink-0"
                                        aria-hidden="true"
                                    />Ajoutez une description pour les lecteurs
                                    d’écran.</FieldDescription
                                >
                                <FieldError>{{
                                    form.errors.alt_text
                                }}</FieldError>
                            </Field>
                            <Field>
                                <FieldLabel for="media-details-folder"
                                    >Dossier</FieldLabel
                                >
                                <MediaFolderSelect
                                    id="media-details-folder"
                                    v-model="form.folder_id"
                                    :folders="folders"
                                />
                                <FieldError>{{
                                    form.errors.folder_id
                                }}</FieldError>
                            </Field>
                            <Field>
                                <FieldLabel for="media-details-tags"
                                    >Étiquettes</FieldLabel
                                >
                                <MediaTagInput
                                    id="media-details-tags"
                                    v-model="form.tags"
                                    :suggestions="tags"
                                    :max="maxTags"
                                    :invalid="!!form.errors.tags"
                                />
                                <FieldError>{{ form.errors.tags }}</FieldError>
                            </Field>
                            <Field>
                                <FieldLabel for="media-details-visibility"
                                    >Accès</FieldLabel
                                >
                                <Select v-model="form.visibility">
                                    <SelectTrigger id="media-details-visibility"
                                        ><SelectValue
                                    /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="private"
                                            >Privé</SelectItem
                                        >
                                        <SelectItem
                                            v-if="canMakePublic"
                                            value="public"
                                            >Public</SelectItem
                                        >
                                    </SelectContent>
                                </Select>
                                <FieldError>{{
                                    form.errors.visibility
                                }}</FieldError>
                            </Field>
                            <div>
                                <Button
                                    type="submit"
                                    :disabled="form.processing || !form.isDirty"
                                    >{{
                                        form.processing
                                            ? 'Enregistrement…'
                                            : 'Enregistrer'
                                    }}</Button
                                >
                            </div>
                        </form>

                        <Separator />
                        <form
                            class="flex flex-col gap-3"
                            aria-labelledby="media-replace-title"
                            @submit.prevent="submitReplacement"
                        >
                            <div class="flex flex-col gap-1">
                                <h3
                                    id="media-replace-title"
                                    class="text-sm font-medium"
                                >
                                    Remplacer le fichier
                                </h3>
                                <p class="text-muted-foreground text-sm">
                                    Le nouveau fichier garde le même lien et
                                    reste joint aux mêmes contenus.
                                    <template v-if="item.is_image"
                                        >Choisissez une autre image.</template
                                    >
                                </p>
                            </div>
                            <Field>
                                <FieldLabel
                                    for="media-replace-file"
                                    class="sr-only"
                                    >Nouveau fichier</FieldLabel
                                >
                                <Input
                                    id="media-replace-file"
                                    :key="replaceInputKey"
                                    type="file"
                                    :accept="replaceAccept"
                                    :disabled="replacement.processing"
                                    @change="chooseReplacement"
                                />
                                <FieldError>{{
                                    replacement.errors.file
                                }}</FieldError>
                            </Field>
                            <Field
                                v-if="
                                    replacement.file &&
                                    ['image/jpeg', 'image/png'].includes(
                                        replacement.file.type,
                                    )
                                "
                                orientation="horizontal"
                            >
                                <Checkbox
                                    id="media-replace-optimize"
                                    v-model="replacement.optimize"
                                />
                                <FieldLabel for="media-replace-optimize"
                                    >Optimiser en WebP si plus léger</FieldLabel
                                >
                            </Field>
                            <div class="flex items-center gap-3">
                                <Button
                                    type="submit"
                                    variant="outline"
                                    :disabled="
                                        replacement.processing ||
                                        !replacement.file
                                    "
                                    ><RefreshCw data-icon="inline-start" />{{
                                        replacement.processing
                                            ? 'Remplacement…'
                                            : 'Remplacer'
                                    }}</Button
                                >
                                <progress
                                    v-if="replacement.progress"
                                    class="accent-primary"
                                    :value="replacement.progress.percentage"
                                    max="100"
                                    aria-label="Progression du remplacement"
                                />
                            </div>
                        </form>
                    </template>

                    <template v-if="item.can.delete">
                        <Separator />
                        <div class="flex flex-col gap-2">
                            <Button
                                variant="destructive"
                                class="self-start"
                                :disabled="isUsed || deleting"
                                @click="confirmDelete = true"
                                ><Trash2 data-icon="inline-start" />Supprimer le
                                fichier</Button
                            >
                            <p
                                v-if="isUsed"
                                class="text-muted-foreground text-sm"
                            >
                                Détachez ce fichier de ses contenus avant de le
                                supprimer.
                            </p>
                            <p
                                v-if="deleteError"
                                role="alert"
                                class="text-destructive text-sm"
                            >
                                {{ deleteError }}
                            </p>
                        </div>
                    </template>
                </div>

                <ConfirmationAction
                    v-model:open="confirmDelete"
                    :title="'Supprimer « ' + item.title + ' » ?'"
                    description="Le fichier sera supprimé définitivement."
                    confirm-label="Supprimer"
                    :processing="deleting"
                    @confirm="remove"
                />
            </template>
        </SheetContent>
    </Sheet>
</template>
