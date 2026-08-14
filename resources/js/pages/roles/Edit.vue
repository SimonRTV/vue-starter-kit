<script setup lang="ts">
import { Head, setLayoutProps } from '@inertiajs/vue3';
import RoleController from '@/actions/App/Http/Controllers/RoleController';
import { PageHeader } from '@/components/application';
import RoleForm from '@/components/roles/RoleForm.vue';
import { formatRoleName } from '@/lib/roleNames';
import type { ManagedRoleDetail, RolePermissionOption } from '@/types';

const props = defineProps<{
    role: ManagedRoleDetail;
    permissions: RolePermissionOption[];
}>();

const roleName = formatRoleName(props.role.name);

setLayoutProps({
    breadcrumbs: [
        {
            title: 'Rôles',
            href: RoleController.index(),
        },
        {
            title: roleName,
            href: RoleController.show(props.role.id),
        },
        {
            title: 'Modifier',
            href: RoleController.edit(props.role.id),
        },
    ],
});
</script>

<template>
    <div class="flex flex-1 flex-col">
        <Head :title="'Modifier ' + roleName" />

        <main
            class="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 p-4 md:p-6 lg:p-8"
        >
            <PageHeader
                :title="'Modifier ' + roleName"
                description="Modifiez le nom du rôle ou les autorisations qu’il accorde."
            />

            <RoleForm :role="role" :permissions="permissions" />
        </main>
    </div>
</template>
