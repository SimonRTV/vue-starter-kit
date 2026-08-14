import { Link } from '@inertiajs/vue3';
import { createColumnHelper } from '@tanstack/vue-table';
import { h } from 'vue';
import RoleController from '@/actions/App/Http/Controllers/RoleController';
import { renderDataTableSortHeader } from '@/components/data-table';
import type { DataTableFeatures } from '@/components/data-table';
import RoleTableActions from '@/components/roles/RoleTableActions.vue';
import { Badge } from '@/components/ui/badge';
import { formatRoleName } from '@/lib/roleNames';
import type { ManagedRoleSummary } from '@/types';

const columnHelper = createColumnHelper<
    DataTableFeatures,
    ManagedRoleSummary
>();

function formatDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Intl.DateTimeFormat('fr-CH', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

export const roleColumns = columnHelper.columns([
    columnHelper.accessor('name', {
        header: ({ column }) => renderDataTableSortHeader('Rôle', column),
        cell: ({ row }) =>
            h('div', { class: 'flex min-w-48 items-center gap-2' }, [
                h(
                    Link,
                    {
                        href: RoleController.show(row.original.id),
                        class: 'font-medium hover:underline',
                    },
                    () => formatRoleName(row.original.name),
                ),
                row.original.is_protected
                    ? h(Badge, { variant: 'secondary' }, () => 'Protégé')
                    : null,
            ]),
    }),
    columnHelper.accessor('permissions_count', {
        header: ({ column }) =>
            renderDataTableSortHeader('Permissions', column),
        cell: ({ row }) =>
            `${row.original.permissions_count} ${row.original.permissions_count === 1 ? 'autorisation' : 'autorisations'}`,
    }),
    columnHelper.accessor('users_count', {
        header: ({ column }) =>
            renderDataTableSortHeader('Utilisateurs', column),
        cell: ({ row }) =>
            h(
                Badge,
                {
                    variant:
                        row.original.users_count > 0 ? 'default' : 'outline',
                },
                () =>
                    `${row.original.users_count} ${row.original.users_count === 1 ? 'utilisateur' : 'utilisateurs'}`,
            ),
    }),
    columnHelper.accessor('created_at', {
        header: ({ column }) => renderDataTableSortHeader('Création', column),
        cell: ({ row }) => formatDate(row.original.created_at),
    }),
    columnHelper.display({
        id: 'actions',
        header: () => h('span', { class: 'sr-only' }, 'Actions du rôle'),
        cell: ({ row }) => h(RoleTableActions, { role: row.original }),
        enableSorting: false,
    }),
]);
