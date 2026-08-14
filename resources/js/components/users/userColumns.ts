import { Link } from '@inertiajs/vue3';
import { createColumnHelper } from '@tanstack/vue-table';
import { h } from 'vue';
import UserController from '@/actions/App/Http/Controllers/UserController';
import { renderDataTableSortHeader } from '@/components/data-table';
import type { DataTableFeatures } from '@/components/data-table';
import { Badge } from '@/components/ui/badge';
import UserTableActions from '@/components/users/UserTableActions.vue';
import { formatRoleName } from '@/lib/roleNames';
import type { ManagedUserSummary } from '@/types';

const columnHelper = createColumnHelper<
    DataTableFeatures,
    ManagedUserSummary
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

export const userColumns = columnHelper.columns([
    columnHelper.accessor((user) => user.name + ' ' + user.email, {
        id: 'name',
        header: ({ column }) =>
            renderDataTableSortHeader('Utilisateur', column),
        cell: ({ row }) =>
            h('div', { class: 'flex min-w-56 flex-col gap-1' }, [
                h(
                    Link,
                    {
                        href: UserController.show(row.original.id),
                        class: 'font-medium hover:underline',
                    },
                    () => row.original.name,
                ),
                h(
                    'span',
                    { class: 'text-xs text-muted-foreground' },
                    row.original.email,
                ),
            ]),
    }),
    columnHelper.accessor('disabled_at', {
        header: ({ column }) => renderDataTableSortHeader('Statut', column),
        cell: ({ row }) =>
            h(
                Badge,
                {
                    variant: row.original.disabled_at
                        ? 'destructive'
                        : 'outline',
                },
                () => (row.original.disabled_at ? 'Désactivé' : 'Actif'),
            ),
    }),
    columnHelper.accessor('email_verified_at', {
        header: ({ column }) =>
            renderDataTableSortHeader('Vérification', column),
        cell: ({ row }) =>
            h(
                Badge,
                {
                    variant: row.original.email_verified_at
                        ? 'default'
                        : 'secondary',
                },
                () =>
                    row.original.email_verified_at
                        ? 'Vérifiée'
                        : 'Non vérifiée',
            ),
    }),
    columnHelper.accessor('roles', {
        header: 'Rôles',
        cell: ({ row }) =>
            row.original.roles.length
                ? h(
                      'div',
                      { class: 'flex min-w-36 flex-wrap gap-1' },
                      row.original.roles.map((role) =>
                          h(Badge, { variant: 'secondary' }, () =>
                              formatRoleName(role),
                          ),
                      ),
                  )
                : h(
                      'span',
                      { class: 'text-sm text-muted-foreground' },
                      'Aucun rôle',
                  ),
        enableSorting: false,
    }),
    columnHelper.accessor('last_login_at', {
        header: ({ column }) =>
            renderDataTableSortHeader('Dernière connexion', column),
        cell: ({ row }) => formatDate(row.original.last_login_at),
    }),
    columnHelper.display({
        id: 'actions',
        header: () =>
            h('span', { class: 'sr-only' }, 'Actions de l’utilisateur'),
        cell: ({ row }) => h(UserTableActions, { user: row.original }),
        enableSorting: false,
    }),
]);
