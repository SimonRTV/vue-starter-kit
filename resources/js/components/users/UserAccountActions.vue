<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import {
    Ban,
    Edit3,
    KeyRound,
    MoreHorizontal,
    RotateCcwKey,
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
import { Spinner } from '@/components/ui/spinner';
import type { ManagedUserSummary } from '@/types';

const props = defineProps<{
    user: ManagedUserSummary;
}>();

const disableOpen = ref(false);
const securityResetOpen = ref(false);
const deleteOpen = ref(false);
const processing = ref<
    'delete' | 'disable' | 'enable' | 'password' | 'security' | null
>(null);

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

function sendPasswordReset(): void {
    router.post(
        UserController.sendPasswordReset(props.user.id),
        {},
        {
            preserveScroll: true,
            onStart: () => {
                processing.value = 'password';
            },
            onFinish: () => {
                processing.value = null;
            },
        },
    );
}

function resetSecurity(): void {
    router.post(
        UserController.resetSecurity(props.user.id),
        {},
        {
            preserveScroll: true,
            onStart: () => {
                processing.value = 'security';
            },
            onSuccess: () => {
                securityResetOpen.value = false;
            },
            onFinish: () => {
                processing.value = null;
            },
        },
    );
}
</script>

<template>
    <div>
        <DropdownMenu>
            <DropdownMenuTrigger as-child>
                <Button
                    variant="outline"
                    :disabled="processing !== null"
                    :aria-label="'Actions pour ' + user.name"
                >
                    <Spinner
                        v-if="processing !== null"
                        data-icon="inline-start"
                    />
                    <MoreHorizontal v-else data-icon="inline-start" />
                    {{ processing !== null ? 'Traitement…' : 'Actions' }}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" class="w-56">
                <DropdownMenuLabel>Gérer l’utilisateur</DropdownMenuLabel>

                <DropdownMenuGroup v-if="user.can.update">
                    <DropdownMenuItem :as-child="true">
                        <Link :href="UserController.edit(user.id)">
                            <Edit3 />
                            Modifier l’utilisateur
                        </Link>
                    </DropdownMenuItem>
                </DropdownMenuGroup>

                <DropdownMenuSeparator
                    v-if="
                        user.can.update &&
                        (user.can.disable ||
                            user.can.enable ||
                            user.can.reset_password ||
                            user.can.reset_security)
                    "
                />

                <DropdownMenuGroup
                    v-if="
                        user.can.disable ||
                        user.can.enable ||
                        user.can.reset_password ||
                        user.can.reset_security
                    "
                >
                    <DropdownMenuItem
                        v-if="user.can.disable"
                        @click="disableOpen = true"
                    >
                        <Ban />
                        Désactiver le compte
                    </DropdownMenuItem>

                    <DropdownMenuItem
                        v-if="user.can.enable"
                        @click="enableUser"
                    >
                        <UserCheck />
                        Réactiver le compte
                    </DropdownMenuItem>

                    <DropdownMenuItem
                        v-if="user.can.reset_password"
                        @click="sendPasswordReset"
                    >
                        <KeyRound />
                        Envoyer un lien de réinitialisation
                    </DropdownMenuItem>

                    <DropdownMenuItem
                        v-if="user.can.reset_security"
                        @click="securityResetOpen = true"
                    >
                        <RotateCcwKey />
                        Réinitialiser la sécurité
                    </DropdownMenuItem>
                </DropdownMenuGroup>

                <template v-if="user.can.delete">
                    <DropdownMenuSeparator />
                    <DropdownMenuGroup>
                        <DropdownMenuItem
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
            v-model:open="disableOpen"
            :title="'Désactiver « ' + user.name + ' » ?'"
            description="L’utilisateur sera déconnecté de tous ses appareils et ne pourra plus se connecter tant que le compte n’aura pas été réactivé."
            confirm-label="Désactiver le compte"
            pending-label="Désactivation…"
            :processing="processing === 'disable'"
            @confirm="disableUser"
        />

        <ConfirmationAction
            v-model:open="securityResetOpen"
            :title="'Réinitialiser la sécurité de « ' + user.name + ' » ?'"
            description="L’authentification à deux facteurs et les clés d’accès seront supprimées, l’utilisateur sera déconnecté de tous ses appareils et recevra un lien de réinitialisation du mot de passe."
            confirm-label="Réinitialiser la sécurité"
            pending-label="Réinitialisation…"
            :processing="processing === 'security'"
            @confirm="resetSecurity"
        />

        <ConfirmationAction
            v-model:open="deleteOpen"
            :title="'Supprimer définitivement « ' + user.name + ' » ?'"
            description="Ce compte sera supprimé définitivement. Cette action est irréversible. Envisagez plutôt de le désactiver si son historique doit être conservé."
            confirm-label="Supprimer définitivement"
            pending-label="Suppression…"
            :processing="processing === 'delete'"
            @confirm="deleteUser"
        />
    </div>
</template>
