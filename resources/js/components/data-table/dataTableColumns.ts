import { ArrowDown, ArrowUp, ArrowUpDown } from '@lucide/vue';
import { h } from 'vue';
import { Button } from '@/components/ui/button';

type SortableColumn = {
    toggleSorting: (desc: boolean) => void;
    getIsSorted: () => false | 'asc' | 'desc';
};

export function renderDataTableSortHeader(
    label: string,
    column: SortableColumn,
) {
    return h(
        Button,
        {
            variant: 'ghost',
            size: 'sm',
            onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        },
        () => {
            const sorted = column.getIsSorted();
            const icon =
                sorted === 'asc'
                    ? ArrowUp
                    : sorted === 'desc'
                      ? ArrowDown
                      : ArrowUpDown;

            return [
                label,
                h(icon, {
                    'data-icon': 'inline-end',
                    'aria-hidden': 'true',
                }),
            ];
        },
    );
}
