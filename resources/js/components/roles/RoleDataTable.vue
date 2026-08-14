<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ShieldCheck, ShieldPlus, X } from '@lucide/vue';
import { watchDebounced } from '@vueuse/core';
import { computed, ref, watch } from 'vue';
import RoleController from '@/actions/App/Http/Controllers/RoleController';
import { EmptyState, ResourceTable } from '@/components/application';
import { roleColumns } from '@/components/roles/roleColumns';
import { Button } from '@/components/ui/button';
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useServerDataTable } from '@/composables/useServerDataTable';
import type {
    ManagedRolePagination,
    ManagedRoleSummary,
    RoleAssignmentStatus,
    RoleIndexFilters,
    RoleSort,
    RoleSortDirection,
} from '@/types';

type RoleIndexQuery = {
    search?: string;
    assignment?: RoleAssignmentStatus;
    sort: RoleSort;
    direction: RoleSortDirection;
    per_page: number;
    page: number;
};

const props = defineProps<{
    roles: ManagedRolePagination;
    filters: RoleIndexFilters;
    canCreate: boolean;
}>();

const search = ref(props.filters.search ?? '');
const assignment = ref<RoleAssignmentStatus | 'all'>(
    props.filters.assignment ?? 'all',
);

const {
    pagination,
    processing,
    reset,
    sorting,
    updatePagination,
    updateSorting,
    visit,
} = useServerDataTable<ManagedRoleSummary, RoleSort, RoleIndexQuery>({
    pagination: () => props.roles,
    sorting: () => props.filters,
    query: () => ({
        search: search.value.trim() || undefined,
        assignment: assignment.value === 'all' ? undefined : assignment.value,
        sort: props.filters.sort,
        direction: props.filters.direction,
        per_page: props.filters.per_page,
        page: props.roles.current_page,
    }),
    url: (query) => RoleController.index.url({ query }),
    resetUrl: () => RoleController.index.url(),
    only: ['roles', 'filters', 'abilities'],
});

const hasCustomView = computed(
    () =>
        search.value.trim() !== '' ||
        assignment.value !== 'all' ||
        props.filters.sort !== 'created_at' ||
        props.filters.direction !== 'desc' ||
        props.filters.per_page !== 10,
);
const shouldShowTable = computed(
    () =>
        props.roles.total > 0 ||
        props.filters.search !== null ||
        props.filters.assignment !== null,
);

function updateAssignment(value: unknown): void {
    if (value !== 'all' && value !== 'assigned' && value !== 'unused') {
        return;
    }

    assignment.value = value;
    visit({
        assignment: value === 'all' ? undefined : value,
        page: 1,
    });
}

watchDebounced(
    search,
    (value) => {
        const normalizedSearch = value.trim();

        if (normalizedSearch === (props.filters.search ?? '')) {
            return;
        }

        visit({
            search: normalizedSearch || undefined,
            page: 1,
        });
    },
    { debounce: 300, maxWait: 1000 },
);

watch(
    () => props.filters.search,
    (value) => {
        const nextSearch = value ?? '';

        if (search.value !== nextSearch) {
            search.value = nextSearch;
        }
    },
);

watch(
    () => props.filters.assignment,
    (value) => {
        assignment.value = value ?? 'all';
    },
);
</script>

<template>
    <ResourceTable
        title="Tous les rôles"
        description="Recherchez, filtrez, triez et gérez les profils d’accès."
        :columns="roleColumns"
        :data="roles.data"
        :pagination="pagination"
        :sorting="sorting"
        :row-count="roles.total"
        :processing="processing"
        item-label="rôle"
        items-label="rôles"
        empty-label="Aucun rôle trouvé"
        empty-message="Aucun rôle ne correspond à vos filtres."
        :show-table="shouldShowTable"
        @update:pagination="updatePagination"
        @update:sorting="updateSorting"
    >
        <template #toolbar>
            <FieldGroup
                class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_12rem_auto] sm:items-end"
            >
                <Field class="w-full">
                    <FieldLabel for="role-search" class="sr-only">
                        Rechercher des rôles
                    </FieldLabel>
                    <Input
                        id="role-search"
                        v-model="search"
                        placeholder="Rechercher par nom…"
                        autocomplete="off"
                        maxlength="100"
                    />
                </Field>

                <Field>
                    <FieldLabel for="role-assignment" class="sr-only">
                        Filtrer par attribution
                    </FieldLabel>
                    <Select
                        :model-value="assignment"
                        @update:model-value="updateAssignment"
                    >
                        <SelectTrigger id="role-assignment" class="w-full">
                            <SelectValue
                                placeholder="Toutes les attributions"
                            />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectGroup>
                                <SelectItem value="all">
                                    Toutes les attributions
                                </SelectItem>
                                <SelectItem value="assigned">
                                    Attribués
                                </SelectItem>
                                <SelectItem value="unused"
                                    >Inutilisés</SelectItem
                                >
                            </SelectGroup>
                        </SelectContent>
                    </Select>
                </Field>

                <Button
                    v-if="hasCustomView"
                    variant="outline"
                    :disabled="processing"
                    @click="reset"
                >
                    <X data-icon="inline-start" />
                    Réinitialiser
                </Button>
            </FieldGroup>
        </template>

        <template #empty>
            <EmptyState
                title="Aucun rôle"
                description="Créez le premier profil d’accès et choisissez ses autorisations."
                :icon="ShieldCheck"
            >
                <template v-if="canCreate" #actions>
                    <Button as-child>
                        <Link :href="RoleController.create()">
                            <ShieldPlus data-icon="inline-start" />
                            Créer un rôle
                        </Link>
                    </Button>
                </template>
            </EmptyState>
        </template>
    </ResourceTable>
</template>
