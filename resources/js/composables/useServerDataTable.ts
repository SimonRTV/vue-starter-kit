import { router } from '@inertiajs/vue3';
import type { PaginationState, SortingState } from '@tanstack/vue-table';
import { computed, ref } from 'vue';
import type { Pagination } from '@/types';

type DataTableSortDirection = 'asc' | 'desc';

type ServerDataTableQuery<TSort extends string> = {
    sort: TSort;
    direction: DataTableSortDirection;
    per_page: number;
    page: number;
};

type ServerDataTableSorting<TSort extends string> = {
    sort: TSort;
    direction: DataTableSortDirection;
};

type UseServerDataTableOptions<
    TData,
    TSort extends string,
    TQuery extends ServerDataTableQuery<TSort>,
> = {
    pagination: () => Pagination<TData>;
    sorting: () => ServerDataTableSorting<TSort>;
    query: () => TQuery;
    url: (query: TQuery) => string;
    resetUrl: () => string;
    only: readonly string[];
};

export function useServerDataTable<
    TData,
    TSort extends string,
    TQuery extends ServerDataTableQuery<TSort>,
>(options: UseServerDataTableOptions<TData, TSort, TQuery>) {
    const activeVisits = ref(0);
    const processing = computed(() => activeVisits.value > 0);
    const pagination = computed<PaginationState>(() => ({
        pageIndex: options.pagination().current_page - 1,
        pageSize: options.pagination().per_page,
    }));
    const sorting = computed<SortingState>(() => [
        {
            id: options.sorting().sort,
            desc: options.sorting().direction === 'desc',
        },
    ]);

    function request(url: string, replace: boolean): void {
        router.get(
            url,
            {},
            {
                only: [...options.only],
                preserveScroll: true,
                preserveState: true,
                replace,
                onStart: () => {
                    activeVisits.value += 1;
                },
                onFinish: () => {
                    activeVisits.value = Math.max(0, activeVisits.value - 1);
                },
            },
        );
    }

    function visit(overrides: Partial<TQuery> = {}, replace = true): void {
        const query = {
            ...options.query(),
            ...overrides,
        } as TQuery;

        request(options.url(query), replace);
    }

    function updatePagination(nextPagination: PaginationState): void {
        visit(
            {
                page: nextPagination.pageIndex + 1,
                per_page: nextPagination.pageSize,
            } as Partial<TQuery>,
            false,
        );
    }

    function updateSorting(nextSorting: SortingState): void {
        const [nextSort] = nextSorting;

        if (!nextSort) {
            return;
        }

        visit({
            sort: nextSort.id as TSort,
            direction: nextSort.desc ? 'desc' : 'asc',
            page: 1,
        } as Partial<TQuery>);
    }

    function reset(): void {
        request(options.resetUrl(), true);
    }

    return {
        pagination,
        processing,
        reset,
        sorting,
        updatePagination,
        updateSorting,
        visit,
    };
}
