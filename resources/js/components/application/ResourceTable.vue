<script setup lang="ts" generic="TData extends { id: string | number }">
import type {
    ColumnDef,
    PaginationState,
    SortingState,
} from '@tanstack/vue-table';
import { DataTable } from '@/components/data-table';
import type { DataTableFeatures } from '@/components/data-table';
import {
    Card,
    CardAction,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

withDefaults(
    defineProps<{
        title: string;
        description?: string;
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
        showTable?: boolean;
    }>(),
    {
        processing: false,
        pageSizeOptions: () => [10, 25, 50],
        itemLabel: 'item',
        itemsLabel: 'items',
        emptyLabel: 'No items found',
        emptyMessage: 'No results match your filters.',
        showTable: true,
    },
);

const emit = defineEmits<{
    'update:pagination': [pagination: PaginationState];
    'update:sorting': [sorting: SortingState];
}>();
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>{{ title }}</CardTitle>
            <CardDescription v-if="description">
                {{ description }}
            </CardDescription>
            <CardAction v-if="$slots.actions">
                <slot name="actions" />
            </CardAction>
        </CardHeader>
        <CardContent>
            <DataTable
                v-if="showTable"
                :columns="columns"
                :data="data"
                :pagination="pagination"
                :sorting="sorting"
                :row-count="rowCount"
                :processing="processing"
                :page-size-options="pageSizeOptions"
                :item-label="itemLabel"
                :items-label="itemsLabel"
                :empty-label="emptyLabel"
                :empty-message="emptyMessage"
                @update:pagination="emit('update:pagination', $event)"
                @update:sorting="emit('update:sorting', $event)"
            >
                <template v-if="$slots.toolbar" #toolbar>
                    <slot name="toolbar" />
                </template>
                <template #empty>
                    <slot name="filteredEmpty">
                        {{ emptyMessage }}
                    </slot>
                </template>
            </DataTable>
            <slot v-else name="empty" />
        </CardContent>
        <CardFooter v-if="$slots.footer">
            <slot name="footer" />
        </CardFooter>
    </Card>
</template>
