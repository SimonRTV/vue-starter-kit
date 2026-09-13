<script setup lang="ts">
import { Link, router, useForm } from '@inertiajs/vue3';
import { FileText, UploadCloud } from '@lucide/vue';
import { ref } from 'vue';
import {
    destroy,
    index,
    store,
    update,
} from '@/actions/App/Http/Controllers/MediaController';
import { ConfirmationAction, PageHeader } from '@/components/application';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Field, FieldError, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useFileDropzone } from '@/composables/useFileDropzone';
import type { MediaItem } from '@/types/media';
import type { Pagination } from '@/types/pagination';

const props = defineProps<{
    media: Pagination<MediaItem>;
    filters: { search: string; visibility: string | null };
    canUpload: boolean;
    canPublish: boolean;
    maxUploadKb: number;
    extensions: string[];
}>();
defineOptions({
    layout: { breadcrumbs: [{ title: 'Médiathèque', href: index() }] },
});
const search = ref(props.filters.search);
const uploadInput = ref<HTMLInputElement | null>(null);
const upload = useForm<{
    file: File | null;
    title: string;
    alt_text: string;
    visibility: string;
}>({ file: null, title: '', alt_text: '', visibility: 'private' });
const { isDragging, selectFiles, dragEnter, dragLeave, dragOver, drop } =
    useFileDropzone({
        disabled: () => upload.processing,
        extensions: () => props.extensions,
        maxSizeKb: () => props.maxUploadKb,
        onSelect: (file) => {
            upload.file = file;
            upload.clearErrors('file');
        },
        onError: (message) => upload.setError('file', message),
    });
const editForm = useForm({ title: '', alt_text: '', visibility: 'private' });
const editing = ref<MediaItem | null>(null);
const editOpen = ref(false);
const deleting = ref<MediaItem | null>(null);
const deleteOpen = ref(false);
const deletingFile = ref(false);
const deleteError = ref('');
function uploadFile(): void {
    if (upload.processing || !upload.file || upload.errors.file) {
        return;
    }

    upload.post(store.url(), {
        preserveScroll: true,
        onSuccess: () => {
            upload.reset();

            if (uploadInput.value) {
                uploadInput.value.value = '';
            }
        },
    });
}
function chooseFile(event: Event): void {
    const input = event.target as HTMLInputElement;
    selectFiles(Array.from(input.files ?? []));
    input.value = '';
}
function clearFile(): void {
    upload.file = null;
    upload.clearErrors('file');
}
function edit(item: MediaItem): void {
    editing.value = item;
    editForm.title = item.title;
    editForm.alt_text = item.alt_text ?? '';
    editForm.visibility = item.visibility;
    editForm.clearErrors();
    editOpen.value = true;
}
function save(): void {
    if (editing.value) {
        editForm.put(update.url(editing.value.id), {
            preserveScroll: true,
            onSuccess: () => {
                editOpen.value = false;
            },
        });
    }
}
function remove(): void {
    if (!deleting.value || deletingFile.value) {
        return;
    }

    deletingFile.value = true;
    deleteError.value = '';
    router.delete(destroy.url(deleting.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            deleteOpen.value = false;
        },
        onError: (errors) => {
            deleteError.value = String(
                errors.media ?? 'Impossible de supprimer le fichier.',
            );
            deleteOpen.value = false;
        },
        onFinish: () => {
            deletingFile.value = false;
        },
    });
}
function filter(): void {
    router.get(
        index.url({ query: { search: search.value } }),
        {},
        { preserveState: true },
    );
}
function size(bytes: number): string {
    return bytes >= 1048576
        ? `${(bytes / 1048576).toFixed(1)} Mo`
        : `${Math.ceil(bytes / 1024)} Ko`;
}
</script>

