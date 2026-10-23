import { Folder, TriangleAlert } from '@lucide/vue';
import { createColumnHelper } from '@tanstack/vue-table';
import { h } from 'vue';
import { renderDataTableSortHeader } from '@/components/data-table';
import type { DataTableFeatures } from '@/components/data-table';
import { Badge } from '@/components/ui/badge';
import {
    fileExtension,
    fileIcon,
    formatFileSize,
    formatMediaDate,
    isMissingAltText,
} from '@/lib/media';
import type { MediaItem } from '@/types/media';

const columnHelper = createColumnHelper<DataTableFeatures, MediaItem>();

export function mediaColumns(
    open: (item: MediaItem) => void,
    folderPath: (folderId: number | null) => string | null,
) {
    return columnHelper.columns([
        columnHelper.accessor('title', {
            header: ({ column }) =>
                renderDataTableSortHeader('Fichier', column),
            cell: ({ row }) =>
                h('div', { class: 'flex min-w-64 items-center gap-3' }, [
                    row.original.thumbnail_url
                        ? h('img', {
                              src: row.original.thumbnail_url,
                              alt: '',
                              loading: 'lazy',
                              class: 'size-10 shrink-0 rounded bg-muted object-cover',
                          })
                        : h(
                              'span',
                              {
                                  class: 'flex size-10 shrink-0 items-center justify-center rounded bg-muted',
                              },
                              h(fileIcon(row.original), {
                                  class: 'size-5 text-muted-foreground',
                                  'aria-hidden': 'true',
                              }),
                          ),
                    h('div', { class: 'flex min-w-0 flex-col' }, [
                        h(
                            'button',
                            {
                                type: 'button',
                                class: 'truncate text-left font-medium hover:underline focus-visible:underline focus-visible:outline-none',
                                onClick: () => open(row.original),
                            },
                            row.original.title,
                        ),
                        h(
                            'span',
                            { class: 'truncate text-xs text-muted-foreground' },
                            row.original.original_name,
                        ),
                    ]),
                ]),
        }),
        columnHelper.display({
            id: 'type',
            header: () => 'Type',
            cell: ({ row }) =>
                h('div', { class: 'flex items-center gap-1.5' }, [
                    h(
                        Badge,
                        { variant: 'outline' },
                        () => fileExtension(row.original) || '—',
                    ),
                    isMissingAltText(row.original)
                        ? h(TriangleAlert, {
                              class: 'size-4 text-amber-600',
                              'aria-label': 'Texte alternatif manquant',
                              role: 'img',
                          })
                        : null,
                ]),
            enableSorting: false,
        }),
        columnHelper.display({
            id: 'folder',
            header: () => 'Dossier',
            cell: ({ row }) => {
                const path = folderPath(row.original.folder_id);

                return path
                    ? h(
                          'span',
                          {
                              class: 'flex max-w-48 items-center gap-1.5',
                              title: path,
                          },
                          [
                              h(Folder, {
                                  class: 'size-3.5 shrink-0 text-muted-foreground',
                                  'aria-hidden': 'true',
                              }),
                              h('span', { class: 'truncate' }, path),
                          ],
                      )
                    : h(
                          'span',
                          { class: 'text-muted-foreground' },
                          'Non classé',
                      );
            },
            enableSorting: false,
        }),
        columnHelper.display({
            id: 'visibility',
            header: () => 'Accès',
            cell: ({ row }) =>
                h(
                    Badge,
                    {
                        variant:
                            row.original.visibility === 'public'
                                ? 'default'
                                : 'secondary',
                    },
                    () =>
                        row.original.visibility === 'public'
                            ? 'Public'
                            : 'Privé',
                ),
            enableSorting: false,
        }),
        columnHelper.display({
            id: 'tags',
            header: () => 'Étiquettes',
            cell: ({ row }) =>
                row.original.tags.length
                    ? h(
                          'div',
                          { class: 'flex max-w-56 flex-wrap gap-1' },
                          row.original.tags.map((tag) =>
                              h(
                                  Badge,
                                  {
                                      key: tag,
                                      variant: 'secondary',
                                      class: 'font-normal',
                                  },
                                  () => tag,
                              ),
                          ),
                      )
                    : h('span', { class: 'text-muted-foreground' }, '—'),
            enableSorting: false,
        }),
        columnHelper.display({
            id: 'usages',
            header: () => 'Utilisation',
            cell: ({ row }) =>
                row.original.usages.length
                    ? `${row.original.usages.length} contenu${row.original.usages.length > 1 ? 's' : ''}`
                    : h('span', { class: 'text-muted-foreground' }, 'Aucune'),
            enableSorting: false,
        }),
        columnHelper.accessor('size', {
            header: ({ column }) => renderDataTableSortHeader('Taille', column),
            cell: ({ row }) =>
                h(
                    'span',
                    { class: 'tabular-nums' },
                    formatFileSize(row.original.size),
                ),
        }),
        columnHelper.accessor('created_at', {
            header: ({ column }) =>
                renderDataTableSortHeader('Importé le', column),
            cell: ({ row }) =>
                h('div', { class: 'flex flex-col' }, [
                    formatMediaDate(row.original.created_at),
                    row.original.uploader
                        ? h(
                              'span',
                              { class: 'text-xs text-muted-foreground' },
                              row.original.uploader,
                          )
                        : null,
                ]),
        }),
    ]);
}
