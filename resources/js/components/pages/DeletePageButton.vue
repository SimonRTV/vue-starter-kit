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
        :title="'Delete “' + page.title + '”?'"
        description="This permanently removes the page and cannot be undone."
        confirm-label="Delete page"
        pending-label="Deleting…"
        :processing="processing"
        @confirm="deletePage"
    >
        <template #trigger>
            <Button variant="destructive">
                <Trash2 data-icon="inline-start" />
                Delete page
            </Button>
        </template>
    </ConfirmationAction>
</template>
