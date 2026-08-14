<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import UserController from '@/actions/App/Http/Controllers/UserController';
import { PageHeader } from '@/components/application';
import UserForm from '@/components/users/UserForm.vue';
import type { UserRoleOption } from '@/types';

defineProps<{
    roles: UserRoleOption[];
    canManageVerification: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Utilisateurs',
                href: UserController.index(),
            },
            {
                title: 'Nouvel utilisateur',
                href: UserController.create(),
            },
        ],
    },
});
</script>

<template>
    <div class="flex flex-1 flex-col">
        <Head title="Nouvel utilisateur" />

        <main
            class="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 p-4 md:p-6 lg:p-8"
        >
            <PageHeader
                title="Nouvel utilisateur"
                description="Créez un compte, attribuez ses accès et envoyez une invitation sécurisée."
            />

            <UserForm
                :roles="roles"
                :can-manage-verification="canManageVerification"
            />
        </main>
    </div>
</template>
