<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { Edit3, Eye, MoreHorizontal, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import PageController from '@/actions/App/Http/Controllers/PageController';
import { ConfirmationAction } from '@/components/application';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { PageSummary } from '@/types';

const props = defineProps<{
    page: PageSummary;
}>();

const deleteOpen = ref(false);
const processing = ref(false);

function deletePage(): void {
    router.delete(PageController.destroy(props.page.id), {
        preserveScroll: true,
        onStart: () => {
            processing.value = true;
        },
        onSuccess: () => {
            deleteOpen.value = false;
        },
        onFinish: () => {
            processing.value = false;
        },
    });
}
</script>

<template>
    <div class="flex justify-end">
        <DropdownMenu>
            <DropdownMenuTrigger as-child>
                <Button
                    variant="ghost"
                    size="icon-sm"
                    :aria-label="'Actions for ' + page.title"
                >
                    <MoreHorizontal />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                <DropdownMenuLabel>Actions</DropdownMenuLabel>
                <DropdownMenuGroup>
                    <DropdownMenuItem :as-child="true">
                        <Link :href="PageController.show(page.id)">
                            <Eye />
                            View
                        </Link>
                    </DropdownMenuItem>
                    <DropdownMenuItem :as-child="true">
                        <Link :href="PageController.edit(page.id)">
                            <Edit3 />
                            Edit
                        </Link>
                    </DropdownMenuItem>
                </DropdownMenuGroup>
                <DropdownMenuSeparator />
                <DropdownMenuGroup>
                    <DropdownMenuItem
                        variant="destructive"
                        @click="deleteOpen = true"
                    >
                        <Trash2 />
                        Delete
                    </DropdownMenuItem>
                </DropdownMenuGroup>
            </DropdownMenuContent>
        </DropdownMenu>

        <ConfirmationAction
            v-model:open="deleteOpen"
            :title="'Delete “' + page.title + '”?'"
            description="This permanently removes the page and cannot be undone."
            confirm-label="Delete page"
            pending-label="Deleting…"
            :processing="processing"
            @confirm="deletePage"
        />
    </div>
</template>
