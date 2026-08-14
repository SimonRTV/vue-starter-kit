<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { Edit3, Eye, MoreHorizontal, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import RoleController from '@/actions/App/Http/Controllers/RoleController';
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
import type { ManagedRoleSummary } from '@/types';

const props = defineProps<{
    role: ManagedRoleSummary;
}>();

const deleteOpen = ref(false);
const processing = ref(false);

function deleteRole(): void {
    router.delete(RoleController.destroy(props.role.id), {
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
                    :aria-label="'Actions pour ' + role.name"
                >
                    <MoreHorizontal />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                <DropdownMenuLabel>Actions</DropdownMenuLabel>
                <DropdownMenuGroup>
                    <DropdownMenuItem :as-child="true">
                        <Link :href="RoleController.show(role.id)">
                            <Eye />
                            Consulter
                        </Link>
                    </DropdownMenuItem>
                    <DropdownMenuItem v-if="role.can.update" :as-child="true">
                        <Link :href="RoleController.edit(role.id)">
                            <Edit3 />
                            Modifier
                        </Link>
                    </DropdownMenuItem>
                </DropdownMenuGroup>
                <template v-if="role.can.delete">
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
                </template>
            </DropdownMenuContent>
        </DropdownMenu>

        <ConfirmationAction
            v-model:open="deleteOpen"
            :title="'Supprimer « ' + role.name + ' » ?'"
            description="Ce rôle et ses autorisations seront supprimés définitivement."
            confirm-label="Supprimer le rôle"
            pending-label="Suppression…"
            :processing="processing"
            @confirm="deleteRole"
        />
    </div>
</template>
