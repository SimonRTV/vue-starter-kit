<script setup lang="ts">
import { Link, router, useForm } from '@inertiajs/vue3';
import { FileText, Globe, Lock } from '@lucide/vue';
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
import { fileExtension, formatFileSize } from '@/lib/media';
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
                class="text-destructive text-sm"
            >
                {{ form.errors.media_id || error }}
            </p>
            <p v-if="!attachments.length" class="text-muted-foreground text-sm">
                Aucun fichier joint.
            </p>
            <ul v-else class="divide-y">
                <li
                    v-for="item in attachments"
                    :key="item.id"
                    class="flex items-center justify-between gap-3 py-3"
                >
                    <a
                        :href="item.download_url"
                        class="group focus-visible:ring-ring flex min-w-0 flex-1 items-center gap-3 rounded-md focus-visible:ring-2 focus-visible:outline-none"
                    >
                        <span
                            class="bg-muted flex aspect-[4/3] w-20 shrink-0 items-center justify-center overflow-hidden rounded-md border"
                        >
                            <img
                                v-if="item.thumbnail_url"
                                :src="item.thumbnail_url"
                                :alt="item.alt_text ?? ''"
                                loading="lazy"
                                class="size-full object-contain"
                            />
                            <span
                                v-else
                                class="flex flex-col items-center gap-1"
                            >
                                <FileText
                                    class="text-muted-foreground size-6"
                                    aria-hidden="true"
                                />
                                <span
                                    class="text-muted-foreground text-[0.625rem] font-semibold tracking-wider"
                                    >{{ fileExtension(item) }}</span
                                >
                            </span>
                        </span>
                        <span class="flex min-w-0 flex-col gap-1">
                            <span
                                class="truncate text-sm font-medium underline-offset-4 group-hover:underline"
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
                                    {{ formatFileSize(item.size) }}</span
                                >
                            </span>
                        </span>
                    </a>
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
            <MediaPicker
                v-model:open="pickerOpen"
                :attached-ids="attachments.map((item) => item.id)"
                @select="attach"
            />
        </CardContent>
    </Card>
</template>
