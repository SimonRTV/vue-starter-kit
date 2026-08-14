<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ShieldPlus } from '@lucide/vue';
import RoleController from '@/actions/App/Http/Controllers/RoleController';
import { PageHeader } from '@/components/application';
import RoleDataTable from '@/components/roles/RoleDataTable.vue';
import { Button } from '@/components/ui/button';
import type {
    ManagedRolePagination,
    RoleIndexAbilities,
    RoleIndexFilters,
} from '@/types';

defineProps<{
    roles: ManagedRolePagination;
    filters: RoleIndexFilters;
    abilities: RoleIndexAbilities;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Rôles',
                href: RoleController.index(),
            },
        ],
    },
});
</script>

<template>
    <div class="flex flex-1 flex-col">
        <Head title="Rôles" />

        <main
            class="mx-auto flex w-full max-w-[1600px] flex-1 flex-col gap-6 p-4 md:p-6 lg:p-8"
        >
            <PageHeader
                title="Rôles"
                description="Créez des profils d’accès et contrôlez les autorisations qu’ils accordent."
            >
                <template v-if="abilities.create" #actions>
                    <Button as-child>
                        <Link :href="RoleController.create()">
                            <ShieldPlus data-icon="inline-start" />
                            Nouveau rôle
                        </Link>
                    </Button>
                </template>
            </PageHeader>

            <RoleDataTable
                :roles="roles"
                :filters="filters"
                :can-create="abilities.create"
            />
        </main>
    </div>
</template>
