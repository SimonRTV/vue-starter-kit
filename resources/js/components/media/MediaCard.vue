<script setup lang="ts">
import {
    Folder,
    Globe,
    Link2,
    Lock,
    TriangleAlert,
} from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import {
    fileExtension,
    fileIcon,
    formatFileSize,
    formatMediaDate,
    isMissingAltText,
} from '@/lib/media';
import type { MediaItem } from '@/types/media';

defineProps<{
    item: MediaItem;
    selectable: boolean;
    selected: boolean;
    disabled?: boolean;
    draggable?: boolean;
    folderPath?: string | null;
}>();
const emit = defineEmits<{
    open: [];
    toggle: [];
    dragstart: [event: DragEvent];
}>();
</script>

<template>
    <article
        class="group bg-card relative flex min-w-0 flex-col overflow-hidden rounded-xl border transition-shadow focus-within:shadow-md hover:shadow-md"
        :class="[
            selected ? 'ring-primary ring-2' : '',
            draggable ? 'cursor-grab active:cursor-grabbing' : '',
        ]"
        :draggable="draggable"
        @dragstart="emit('dragstart', $event)"
    >
        <label
            v-if="selectable"
            class="bg-background/90 absolute top-2 left-2 z-10 flex size-7 items-center justify-center rounded-md border shadow-sm transition-opacity group-focus-within:opacity-100 group-hover:opacity-100"
            :class="selected ? 'opacity-100' : 'opacity-0 max-sm:opacity-100'"
        >
            <span class="sr-only">Sélectionner {{ item.title }}</span>
            <input
                type="checkbox"
                class="accent-primary size-4"
                :checked="selected"
                :disabled="disabled"
                @change="emit('toggle')"
            />
        </label>
        <button
            type="button"
            class="bg-muted focus-visible:ring-ring relative flex aspect-[4/3] items-center justify-center overflow-hidden focus-visible:ring-2 focus-visible:outline-none focus-visible:ring-inset"
            :aria-label="`Afficher les détails de ${item.title}`"
            @click="emit('open')"
        >
            <img
                v-if="item.thumbnail_url"
                :src="item.thumbnail_url"
                :alt="item.alt_text ?? ''"
                loading="lazy"
                draggable="false"
                class="size-full object-contain transition-transform group-hover:scale-[1.02]"
            />
            <span v-else class="flex flex-col items-center gap-2">
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
            <span class="absolute top-2 right-2 flex gap-1">
                <Badge
                    v-if="item.visibility === 'public'"
                    class="gap-1"
                    title="Accessible à toute personne disposant du lien"
                    ><Globe class="size-3" aria-hidden="true" />Public</Badge
                >
                <Badge v-else variant="secondary" class="gap-1"
                    ><Lock class="size-3" aria-hidden="true" />Privé</Badge
                >
            </span>
        </button>
        <div class="flex min-w-0 flex-1 flex-col gap-2 p-3">
            <div class="min-w-0">
                <h3 class="truncate text-sm font-medium" :title="item.title">
                    {{ item.title }}
                </h3>
                <p class="text-muted-foreground truncate text-xs">
                    {{ fileExtension(item) }} ·
                    {{ formatFileSize(item.size) }} ·
                    {{ formatMediaDate(item.created_at) }}
                </p>
                <p
                    v-if="folderPath"
                    class="text-muted-foreground mt-1 flex min-w-0 items-center gap-1 text-xs"
                    :title="folderPath"
                >
                    <Folder class="size-3 shrink-0" aria-hidden="true" />
                    <span class="truncate">{{ folderPath }}</span>
                </p>
            </div>
            <div
                v-if="
                    item.tags.length ||
                    isMissingAltText(item) ||
                    item.usages.length
                "
                class="flex flex-wrap items-center gap-1"
            >
                <Badge
                    v-if="isMissingAltText(item)"
                    variant="outline"
                    class="gap-1 border-amber-500/50 text-amber-700 dark:text-amber-400"
                    ><TriangleAlert class="size-3" aria-hidden="true" />Texte
                    alternatif manquant</Badge
                >
                <Badge
                    v-if="item.usages.length"
                    variant="outline"
                    class="gap-1"
                    :title="item.usages.map((usage) => usage.title).join(', ')"
                    ><Link2 class="size-3" aria-hidden="true" />{{
                        item.usages.length === 1
                            ? '1 contenu'
                            : `${item.usages.length} contenus`
                    }}</Badge
                >
                <Badge
                    v-for="tag in item.tags"
                    :key="tag"
                    variant="secondary"
                    class="font-normal"
                    >{{ tag }}</Badge
                >
            </div>
        </div>
    </article>
</template>
