<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import PageController from '@/actions/App/Http/Controllers/PageController';
import { ConfirmationAction } from '@/components/application';
import { Button } from '@/components/ui/button';
import type { PageSummary } from '@/types';

const props = defineProps<{
    page: PageSummary;
}>();

const isOpen = ref(false);
const processing = ref(false);

function deletePage(): void {
    router.delete(PageController.destroy(props.page.id), {
        preserveScroll: true,
        onStart: () => {
            processing.value = true;
        },
        onSuccess: () => {
            isOpen.value = false;
        },
        onFinish: () => {
            processing.value = false;
        },
    });
}
</script>

<template>
    <ConfirmationAction
        v-model:open="isOpen"
        :title="'Supprimer « ' + page.title + ' » ?'"
        description="Cette page sera supprimée définitivement. Cette action est irréversible."
        confirm-label="Supprimer la page"
        pending-label="Suppression…"
        :processing="processing"
        @confirm="deletePage"
    >
        <template #trigger>
            <Button variant="destructive">
                <Trash2 data-icon="inline-start" />
                Supprimer la page
            </Button>
        </template>
    </ConfirmationAction>
</template>
