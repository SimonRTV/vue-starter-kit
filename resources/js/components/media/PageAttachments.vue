<script setup lang="ts">
import { Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { index } from '@/actions/App/Http/Controllers/MediaController';
import {
    destroy,
    store,
} from '@/actions/App/Http/Controllers/PageAttachmentController';
import MediaPicker from '@/components/media/MediaPicker.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { MediaItem } from '@/types/media';
const props = defineProps<{ pageId: number; attachments: MediaItem[] }>();
const pickerOpen = ref(false);
const removing = ref(false);
const error = ref('');
const form = useForm({ media_id: '' });
function attach(item: MediaItem): void {
    if (form.processing) {
        return;
    }

    pickerOpen.value = false;
    form.media_id = item.id;
    form.post(store.url(props.pageId), {
        preserveScroll: true,
        onSuccess: () => {
            pickerOpen.value = false;
        },
    });
}
function detach(item: MediaItem): void {
    error.value = '';
    removing.value = true;
    router.delete(destroy.url({ page: props.pageId, media: item.id }), {
        preserveScroll: true,
        onError: () => {
            error.value = 'Impossible de détacher le fichier.';
        },
        onFinish: () => {
            removing.value = false;
        },
    });
}
</script>

<template>
    <Card>
        <CardHeader
            ><CardTitle>Pièces jointes</CardTitle
            ><CardDescription
                >Seuls les fichiers publics seront affichés sur la page
                publique.</CardDescription
            ></CardHeader
        >
        <CardContent class="space-y-4">
            <div class="flex flex-wrap gap-3">
                <Button
                    type="button"
                    :disabled="form.processing || removing"
                    @click="pickerOpen = true"
                    >Joindre un fichier</Button
                ><Button variant="outline" as-child
                    ><Link :href="index()">Médiathèque</Link></Button
                >
            </div>
            <p
                v-if="form.errors.media_id || error"
                role="alert"
                class="text-sm text-destructive"
            >
                {{ form.errors.media_id || error }}
            </p>
            <p v-if="!attachments.length" class="text-sm text-muted-foreground">
                Aucun fichier joint.
            </p>
            <ul v-else class="divide-y">
                <li
                    v-for="item in attachments"
                    :key="item.id"
                    class="flex items-center justify-between gap-3 py-3"
                >
                    <div class="min-w-0">
                        <a
                            :href="item.download_url"
                            class="block truncate text-sm font-medium underline underline-offset-4"
                            >{{ item.title }}</a
                        ><span class="text-xs text-muted-foreground">{{
                            item.visibility === 'public' ? 'Public' : 'Privé'
                        }}</span>
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        :disabled="form.processing || removing"
                        @click="detach(item)"
                        >Détacher</Button
                    >
                </li>
            </ul>
            <MediaPicker v-model:open="pickerOpen" @select="attach" />
        </CardContent>
    </Card>
</template>
