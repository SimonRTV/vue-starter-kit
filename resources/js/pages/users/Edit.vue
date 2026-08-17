<script setup lang="ts">
import { setLayoutProps } from '@inertiajs/vue3';
import UserController from '@/actions/App/Http/Controllers/UserController';
import { PageHeader } from '@/components/application';
import UserForm from '@/components/users/UserForm.vue';
import type { ManagedUserDetail, UserRoleOption } from '@/types';

const props = defineProps<{
    user: ManagedUserDetail;
    roles: UserRoleOption[];
    canManageVerification: boolean;
}>();

setLayoutProps({
    breadcrumbs: [
        {
            title: 'Utilisateurs',
            href: UserController.index(),
        },
        {
            title: props.user.name,
            href: UserController.show(props.user.id),
        },
        {
            title: 'Modifier',
            href: UserController.edit(props.user.id),
        },
    ],
});
</script>

<template>
    <div class="flex flex-1 flex-col">
        <main
            class="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 p-4 md:p-6 lg:p-8"
        >
            <PageHeader
                :title="'Modifier ' + user.name"
                description="Modifiez les informations du compte, sa vérification ou ses rôles d’accès."
            />

            <UserForm
                :user="user"
                :roles="roles"
                :can-manage-verification="canManageVerification"
            />
        </main>
    </div>
</template>
