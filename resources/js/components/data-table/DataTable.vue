<script setup lang="ts" generic="TData extends { id: string | number }">
import { FlexRender, useTable } from '@tanstack/vue-table';
import type {
    ColumnDef,
    PaginationState,
    SortingState,
} from '@tanstack/vue-table';
import { computed, watch } from 'vue';
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
        selectable?: boolean;
        selectedIds?: string[];
        pageSizeOptions?: readonly number[];
        itemLabel?: string;
        itemsLabel?: string;
        emptyLabel?: string;
        emptyMessage?: string;
    }>(),
    {
        processing: false,
        selectable: false,
        selectedIds: () => [],
        pageSizeOptions: () => [10, 25, 50],
        itemLabel: 'élément',
        itemsLabel: 'éléments',
        emptyLabel: 'Aucun élément trouvé',
        emptyMessage: 'Aucun résultat ne correspond à vos filtres.',
    },
);

const emit = defineEmits<{
    'update:selectedIds': [ids: string[]];
    'update:pagination': [pagination: PaginationState];
    'update:sorting': [sorting: SortingState];
}>();

const allSelected = computed(
    () =>
        props.data.length > 0 &&
        props.data.every((row) => props.selectedIds.includes(String(row.id))),
);
function toggleRow(id: string): void {
    emit(
        'update:selectedIds',
        props.selectedIds.includes(id)
            ? props.selectedIds.filter((value) => value !== id)
            : [...props.selectedIds, id],
    );
}
watch(
    () => props.data,
    () => emit('update:selectedIds', []),
);

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

        <div
            v-if="selectable && selectedIds.length"
            class="flex flex-wrap items-center gap-3 rounded-md border bg-muted/30 p-3"
        >
            <span class="text-sm"
                >{{ selectedIds.length }} sélectionné(s) sur cette page</span
            >
            <slot name="bulk-actions" />
        </div>
        <div class="rounded-md border">
            <Table>
                <TableHeader>
                    <TableRow
                        v-for="headerGroup in table.getHeaderGroups()"
                        :key="headerGroup.id"
                    >
                        <TableHead v-if="selectable" class="w-10">
                            <input
                                type="checkbox"
                                aria-label="Sélectionner toutes les lignes de cette page"
                                class="size-4 accent-primary"
                                :checked="allSelected"
                                :indeterminate="
                                    selectedIds.length > 0 && !allSelected
                                "
                                :disabled="processing || data.length === 0"
                                @change="
                                    emit(
                                        'update:selectedIds',
                                        allSelected
                                            ? []
                                            : data.map((row) => String(row.id)),
                                    )
                                "
                            />
                        </TableHead>
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
                            <TableCell v-if="selectable">
                                <input
                                    type="checkbox"
                                    :aria-label="`Sélectionner la ligne ${row.id}`"
                                    class="size-4 accent-primary"
                                    :checked="selectedIds.includes(row.id)"
                                    :disabled="processing"
                                    @change="toggleRow(row.id)"
                                />
                            </TableCell>
                            <TableCell
                                v-for="cell in row.getAllCells()"
                                :key="cell.id"
                            >
                                <FlexRender :cell="cell" />
                            </TableCell>
                        </TableRow>
                    </template>
                    <TableEmpty
                        v-else
                        :colspan="columns.length + (selectable ? 1 : 0)"
                    >
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
