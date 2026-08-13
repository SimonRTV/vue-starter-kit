import {
    rowPaginationFeature,
    rowSortingFeature,
    tableFeatures,
} from '@tanstack/vue-table';

export const dataTableFeatures = tableFeatures({
    rowPaginationFeature,
    rowSortingFeature,
});

export type DataTableFeatures = typeof dataTableFeatures;
