<script setup lang="ts">
import { computed } from 'vue';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { MediaFolder } from '@/types/media';

const props = withDefaults(
    defineProps<{
        id: string;
        folders: MediaFolder[];
        noneLabel?: string;
        excludeIds?: number[];
        maxDepth?: number;
        disabled?: boolean;
    }>(),
    {
        noneLabel: 'Non classé',
        excludeIds: () => [],
        maxDepth: Number.POSITIVE_INFINITY,
        disabled: false,
    },
);
const folderId = defineModel<number | null>({ required: true });

const options = computed(() =>
    props.folders.filter(
        (folder) =>
            !props.excludeIds.includes(folder.id) &&
            folder.depth <= props.maxDepth,
    ),
);
const selectedLabel = computed(
    () =>
        props.folders.find((folder) => folder.id === folderId.value)?.path ??
        props.noneLabel,
);
const value = computed({
    get: () => (folderId.value === null ? 'none' : String(folderId.value)),
    set: (next: string) => {
        folderId.value = next === 'none' ? null : Number(next);
    },
});
</script>

<template>
    <Select v-model="value" :disabled="disabled">
        <SelectTrigger :id="id" class="w-full"
            ><SelectValue>{{ selectedLabel }}</SelectValue></SelectTrigger
        >
        <SelectContent>
            <SelectItem value="none">{{ noneLabel }}</SelectItem>
            <SelectItem
                v-for="folder in options"
                :key="folder.id"
                :value="String(folder.id)"
                :text-value="folder.path"
            >
                <span
                    :style="{ paddingLeft: `${(folder.depth - 1) * 0.875}rem` }"
                    >{{ folder.name }}</span
                >
            </SelectItem>
        </SelectContent>
    </Select>
</template>
