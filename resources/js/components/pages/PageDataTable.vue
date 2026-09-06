<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { FileText, Plus, Search, X } from '@lucide/vue';
import { watchDebounced } from '@vueuse/core';
import { computed, ref, watch } from 'vue';
import BulkPageController from '@/actions/App/Http/Controllers/BulkPageController';
import PageController from '@/actions/App/Http/Controllers/PageController';
import { pages as exportTable } from '@/actions/App/Http/Controllers/TableExportController';
import { ConfirmationAction } from '@/components/application';
import { EmptyState, ResourceTable } from '@/components/application';
import TableViewTools from '@/components/data-table/TableViewTools.vue';
import { pageColumns } from '@/components/pages/pageColumns';
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
    PageIndexFilters,
    PagePagination,
    PageSort,
    PageSortDirection,
    PageStatus,
    PageSummary,
} from '@/types';

type PageIndexQuery = {
    search?: string;
    status?: PageStatus;
    sort: PageSort;
    direction: PageSortDirection;
    per_page: number;
    page: number;
};

const props = defineProps<{
    pages: PagePagination;
    filters: PageIndexFilters;
    canBulkUpdate: boolean;
}>();

const selectedIds = ref<string[]>([]);
const bulk = useForm<{ ids: string[]; action: 'publish' | 'unpublish' }>({
    ids: [],
    action: 'publish',
});
const confirmBulk = ref(false);
function requestBulk(action: 'publish' | 'unpublish'): void {
    bulk.action = action;
    bulk.clearErrors();
    confirmBulk.value = true;
}
function submitBulk(): void {
    bulk.ids = [...selectedIds.value];
    bulk.patch(BulkPageController.url(), {
        preserveScroll: true,
        onSuccess: () => {
            selectedIds.value = [];
            confirmBulk.value = false;
        },
        onError: () => {
            confirmBulk.value = false;
        },
    });
}

const search = ref(props.filters.search ?? '');
const status = ref<PageStatus | 'all'>(props.filters.status ?? 'all');

const {
    pagination,
    processing,
    reset,
    sorting,
    updatePagination,
    updateSorting,
    visit,
} = useServerDataTable<PageSummary, PageSort, PageIndexQuery>({
    pagination: () => props.pages,
    sorting: () => props.filters,
    query: () => ({
        search: search.value.trim() || undefined,
        status: status.value === 'all' ? undefined : status.value,
        sort: props.filters.sort,
        direction: props.filters.direction,
        per_page: props.filters.per_page,
        page: props.pages.current_page,
    }),
    url: (query) => PageController.index.url({ query }),
    resetUrl: () => PageController.index.url(),
    only: ['pages', 'filters'],
});

const hasCustomView = computed(
    () =>
        search.value.trim() !== '' ||
        status.value !== 'all' ||
        props.filters.sort !== 'updated_at' ||
        props.filters.direction !== 'desc' ||
        props.filters.per_page !== 10,
);
const shouldShowTable = computed(
    () =>
        props.pages.total > 0 ||
        props.filters.search !== null ||
        props.filters.status !== null,
);

function updateStatus(value: unknown): void {
    if (value !== 'all' && value !== 'draft' && value !== 'published') {
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
    () => props.filters.status,
    (value) => {
        status.value = value ?? 'all';
    },
);
</script>

<template>
    <ResourceTable
        title="Toutes les pages"
        description="Recherchez, filtrez, triez et gérez toutes les pages."
        :columns="pageColumns"
        :data="pages.data"
        :pagination="pagination"
        :sorting="sorting"
        :row-count="pages.total"
        :processing="processing || bulk.processing"
        :selectable="canBulkUpdate"
        v-model:selected-ids="selectedIds"
        item-label="page"
        items-label="pages"
        empty-label="Aucune page trouvée"
        empty-message="Aucune page ne correspond à vos filtres."
        :show-table="shouldShowTable"
        @update:pagination="updatePagination"
        @update:sorting="updateSorting"
    >
        <template #bulk-actions>
            <Button
                type="button"
                size="sm"
                :disabled="processing || bulk.processing"
                @click="requestBulk('publish')"
                >Publier</Button
            >
            <Button
                type="button"
                size="sm"
                variant="outline"
                :disabled="processing || bulk.processing"
                @click="requestBulk('unpublish')"
                >Passer en brouillon</Button
            >
            <Button
                type="button"
                size="sm"
                variant="ghost"
                :disabled="processing || bulk.processing"
                @click="selectedIds = []"
                >Désélectionner</Button
            >
        </template>
        <template #toolbar>
            <p
                v-if="bulk.errors.ids"
                role="alert"
                class="text-sm text-destructive"
            >
                {{ bulk.errors.ids }}
            </p>
            <ConfirmationAction
                v-model:open="confirmBulk"
                :title="
                    bulk.action === 'publish'
                        ? 'Publier les pages sélectionnées ?'
                        : 'Passer les pages en brouillon ?'
                "
                :description="`${selectedIds.length} page(s) seront modifiées. ${bulk.action === 'publish' ? 'Les pages pourront être consultées sur le site public.' : 'Les pages ne seront plus accessibles sur le site public.'}`"
                confirm-label="Confirmer"
                :processing="bulk.processing"
                @confirm="submitBulk"
            />
            <TableViewTools
                table-key="pages"
                :filters="filters"
                :url="(query) => PageController.index.url({ query })"
                :export-url="exportTable.url({ query: filters })"
                :total="pages.total"
                :disabled="processing"
            />
            <FieldGroup
                class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_11rem_auto] sm:items-end"
            >
                <Field class="w-full sm:max-w-sm">
                    <FieldLabel for="page-search" class="sr-only">
                        Rechercher des pages
                    </FieldLabel>
                    <div class="relative">
                        <Search
                            class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <Input
                            id="page-search"
                            v-model="search"
                            class="pl-9"
                            placeholder="Rechercher des pages…"
                            autocomplete="off"
                            maxlength="100"
                        />
                    </div>
                </Field>

                <Field>
                    <FieldLabel for="page-status" class="sr-only">
                        Filtrer par statut
                    </FieldLabel>
                    <Select
                        :model-value="status"
                        @update:model-value="updateStatus"
                    >
                        <SelectTrigger id="page-status" class="w-full">
                            <SelectValue placeholder="Tous les statuts" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectGroup>
                                <SelectItem value="all">
                                    Tous les statuts
                                </SelectItem>
                                <SelectItem value="published">
                                    Publiées
                                </SelectItem>
                                <SelectItem value="draft"
                                    >Brouillons</SelectItem
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
                title="Aucune page"
                description="Créez votre première page pour commencer votre bibliothèque de contenu."
                :icon="FileText"
            >
                <template #actions>
                    <Button as-child>
                        <Link :href="PageController.create()">
                            <Plus data-icon="inline-start" />
                            Créer une page
                        </Link>
                    </Button>
                </template>
            </EmptyState>
        </template>
    </ResourceTable>
</template>