<template>
    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 md:p-8">
        <PageHeader
            title="Médiathèque"
            description="Importez, organisez et réutilisez vos images et documents."
        />
        <Card v-if="canUpload">
            <CardHeader
                ><CardTitle>Importer un fichier</CardTitle
                ><CardDescription
                    >{{ extensions.join(', ').toUpperCase() }} ·
                    {{ Math.round(maxUploadKb / 1024) }} Mo maximum. Les
                    fichiers sont privés par défaut.</CardDescription
                ></CardHeader
            >
            <CardContent>
                <form
                    class="grid gap-4 md:grid-cols-2"
                    @submit.prevent="uploadFile"
                >
                    <div class="space-y-3 md:col-span-2">
                        <div
                            class="focus-within:ring-ring relative flex flex-col items-center gap-3 rounded-xl border-2 border-dashed p-6 text-center transition-colors focus-within:ring-2 focus-within:ring-offset-2 sm:p-10"
                            :class="[
                                isDragging
                                    ? 'border-primary bg-primary/10'
                                    : 'border-border bg-muted/30 hover:bg-muted/60',
                                upload.processing ? 'opacity-60' : '',
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
                                        ? 'Déposez votre fichier ici'
                                        : 'Glissez-déposez votre fichier ici'
                                }}
                            </p>
                            <label
                                for="media-file"
                                class="text-primary text-sm font-medium underline underline-offset-4"
                                >ou cliquez pour choisir un fichier</label
                            >
                            <input
                                id="media-file"
                                ref="uploadInput"
                                type="file"
                                :accept="
                                    extensions
                                        .map((extension) => '.' + extension)
                                        .join(',')
                                "
                                :disabled="upload.processing"
                                aria-describedby="media-file-help media-file-error"
                                :aria-invalid="!!upload.errors.file"
                                class="absolute inset-0 size-full cursor-pointer opacity-0 disabled:cursor-not-allowed"
                                @change="chooseFile"
                            />
                            <p
                                id="media-file-help"
                                class="text-muted-foreground text-xs"
                            >
                                Un fichier à la fois ·
                                {{ size(maxUploadKb * 1024) }} maximum
                            </p>
                        </div>
                        <div
                            v-if="upload.file"
                            class="flex items-center justify-between gap-3 rounded-md border p-3"
                        >
                            <p role="status" class="min-w-0 text-sm break-all">
                                {{ upload.file.name }} ·
                                {{ size(upload.file.size) }}
                            </p>
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                :disabled="upload.processing"
                                @click="clearFile"
                                >Retirer</Button
                            >
                        </div>
                        <FieldError id="media-file-error" role="alert">{{
                            upload.errors.file
                        }}</FieldError>
                    </div>
                    <Field
                        ><FieldLabel for="media-title"
                            >Titre (facultatif)</FieldLabel
                        ><Input
                            id="media-title"
                            v-model="upload.title"
                            maxlength="255"
                        /><FieldError>{{
                            upload.errors.title
                        }}</FieldError></Field
                    >
                    <Field
                        ><FieldLabel for="media-alt"
                            >Texte alternatif de l’image</FieldLabel
                        ><Input
                            id="media-alt"
                            v-model="upload.alt_text"
                            maxlength="500"
                        /><FieldError>{{
                            upload.errors.alt_text
                        }}</FieldError></Field
                    >
                    <Field v-if="canPublish"
                        ><FieldLabel for="media-visibility">Accès</FieldLabel
                        ><Select v-model="upload.visibility"
                            ><SelectTrigger id="media-visibility"
                                ><SelectValue /></SelectTrigger
                            ><SelectContent
                                ><SelectItem value="private">Privé</SelectItem
                                ><SelectItem value="public"
                                    >Public</SelectItem
                                ></SelectContent
                            ></Select
                        ><FieldError>{{ upload.errors.visibility }}</FieldError>
                        <p class="text-muted-foreground text-xs">
                            Public : accessible à toute personne disposant du
                            lien.
                        </p></Field
                    >
                    <div class="flex items-center gap-4 md:col-span-2">
                        <Button
                            type="submit"
                            :disabled="
                                upload.processing ||
                                !upload.file ||
                                !!upload.errors.file
                            "
                            >{{
                                upload.processing ? 'Importation…' : 'Importer'
                            }}</Button
                        ><progress
                            v-if="upload.progress"
                            :value="upload.progress.percentage"
                            max="100"
                            aria-label="Progression de l’importation"
                        />
                    </div>
                </form>
            </CardContent>
        </Card>
        <form class="flex items-end gap-3" @submit.prevent="filter">
            <Field class="max-w-sm flex-1"
                ><FieldLabel for="media-search"
                    >Rechercher un fichier</FieldLabel
                ><Input
                    id="media-search"
                    v-model="search"
                    maxlength="100" /></Field
            ><Button type="submit" variant="outline">Rechercher</Button>
        </form>
        <p v-if="deleteError" role="alert" class="text-destructive text-sm">
            {{ deleteError }}
        </p>
        <div
            v-if="media.data.length"
            class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
        >
            <Card
                v-for="item in media.data"
                :key="item.id"
                class="min-w-0 overflow-hidden"
            >
                <CardContent class="flex flex-col gap-3 pt-6">
                    <img
                        v-if="item.thumbnail_url"
                        :src="item.thumbnail_url"
                        :alt="item.alt_text ?? ''"
                        loading="lazy"
                        class="bg-muted h-36 w-full rounded-md object-contain"
                    />
                    <div
                        v-else
                        class="bg-muted flex h-36 items-center justify-center rounded-md"
                    >
                        <FileText class="text-muted-foreground size-12" />
                    </div>
                    <div class="min-w-0">
                        <h2 class="truncate font-medium" :title="item.title">
                            {{ item.title }}
                        </h2>
                        <p class="text-muted-foreground truncate text-xs">
                            {{ item.original_name }} · {{ size(item.size) }}
                        </p>
                    </div>
                    <Badge
                        :variant="
                            item.visibility === 'public'
                                ? 'default'
                                : 'secondary'
                        "
                        >{{
                            item.visibility === 'public' ? 'Public' : 'Privé'
                        }}</Badge
                    >
                    <div class="flex flex-wrap gap-2">
                        <Button size="sm" variant="outline" as-child
                            ><a :href="item.download_url"
                                >Télécharger</a
                            ></Button
                        ><Button
                            v-if="item.can.update"
                            size="sm"
                            variant="ghost"
                            @click="edit(item)"
                            >Modifier</Button
                        ><Button
                            v-if="item.can.delete"
                            size="sm"
                            variant="ghost"
                            @click="
                                deleting = item;
                                deleteOpen = true;
                            "
                            >Supprimer</Button
                        >
                    </div>
                </CardContent>
            </Card>
        </div>
        <div
            v-else
            class="text-muted-foreground rounded-lg border border-dashed p-12 text-center"
        >
            {{
                filters.search
                    ? 'Aucun fichier ne correspond à cette recherche.'
                    : 'Votre médiathèque est vide.'
            }}
        </div>
        <nav
            aria-label="Pagination"
            class="flex items-center justify-between gap-3"
        >
            <Button v-if="media.current_page > 1" as-child variant="outline"
                ><Link
                    :href="
                        index({
                            query: {
                                search: filters.search,
                                page: media.current_page - 1,
                            },
                        })
                    "
                    >Précédent</Link
                ></Button
            ><span class="text-muted-foreground text-sm"
                >{{ media.total }} fichiers · Page {{ media.current_page }} /
                {{ media.last_page }}</span
            ><Button
                v-if="media.current_page < media.last_page"
                as-child
                variant="outline"
                ><Link
                    :href="
                        index({
                            query: {
                                search: filters.search,
                                page: media.current_page + 1,
                            },
                        })
                    "
                    >Suivant</Link
                ></Button
            >
        </nav>
        <Dialog v-model:open="editOpen"
            ><DialogContent
                ><DialogHeader
                    ><DialogTitle>Modifier le fichier</DialogTitle
                    ><DialogDescription
                        >Gérez son libellé, son accessibilité et son
                        accès.</DialogDescription
                    ></DialogHeader
                >
                <form class="space-y-4" @submit.prevent="save">
                    <Field
                        ><FieldLabel for="edit-media-title">Titre</FieldLabel
                        ><Input
                            id="edit-media-title"
                            v-model="editForm.title"
                            required
                            maxlength="255"
                        /><FieldError>{{
                            editForm.errors.title
                        }}</FieldError></Field
                    >
                    <Field
                        ><FieldLabel for="edit-media-alt"
                            >Texte alternatif</FieldLabel
                        ><Input
                            id="edit-media-alt"
                            v-model="editForm.alt_text"
                            maxlength="500"
                        /><FieldError>{{
                            editForm.errors.alt_text
                        }}</FieldError></Field
                    >
                    <Field
                        ><FieldLabel for="edit-media-visibility"
                            >Accès</FieldLabel
                        ><Select v-model="editForm.visibility"
                            ><SelectTrigger id="edit-media-visibility"
                                ><SelectValue /></SelectTrigger
                            ><SelectContent
                                ><SelectItem value="private">Privé</SelectItem
                                ><SelectItem
                                    v-if="
                                        canPublish ||
                                        editing?.visibility === 'public'
                                    "
                                    value="public"
                                    >Public</SelectItem
                                ></SelectContent
                            ></Select
                        ><FieldError>{{
                            editForm.errors.visibility
                        }}</FieldError></Field
                    >
                    <Button type="submit" :disabled="editForm.processing"
                        >Enregistrer</Button
                    >
                </form></DialogContent
            ></Dialog
        >
        <ConfirmationAction
            v-model:open="deleteOpen"
            :title="'Supprimer « ' + (deleting?.title ?? '') + ' » ?'"
            description="Le fichier sera supprimé définitivement. Les fichiers encore joints à un contenu doivent d’abord être détachés."
            confirm-label="Supprimer"
            :processing="deletingFile"
            @confirm="remove"
        />
    </div>
</template>
