<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import RoleController from '@/actions/App/Http/Controllers/RoleController';
import { ConfirmationAction } from '@/components/application';
import { Button } from '@/components/ui/button';
import type { ManagedRoleSummary } from '@/types';

const props = defineProps<{
    role: ManagedRoleSummary;
}>();

const isOpen = ref(false);
const processing = ref(false);

function deleteRole(): void {
    router.delete(RoleController.destroy(props.role.id), {
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
        :title="'Supprimer « ' + role.name + ' » ?'"
        description="Ce rôle et ses autorisations seront supprimés définitivement."
        confirm-label="Supprimer le rôle"
        pending-label="Suppression…"
        :processing="processing"
        @confirm="deleteRole"
    >
        <template #trigger>
            <Button variant="destructive">
                <Trash2 data-icon="inline-start" />
                Supprimer le rôle
            </Button>
        </template>
    </ConfirmationAction>
</template>
