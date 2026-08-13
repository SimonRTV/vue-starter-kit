<script setup lang="ts">
import { Search, X } from '@lucide/vue';
import { watchDebounced } from '@vueuse/core';
import { computed, ref, watch } from 'vue';
import PageController from '@/actions/App/Http/Controllers/PageController';
import { DataTable } from '@/components/data-table';
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
}>();

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
    <DataTable
        :columns="pageColumns"
        :data="pages.data"
        :pagination="pagination"
        :sorting="sorting"
        :row-count="pages.total"
        :processing="processing"
        item-label="page"
        items-label="pages"
        empty-label="No pages found"
        empty-message="No pages match your filters."
        @update:pagination="updatePagination"
        @update:sorting="updateSorting"
    >
        <template #toolbar>
            <FieldGroup
                class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_11rem_auto] sm:items-end"
            >
                <Field class="w-full sm:max-w-sm">
                    <FieldLabel for="page-search" class="sr-only">
                        Search pages
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
                            placeholder="Search pages…"
                            autocomplete="off"
                            maxlength="100"
                        />
                    </div>
                </Field>

                <Field>
                    <FieldLabel for="page-status" class="sr-only">
                        Filter by status
                    </FieldLabel>
                    <Select
                        :model-value="status"
                        @update:model-value="updateStatus"
                    >
                        <SelectTrigger id="page-status" class="w-full">
                            <SelectValue placeholder="All statuses" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectGroup>
                                <SelectItem value="all">
                                    All statuses
                                </SelectItem>
                                <SelectItem value="published">
                                    Published
                                </SelectItem>
                                <SelectItem value="draft">Draft</SelectItem>
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
                    Reset
                </Button>
            </FieldGroup>
        </template>
    </DataTable>
</template>
