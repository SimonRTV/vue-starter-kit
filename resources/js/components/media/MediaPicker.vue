<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import { FileText } from '@lucide/vue';
import { onUnmounted, ref, watch } from 'vue';
import { index } from '@/actions/App/Http/Controllers/MediaController';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Skeleton } from '@/components/ui/skeleton';
import type { MediaItem } from '@/types/media';
import type { Pagination } from '@/types/pagination';

const open = defineModel<boolean>('open', { default: false });
const emit = defineEmits<{ select: [media: MediaItem] }>();
const http = useHttp<{ search: string; page: number }, Pagination<MediaItem>>({
    search: '',
    page: 1,
});
const results = ref<Pagination<MediaItem> | null>(null);
const error = ref('');
let requestId = 0;

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
watch(open, (value) => {
    if (value) {
        results.value = null;
        void load();
    } else {
        requestId++;
        http.cancel();
    }
});
onUnmounted(() => http.cancel());
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-h-[85vh] overflow-y-auto sm:max-w-3xl">
            <DialogHeader
                ><DialogTitle>Choisir un fichier</DialogTitle
                ><DialogDescription
                    >Sélectionnez un fichier de votre
                    médiathèque.</DialogDescription
                ></DialogHeader
            >
            <form class="flex items-end gap-3" @submit.prevent="load()">
                <div class="flex-1 space-y-2">
                    <Label for="media-picker-search">Rechercher</Label
                    ><Input
                        id="media-picker-search"
                        v-model="http.search"
                        maxlength="100"
                    />
                </div>
                <Button type="submit" :disabled="http.processing"
                    >Rechercher</Button
                >
            </form>
            <p v-if="error" role="alert" class="text-sm text-destructive">
                {{ error }}
            </p>
            <div
                v-if="http.processing"
                class="grid grid-cols-2 gap-3 sm:grid-cols-3"
                aria-label="Chargement"
            >
                <Skeleton v-for="i in 6" :key="i" class="h-32" />
            </div>
            <div
                v-else-if="results?.data.length"
                class="grid grid-cols-2 gap-3 sm:grid-cols-3"
            >
                <button
                    v-for="item in results.data"
                    :key="item.id"
                    type="button"
                    class="flex flex-col gap-2 rounded-lg border p-3 text-left transition-colors hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring"
                    @click="emit('select', item)"
                >
                    <img
                        v-if="item.thumbnail_url"
                        :src="item.thumbnail_url"
                        :alt="item.alt_text ?? ''"
                        class="h-24 w-full rounded object-contain"
                    />
                    <FileText
                        v-else
                        class="mx-auto h-24 text-muted-foreground"
                    />
                    <span class="w-full truncate text-sm font-medium">{{
                        item.title
                    }}</span>
                    <span class="text-xs text-muted-foreground">{{
                        item.visibility === 'public' ? 'Public' : 'Privé'
                    }}</span>
                </button>
            </div>
            <p
                v-else-if="!error"
                class="py-6 text-center text-sm text-muted-foreground"
            >
                Aucun fichier trouvé. Importez vos fichiers dans la médiathèque.
            </p>
            <div v-if="results" class="flex items-center justify-between gap-3">
                <Button
                    variant="outline"
                    :disabled="http.processing || results.current_page <= 1"
                    @click="load(results.current_page - 1)"
                    >Précédent</Button
                >
                <span class="text-sm"
                    >{{ results.current_page }} / {{ results.last_page }}</span
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
        </DialogContent>
    </Dialog>
</template>
