<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import UserController from '@/actions/App/Http/Controllers/UserController';
import { ConfirmationAction } from '@/components/application';
import { Button } from '@/components/ui/button';
import type { ManagedUserSummary } from '@/types';

const props = defineProps<{
    user: ManagedUserSummary;
}>();

const isOpen = ref(false);
const processing = ref(false);

function deleteUser(): void {
    router.delete(UserController.destroy(props.user.id), {
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
        :title="'Supprimer définitivement « ' + user.name + ' » ?'"
        description="Ce compte sera supprimé définitivement. Cette action est irréversible. Envisagez plutôt de le désactiver si son historique doit être conservé."
        confirm-label="Supprimer définitivement"
        pending-label="Suppression…"
        :processing="processing"
        @confirm="deleteUser"
    >
        <template #trigger>
            <Button variant="destructive">
                <Trash2 data-icon="inline-start" />
                Supprimer définitivement
            </Button>
        </template>
    </ConfirmationAction>
</template>
