<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { UserPlus } from '@lucide/vue';
import UserController from '@/actions/App/Http/Controllers/UserController';
import { PageHeader } from '@/components/application';
import { Button } from '@/components/ui/button';
import UserDataTable from '@/components/users/UserDataTable.vue';
import type {
    ManagedUserPagination,
    UserIndexAbilities,
    UserIndexFilters,
    UserRoleOption,
} from '@/types';

defineProps<{
    users: ManagedUserPagination;
    filters: UserIndexFilters;
    roles: UserRoleOption[];
    abilities: UserIndexAbilities;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Utilisateurs',
                href: UserController.index(),
            },
        ],
    },
});
</script>

<template>
    <div class="flex flex-1 flex-col">
        <Head title="Utilisateurs" />

        <main
            class="mx-auto flex w-full max-w-[1600px] flex-1 flex-col gap-6 p-4 md:p-6 lg:p-8"
        >
            <PageHeader
                title="Utilisateurs"
                description="Invitez, suspendez, sécurisez et gérez les accès aux comptes."
            >
                <template v-if="abilities.create" #actions>
                    <Button as-child>
                        <Link :href="UserController.create()">
                            <UserPlus data-icon="inline-start" />
                            Nouvel utilisateur
                        </Link>
                    </Button>
                </template>
            </PageHeader>

            <UserDataTable
                :users="users"
                :filters="filters"
                :roles="roles"
                :can-create="abilities.create"
            />
        </main>
    </div>
</template>
