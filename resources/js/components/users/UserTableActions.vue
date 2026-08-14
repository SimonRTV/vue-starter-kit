<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import {
    Ban,
    Edit3,
    Eye,
    MoreHorizontal,
    Trash2,
    UserCheck,
} from '@lucide/vue';
import { ref } from 'vue';
import UserController from '@/actions/App/Http/Controllers/UserController';
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
import type { ManagedUserSummary } from '@/types';

const props = defineProps<{
    user: ManagedUserSummary;
}>();

const deleteOpen = ref(false);
const disableOpen = ref(false);
const processing = ref<'delete' | 'disable' | 'enable' | null>(null);

function deleteUser(): void {
    router.delete(UserController.destroy(props.user.id), {
        preserveScroll: true,
        onStart: () => {
            processing.value = 'delete';
        },
        onSuccess: () => {
            deleteOpen.value = false;
        },
        onFinish: () => {
            processing.value = null;
        },
    });
}

function disableUser(): void {
    router.post(
        UserController.disable(props.user.id),
        {},
        {
            preserveScroll: true,
            onStart: () => {
                processing.value = 'disable';
            },
            onSuccess: () => {
                disableOpen.value = false;
            },
            onFinish: () => {
                processing.value = null;
            },
        },
    );
}

function enableUser(): void {
    router.delete(UserController.enable(props.user.id), {
        preserveScroll: true,
        onStart: () => {
            processing.value = 'enable';
        },
        onFinish: () => {
            processing.value = null;
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
                    :aria-label="'Actions pour ' + user.name"
                >
                    <MoreHorizontal />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                <DropdownMenuLabel>Actions</DropdownMenuLabel>
                <DropdownMenuGroup>
                    <DropdownMenuItem :as-child="true">
                        <Link :href="UserController.show(user.id)">
                            <Eye />
                            Consulter
                        </Link>
                    </DropdownMenuItem>
                    <DropdownMenuItem v-if="user.can.update" :as-child="true">
                        <Link :href="UserController.edit(user.id)">
                            <Edit3 />
                            Modifier
                        </Link>
                    </DropdownMenuItem>
                </DropdownMenuGroup>
                <template
                    v-if="
                        user.can.disable || user.can.enable || user.can.delete
                    "
                >
                    <DropdownMenuSeparator />
                    <DropdownMenuGroup>
                        <DropdownMenuItem
                            v-if="user.can.disable"
                            @click="disableOpen = true"
                        >
                            <Ban />
                            Désactiver
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            v-if="user.can.enable"
                            :disabled="processing !== null"
                            @click="enableUser"
                        >
                            <UserCheck />
                            Réactiver
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            v-if="user.can.delete"
                            variant="destructive"
                            @click="deleteOpen = true"
                        >
                            <Trash2 />
                            Supprimer définitivement
                        </DropdownMenuItem>
                    </DropdownMenuGroup>
                </template>
            </DropdownMenuContent>
        </DropdownMenu>

        <ConfirmationAction
            v-model:open="deleteOpen"
            :title="'Supprimer définitivement « ' + user.name + ' » ?'"
            description="Ce compte sera supprimé définitivement. Cette action est irréversible. Envisagez plutôt de le désactiver si son historique doit être conservé."
            confirm-label="Supprimer définitivement"
            pending-label="Suppression…"
            :processing="processing === 'delete'"
            @confirm="deleteUser"
        />

        <ConfirmationAction
            v-model:open="disableOpen"
            :title="'Désactiver « ' + user.name + ' » ?'"
            description="L’utilisateur sera déconnecté de tous ses appareils et ne pourra plus se connecter tant que le compte n’aura pas été réactivé."
            confirm-label="Désactiver le compte"
            pending-label="Désactivation…"
            :processing="processing === 'disable'"
            @confirm="disableUser"
        />
    </div>
</template>
