<script setup lang="ts">
import { X } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';

const props = withDefaults(
    defineProps<{
        id: string;
        suggestions?: string[];
        max?: number;
        disabled?: boolean;
        invalid?: boolean;
        describedBy?: string;
    }>(),
    { suggestions: () => [], max: 10, disabled: false, invalid: false },
);
const tags = defineModel<string[]>({ required: true });
const draft = ref('');
const listId = computed(() => `${props.id}-suggestions`);
const availableSuggestions = computed(() =>
    props.suggestions.filter(
        (suggestion) =>
            !tags.value.some(
                (tag) => tag.toLowerCase() === suggestion.toLowerCase(),
            ),
    ),
);
const isFull = computed(() => tags.value.length >= props.max);

function commit(): void {
    const additions = draft.value
        .split(',')
        .map((tag) => tag.replace(/\s+/g, ' ').trim())
        .filter(Boolean);
    const next = [...tags.value];

    for (const tag of additions) {
        if (next.length >= props.max) {
            break;
        }

        if (!next.some((value) => value.toLowerCase() === tag.toLowerCase())) {
            next.push(tag);
        }
    }

    tags.value = next;
    draft.value = '';
}

function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Enter' || event.key === ',') {
        event.preventDefault();
        commit();
    } else if (
        event.key === 'Backspace' &&
        draft.value === '' &&
        tags.value.length
    ) {
        tags.value = tags.value.slice(0, -1);
    }
}

function remove(tag: string): void {
    tags.value = tags.value.filter((value) => value !== tag);
}
</script>

<template>
    <div class="flex flex-col gap-2">
        <div v-if="tags.length" class="flex flex-wrap gap-1.5">
            <Badge
                v-for="tag in tags"
                :key="tag"
                variant="secondary"
                class="gap-1 pr-1"
            >
                {{ tag }}
                <button
                    type="button"
                    class="hover:bg-foreground/10 focus-visible:ring-ring rounded-sm p-0.5 focus-visible:ring-2 focus-visible:outline-none"
                    :aria-label="`Retirer l’étiquette ${tag}`"
                    :disabled="disabled"
                    @click="remove(tag)"
                >
                    <X class="size-3" aria-hidden="true" />
                </button>
            </Badge>
        </div>
        <Input
            :id="id"
            v-model="draft"
            :list="listId"
            :disabled="disabled || isFull"
            :aria-invalid="invalid"
            :aria-describedby="describedBy"
            :placeholder="
                isFull
                    ? `${max} étiquettes maximum`
                    : 'Ajoutez une étiquette puis Entrée'
            "
            maxlength="30"
            autocomplete="off"
            @keydown="onKeydown"
            @blur="commit"
        />
        <datalist :id="listId">
            <option
                v-for="suggestion in availableSuggestions"
                :key="suggestion"
                :value="suggestion"
            />
        </datalist>
    </div>
</template>
