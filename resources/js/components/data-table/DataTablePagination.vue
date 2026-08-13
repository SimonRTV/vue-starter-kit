<script setup lang="ts">
import type { PaginationState } from '@tanstack/vue-table';
import { computed, useId } from 'vue';
import { Button } from '@/components/ui/button';
import { Field, FieldLabel } from '@/components/ui/field';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';

const props = withDefaults(
    defineProps<{
        pagination: PaginationState;
        rowCount: number;
        processing?: boolean;
        pageSizeOptions?: readonly number[];
        itemLabel?: string;
        itemsLabel?: string;
        emptyLabel?: string;
    }>(),
    {
        processing: false,
        pageSizeOptions: () => [10, 25, 50],
        itemLabel: 'item',
        itemsLabel: 'items',
        emptyLabel: 'No items found',
    },
);

const emit = defineEmits<{
    'update:pagination': [pagination: PaginationState];
}>();

const pageSizeId = useId();
const pageCount = computed(() =>
    Math.max(1, Math.ceil(props.rowCount / props.pagination.pageSize)),
);
const firstVisibleRow = computed(() =>
    props.rowCount === 0
        ? null
        : props.pagination.pageIndex * props.pagination.pageSize + 1,
);
const lastVisibleRow = computed(() =>
    props.rowCount === 0
        ? null
        : Math.min(
              (props.pagination.pageIndex + 1) * props.pagination.pageSize,
              props.rowCount,
          ),
);
const canGoToPreviousPage = computed(() => props.pagination.pageIndex > 0);
const canGoToNextPage = computed(
    () => props.pagination.pageIndex < pageCount.value - 1,
);

function updatePageSize(value: unknown): void {
    const pageSize = Number(value);

    if (!props.pageSizeOptions.includes(pageSize)) {
        return;
    }

    emit('update:pagination', {
        pageIndex: 0,
        pageSize,
    });
}

function goToPreviousPage(): void {
    if (!canGoToPreviousPage.value) {
        return;
    }

    emit('update:pagination', {
        ...props.pagination,
        pageIndex: props.pagination.pageIndex - 1,
    });
}

function goToNextPage(): void {
    if (!canGoToNextPage.value) {
        return;
    }

    emit('update:pagination', {
        ...props.pagination,
        pageIndex: props.pagination.pageIndex + 1,
    });
}
</script>

<template>
    <div
        class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between"
    >
        <div class="flex items-center gap-3 text-sm text-muted-foreground">
            <span v-if="rowCount">
                Showing {{ firstVisibleRow }}–{{ lastVisibleRow }} of
                {{ rowCount }}
                {{ rowCount === 1 ? itemLabel : itemsLabel }}
            </span>
            <span v-else>{{ emptyLabel }}</span>
            <span
                v-if="processing"
                class="flex items-center gap-2"
                aria-live="polite"
            >
                <Spinner />
                Updating…
            </span>
        </div>

        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between lg:justify-end"
        >
            <Field orientation="horizontal" class="w-auto gap-2">
                <FieldLabel :for="pageSizeId" class="whitespace-nowrap">
                    Rows per page
                </FieldLabel>
                <Select
                    :model-value="String(pagination.pageSize)"
                    @update:model-value="updatePageSize"
                >
                    <SelectTrigger :id="pageSizeId" class="w-20">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectGroup>
                            <SelectItem
                                v-for="pageSize in pageSizeOptions"
                                :key="pageSize"
                                :value="String(pageSize)"
                            >
                                {{ pageSize }}
                            </SelectItem>
                        </SelectGroup>
                    </SelectContent>
                </Select>
            </Field>

            <span class="text-sm text-muted-foreground">
                Page {{ pagination.pageIndex + 1 }} of {{ pageCount }}
            </span>

            <div class="flex gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="processing || !canGoToPreviousPage"
                    @click="goToPreviousPage"
                >
                    Previous
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="processing || !canGoToNextPage"
                    @click="goToNextPage"
                >
                    Next
                </Button>
            </div>
        </div>
    </div>
</template>
