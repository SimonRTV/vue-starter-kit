import { Link } from '@inertiajs/vue3';
import { createColumnHelper } from '@tanstack/vue-table';
import { h } from 'vue';
import PageController from '@/actions/App/Http/Controllers/PageController';
import { renderDataTableSortHeader } from '@/components/data-table';
import type { DataTableFeatures } from '@/components/data-table';
import PageTableActions from '@/components/pages/PageTableActions.vue';
import { Badge } from '@/components/ui/badge';
import type { PageSummary } from '@/types';

const columnHelper = createColumnHelper<DataTableFeatures, PageSummary>();

function formatDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Intl.DateTimeFormat('fr-CH', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

export const pageColumns = columnHelper.columns([
    columnHelper.accessor((page) => page.title + ' ' + page.slug, {
        id: 'title',
        header: ({ column }) => renderDataTableSortHeader('Page', column),
        cell: ({ row }) =>
            h('div', { class: 'flex min-w-52 flex-col gap-1' }, [
                h(
                    Link,
                    {
                        href: PageController.show(row.original.id),
                        class: 'font-medium hover:underline',
                    },
                    () => row.original.title,
                ),
                h(
                    'span',
                    { class: 'text-xs text-muted-foreground' },
                    '/' + row.original.slug,
                ),
            ]),
    }),
    columnHelper.accessor('is_published', {
        header: ({ column }) => renderDataTableSortHeader('Statut', column),
        cell: ({ row }) =>
            h(
                Badge,
                {
                    variant: row.original.is_published
                        ? 'default'
                        : 'secondary',
                },
                () => (row.original.is_published ? 'Publiée' : 'Brouillon'),
            ),
    }),
    columnHelper.accessor('published_at', {
        header: ({ column }) =>
            renderDataTableSortHeader('Publication', column),
        cell: ({ row }) => formatDate(row.original.published_at),
    }),
    columnHelper.accessor('updated_at', {
        header: ({ column }) =>
            renderDataTableSortHeader('Dernière modification', column),
        cell: ({ row }) => formatDate(row.original.updated_at),
    }),
    columnHelper.display({
        id: 'actions',
        header: () => h('span', { class: 'sr-only' }, 'Actions de la page'),
        cell: ({ row }) => h(PageTableActions, { page: row.original }),
        enableSorting: false,
    }),
]);
