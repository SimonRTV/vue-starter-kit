<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import {
    store,
    update,
} from '@/actions/App/Http/Controllers/MediaFolderController';
import MediaFolderSelect from '@/components/media/MediaFolderSelect.vue';
import { Button } from '@/components/ui/button';
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
import type { MediaFolder, MediaFolderDialogMode } from '@/types/media';

const props = defineProps<{
    mode: MediaFolderDialogMode;
    folders: MediaFolder[];
    maxDepth: number;
}>();
const open = defineModel<boolean>('open', { default: false });
const form = useForm<{ name: string; parent_id: number | null }>({
    name: '',
    parent_id: null,
});

const editedFolder = computed(() =>
    props.mode.type === 'edit' ? props.mode.folder : null,
);
const descendantIds = computed(() => {
    const folder = editedFolder.value;

    if (!folder) {
        return [];
    }

    const ids: number[] = [];
    const queue = [folder.id];

    while (queue.length) {
        const parentId = queue.shift();

        for (const child of props.folders.filter(
            (candidate) => candidate.parent_id === parentId,
        )) {
            ids.push(child.id);
            queue.push(child.id);
        }
    }

    return ids;
});
const subtreeHeight = computed(() => {
    const folder = editedFolder.value;

    if (!folder) {
        return 1;
    }

    const deepest = Math.max(
        folder.depth,
        ...props.folders
            .filter((candidate) => descendantIds.value.includes(candidate.id))
            .map((candidate) => candidate.depth),
    );

    return deepest - folder.depth + 1;
});

watch(open, (value) => {
    if (!value) {
        return;
    }

    form.clearErrors();
    form.defaults({
        name: editedFolder.value?.name ?? '',
        parent_id:
            props.mode.type === 'edit'
                ? props.mode.folder.parent_id
                : props.mode.parentId,
    });
    form.reset();
});

function submit(): void {
    const options = {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            open.value = false;
        },
    };

    if (editedFolder.value) {
        form.put(update.url(editedFolder.value.id), options);
    } else {
        form.post(store.url(), options);
    }
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>{{
                    editedFolder ? 'Modifier le dossier' : 'Nouveau dossier'
                }}</DialogTitle>
                <DialogDescription>
                    Les dossiers sont partagés avec toutes les personnes qui ont
                    accès à la médiathèque.
                </DialogDescription>
            </DialogHeader>
            <form
                id="media-folder-form"
                class="flex flex-col gap-4"
                @submit.prevent="submit"
            >
                <Field>
                    <FieldLabel for="media-folder-name">Nom</FieldLabel>
                    <Input
                        id="media-folder-name"
                        v-model="form.name"
                        required
                        maxlength="80"
                        autocomplete="off"
                        :aria-invalid="!!form.errors.name"
                    />
                    <FieldError>{{ form.errors.name }}</FieldError>
                </Field>
                <Field>
                    <FieldLabel for="media-folder-parent"
                        >Emplacement</FieldLabel
                    >
                    <MediaFolderSelect
                        id="media-folder-parent"
                        v-model="form.parent_id"
                        :folders="folders"
                        none-label="Racine de la médiathèque"
                        :exclude-ids="
                            editedFolder
                                ? [editedFolder.id, ...descendantIds]
                                : []
                        "
                        :max-depth="maxDepth - subtreeHeight"
                    />
                    <FieldDescription
                        >{{ maxDepth }} niveaux de dossiers au
                        maximum.</FieldDescription
                    >
                    <FieldError>{{ form.errors.parent_id }}</FieldError>
                </Field>
            </form>
            <DialogFooter>
                <Button
                    type="button"
                    variant="outline"
                    :disabled="form.processing"
                    @click="open = false"
                    >Annuler</Button
                >
                <Button
                    type="submit"
                    form="media-folder-form"
                    :disabled="form.processing || !form.name.trim()"
                    >{{ editedFolder ? 'Enregistrer' : 'Créer' }}</Button
                >
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
