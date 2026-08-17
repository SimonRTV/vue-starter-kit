<script setup lang="ts">
import RoleController from '@/actions/App/Http/Controllers/RoleController';
import { PageHeader } from '@/components/application';
import RoleForm from '@/components/roles/RoleForm.vue';
import type { RolePermissionOption } from '@/types';

defineProps<{
    permissions: RolePermissionOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Rôles',
                href: RoleController.index(),
            },
            {
                title: 'Nouveau rôle',
                href: RoleController.create(),
            },
        ],
    },
});
</script>

<template>
    <div class="flex flex-1 flex-col">
        <main
            class="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 p-4 md:p-6 lg:p-8"
        >
            <PageHeader
                title="Nouveau rôle"
                description="Créez un profil d’accès et attribuez-lui des autorisations."
            />

            <RoleForm :permissions="permissions" />
        </main>
    </div>
</template>
