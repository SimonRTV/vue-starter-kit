<script setup lang="ts" generic="TData extends { id: string | number }">
import { FlexRender, useTable } from '@tanstack/vue-table';
import type {
    ColumnDef,
    PaginationState,
    SortingState,
} from '@tanstack/vue-table';
import { computed } from 'vue';
import { dataTableFeatures } from '@/components/data-table/dataTableFeatures';
import type { DataTableFeatures } from '@/components/data-table/dataTableFeatures';
import DataTablePagination from '@/components/data-table/DataTablePagination.vue';
import {
    Table,
    TableBody,
    TableCell,
    TableEmpty,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

type StateUpdater<T> = T | ((previous: T) => T);

const props = withDefaults(
    defineProps<{
        columns: ColumnDef<DataTableFeatures, TData>[];
        data: TData[];
        pagination: PaginationState;
        sorting: SortingState;
        rowCount: number;
        processing?: boolean;
        pageSizeOptions?: readonly number[];
        itemLabel?: string;
        itemsLabel?: string;
        emptyLabel?: string;
        emptyMessage?: string;
    }>(),
    {
        processing: false,
        pageSizeOptions: () => [10, 25, 50],
        itemLabel: 'élément',
        itemsLabel: 'éléments',
        emptyLabel: 'Aucun élément trouvé',
        emptyMessage: 'Aucun résultat ne correspond à vos filtres.',
    },
);

const emit = defineEmits<{
    'update:pagination': [pagination: PaginationState];
    'update:sorting': [sorting: SortingState];
}>();

const rows = computed(() => props.data);
const columns = computed(() => props.columns);
const tableState = computed(() => ({
    pagination: props.pagination,
    sorting: props.sorting,
}));
const rowCount = computed(() => props.rowCount);

function resolveUpdater<T>(updater: StateUpdater<T>, previous: T): T {
    return typeof updater === 'function'
        ? (updater as (value: T) => T)(previous)
        : updater;
}

const table = useTable({
    features: dataTableFeatures,
    columns,
    data: rows,
    state: tableState,
    rowCount,
    manualPagination: true,
    manualSorting: true,
    autoResetPageIndex: false,
    enableMultiSort: false,
    enableSortingRemoval: false,
    getRowId: (row: TData) => String(row.id),
    onPaginationChange: (updater) => {
        emit('update:pagination', resolveUpdater(updater, props.pagination));
    },
    onSortingChange: (updater) => {
        emit('update:sorting', resolveUpdater(updater, props.sorting));
    },
});
</script>

<template>
    <div class="flex flex-col gap-4">
        <slot name="toolbar" />

        <div class="rounded-md border">
            <Table>
                <TableHeader>
                    <TableRow
                        v-for="headerGroup in table.getHeaderGroups()"
                        :key="headerGroup.id"
                    >
                        <TableHead
                            v-for="header in headerGroup.headers"
                            :key="header.id"
                            :aria-sort="
                                header.column.getCanSort()
                                    ? header.column.getIsSorted() === 'asc'
                                        ? 'ascending'
                                        : header.column.getIsSorted() === 'desc'
                                          ? 'descending'
                                          : 'none'
                                    : undefined
                            "
                        >
                            <FlexRender
                                v-if="!header.isPlaceholder"
                                :header="header"
                            />
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <template v-if="table.getRowModel().rows.length">
                        <TableRow
                            v-for="row in table.getRowModel().rows"
                            :key="row.id"
                        >
                            <TableCell
                                v-for="cell in row.getAllCells()"
                                :key="cell.id"
                            >
                                <FlexRender :cell="cell" />
                            </TableCell>
                        </TableRow>
                    </template>
                    <TableEmpty v-else :colspan="columns.length">
                        <slot name="empty">{{ emptyMessage }}</slot>
                    </TableEmpty>
                </TableBody>
            </Table>
        </div>

        <DataTablePagination
            :pagination="pagination"
            :row-count="rowCount"
            :processing="processing"
            :page-size-options="pageSizeOptions"
            :item-label="itemLabel"
            :items-label="itemsLabel"
            :empty-label="emptyLabel"
            @update:pagination="emit('update:pagination', $event)"
        />
    </div>
</template>
