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
                    :aria-label="'Actions pour ' + page.title"
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
                            Consulter
                        </Link>
                    </DropdownMenuItem>
                    <DropdownMenuItem :as-child="true">
                        <Link :href="PageController.edit(page.id)">
                            <Edit3 />
                            Modifier
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
                        Supprimer
                    </DropdownMenuItem>
                </DropdownMenuGroup>
            </DropdownMenuContent>
        </DropdownMenu>

        <ConfirmationAction
            v-model:open="deleteOpen"
            :title="'Supprimer « ' + page.title + ' » ?'"
            description="Cette page sera supprimée définitivement. Cette action est irréversible."
            confirm-label="Supprimer la page"
            pending-label="Suppression…"
            :processing="processing"
            @confirm="deletePage"
        />
    </div>
</template>
