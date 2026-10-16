<script setup lang="ts">
import { FolderInput, Globe, Lock, Trash2 } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import type { MediaBulkAction } from '@/types/media';

defineProps<{ canPublish: boolean; disabled: boolean }>();
const emit = defineEmits<{ action: [action: MediaBulkAction]; clear: [] }>();
</script>

<template>
    <div class="flex flex-wrap items-center gap-2">
        <Button
            type="button"
            size="sm"
            variant="outline"
            :disabled="disabled"
            @click="emit('action', 'move')"
            ><FolderInput data-icon="inline-start" />Déplacer</Button
        >
        <Button
            v-if="canPublish"
            type="button"
            size="sm"
            variant="outline"
            :disabled="disabled"
            @click="emit('action', 'public')"
            ><Globe data-icon="inline-start" />Rendre public</Button
        >
        <Button
            type="button"
            size="sm"
            variant="outline"
            :disabled="disabled"
            @click="emit('action', 'private')"
            ><Lock data-icon="inline-start" />Rendre privé</Button
        >
        <Button
            type="button"
            size="sm"
            variant="destructive"
            :disabled="disabled"
            @click="emit('action', 'delete')"
            ><Trash2 data-icon="inline-start" />Supprimer</Button
        >
        <Button
            type="button"
            size="sm"
            variant="ghost"
            :disabled="disabled"
            @click="emit('clear')"
            >Désélectionner</Button
        >
    </div>
</template>
