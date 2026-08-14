<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { UserPlus, Users, X } from '@lucide/vue';
import { watchDebounced } from '@vueuse/core';
import { computed, ref, watch } from 'vue';
import UserController from '@/actions/App/Http/Controllers/UserController';
import { EmptyState, ResourceTable } from '@/components/application';
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
import { userColumns } from '@/components/users/userColumns';
import { useServerDataTable } from '@/composables/useServerDataTable';
import { formatRoleName } from '@/lib/roleNames';
import type {
    ManagedUserPagination,
    ManagedUserSummary,
    UserIndexFilters,
    UserAccountStatus,
    UserRoleOption,
    UserSort,
    UserSortDirection,
    UserVerificationStatus,
} from '@/types';

type UserIndexQuery = {
    search?: string;
    role?: string;
    verification?: UserVerificationStatus;
    status?: UserAccountStatus;
    sort: UserSort;
    direction: UserSortDirection;
    per_page: number;
    page: number;
};

const props = defineProps<{
    users: ManagedUserPagination;
    filters: UserIndexFilters;
    roles: UserRoleOption[];
    canCreate: boolean;
}>();

const search = ref(props.filters.search ?? '');
const role = ref(props.filters.role ?? 'all');
const verification = ref<UserVerificationStatus | 'all'>(
    props.filters.verification ?? 'all',
);
const status = ref<UserAccountStatus | 'all'>(props.filters.status ?? 'all');

const {
    pagination,
    processing,
    reset,
    sorting,
    updatePagination,
    updateSorting,
    visit,
} = useServerDataTable<ManagedUserSummary, UserSort, UserIndexQuery>({
    pagination: () => props.users,
    sorting: () => props.filters,
    query: () => ({
        search: search.value.trim() || undefined,
        role: role.value === 'all' ? undefined : role.value,
        verification:
            verification.value === 'all' ? undefined : verification.value,
        status: status.value === 'all' ? undefined : status.value,
        sort: props.filters.sort,
        direction: props.filters.direction,
        per_page: props.filters.per_page,
        page: props.users.current_page,
    }),
    url: (query) => UserController.index.url({ query }),
    resetUrl: () => UserController.index.url(),
    only: ['users', 'filters', 'roles', 'abilities'],
});

const hasCustomView = computed(
    () =>
        search.value.trim() !== '' ||
        role.value !== 'all' ||
        verification.value !== 'all' ||
        status.value !== 'all' ||
        props.filters.sort !== 'created_at' ||
        props.filters.direction !== 'desc' ||
        props.filters.per_page !== 10,
);
const shouldShowTable = computed(
    () =>
        props.users.total > 0 ||
        props.filters.search !== null ||
        props.filters.role !== null ||
        props.filters.verification !== null ||
        props.filters.status !== null,
);
function updateRole(value: unknown): void {
    if (typeof value !== 'string') {
        return;
    }

    if (
        value !== 'all' &&
        !props.roles.some((roleOption) => roleOption.name === value)
    ) {
        return;
    }

    role.value = value;
    visit({
        role: value === 'all' ? undefined : value,
        page: 1,
    });
}

function updateVerification(value: unknown): void {
    if (value !== 'all' && value !== 'verified' && value !== 'unverified') {
        return;
    }

    verification.value = value;
    visit({
        verification: value === 'all' ? undefined : value,
        page: 1,
    });
}

function updateStatus(value: unknown): void {
    if (value !== 'all' && value !== 'active' && value !== 'disabled') {
        return;
    }

    status.value = value;
    visit({
        status: value === 'all' ? undefined : value,
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
    () => props.filters.role,
    (value) => {
        role.value = value ?? 'all';
    },
);

watch(
    () => props.filters.verification,
    (value) => {
        verification.value = value ?? 'all';
    },
);

watch(
    () => props.filters.status,
    (value) => {
        status.value = value ?? 'all';
    },
);
</script>

<template>
    <ResourceTable
        title="Tous les utilisateurs"
        description="Recherchez, filtrez, triez et gérez tous les comptes."
        :columns="userColumns"
        :data="users.data"
        :pagination="pagination"
        :sorting="sorting"
        :row-count="users.total"
        :processing="processing"
        item-label="utilisateur"
        items-label="utilisateurs"
        empty-label="Aucun utilisateur trouvé"
        empty-message="Aucun utilisateur ne correspond à vos filtres."
        :show-table="shouldShowTable"
        @update:pagination="updatePagination"
        @update:sorting="updateSorting"
    >
        <template #toolbar>
            <FieldGroup
                class="grid gap-3 sm:grid-cols-2 lg:grid-cols-[minmax(0,1fr)_11rem_11rem_11rem_auto] lg:items-end"
            >
                <Field class="w-full">
                    <FieldLabel for="user-search" class="sr-only">
                        Rechercher des utilisateurs
                    </FieldLabel>
                    <Input
                        id="user-search"
                        v-model="search"
                        placeholder="Rechercher par nom ou e-mail…"
                        autocomplete="off"
                        maxlength="100"
                    />
                </Field>

                <Field>
                    <FieldLabel for="user-role" class="sr-only">
                        Filtrer par rôle
                    </FieldLabel>
                    <Select
                        :model-value="role"
                        @update:model-value="updateRole"
                    >
                        <SelectTrigger id="user-role" class="w-full">
                            <SelectValue placeholder="Tous les rôles" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectGroup>
                                <SelectItem value="all"
                                    >Tous les rôles</SelectItem
                                >
                                <SelectItem
                                    v-for="roleOption in roles"
                                    :key="roleOption.id"
                                    :value="roleOption.name"
                                >
                                    {{ formatRoleName(roleOption.name) }}
                                </SelectItem>
                            </SelectGroup>
                        </SelectContent>
                    </Select>
                </Field>

                <Field>
                    <FieldLabel for="user-status" class="sr-only">
                        Filtrer par statut du compte
                    </FieldLabel>
                    <Select
                        :model-value="status"
                        @update:model-value="updateStatus"
                    >
                        <SelectTrigger id="user-status" class="w-full">
                            <SelectValue placeholder="Tous les statuts" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectGroup>
                                <SelectItem value="all"
                                    >Tous les statuts</SelectItem
                                >
                                <SelectItem value="active">Actifs</SelectItem>
                                <SelectItem value="disabled">
                                    Désactivés
                                </SelectItem>
                            </SelectGroup>
                        </SelectContent>
                    </Select>
                </Field>

                <Field>
                    <FieldLabel for="user-verification" class="sr-only">
                        Filtrer par vérification
                    </FieldLabel>
                    <Select
                        :model-value="verification"
                        @update:model-value="updateVerification"
                    >
                        <SelectTrigger id="user-verification" class="w-full">
                            <SelectValue
                                placeholder="Toutes les vérifications"
                            />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectGroup>
                                <SelectItem value="all">
                                    Toutes les vérifications
                                </SelectItem>
                                <SelectItem value="verified">
                                    Vérifiés
                                </SelectItem>
                                <SelectItem value="unverified">
                                    Non vérifiés
                                </SelectItem>
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
                title="Aucun utilisateur"
                description="Créez le premier compte géré et attribuez-lui ses accès."
                :icon="Users"
            >
                <template v-if="canCreate" #actions>
                    <Button as-child>
                        <Link :href="UserController.create()">
                            <UserPlus data-icon="inline-start" />
                            Créer un utilisateur
                        </Link>
                    </Button>
                </template>
            </EmptyState>
        </template>
    </ResourceTable>
</template>
