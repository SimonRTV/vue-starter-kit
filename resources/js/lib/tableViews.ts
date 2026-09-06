export type TableFilters = Record<string, string | number | boolean | null>;
export type SavedTableView = { name: string; filters: TableFilters };

export function parseTableViews(
    raw: string | null,
    allowedKeys: string[],
): SavedTableView[] {
    try {
        const parsed: unknown = JSON.parse(raw ?? '[]');

        if (!Array.isArray(parsed)) {
            return [];
        }

        return parsed.slice(0, 10).flatMap((view): SavedTableView[] => {
            if (
                !view ||
                typeof view !== 'object' ||
                typeof view.name !== 'string' ||
                !view.name.trim() ||
                view.name.length > 60 ||
                !view.filters ||
                typeof view.filters !== 'object' ||
                Array.isArray(view.filters)
            ) {
                return [];
            }

            const filters: TableFilters = {};

            for (const [key, value] of Object.entries(view.filters)) {
                if (
                    allowedKeys.includes(key) &&
                    key !== 'page' &&
                    (value === null ||
                        typeof value === 'boolean' ||
                        (typeof value === 'number' && Number.isFinite(value)) ||
                        (typeof value === 'string' && value.length <= 100))
                ) {
                    filters[key] = value;
                }
            }

            return [{ name: view.name.trim(), filters }];
        });
    } catch {
        return [];
    }
}

export function saveTableView(
    views: SavedTableView[],
    name: string,
    filters: TableFilters,
): SavedTableView[] {
    const trimmedName = name.trim();

    if (!trimmedName || trimmedName.length > 60) {
        throw new Error('Choisissez un nom de 1 à 60 caractères.');
    }

    if (
        views.some(
            (view) =>
                view.name.toLocaleLowerCase() ===
                trimmedName.toLocaleLowerCase(),
        )
    ) {
        throw new Error('Une vue porte déjà ce nom.');
    }

    if (views.length >= 10) {
        throw new Error('Vous pouvez enregistrer jusqu’à 10 vues par tableau.');
    }

    const savedFilters = Object.fromEntries(
        Object.entries(filters).filter(([key]) => key !== 'page'),
    );

    return [...views, { name: trimmedName, filters: savedFilters }];
}
