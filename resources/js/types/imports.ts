export type ImportField = { key: string; label: string; required: boolean };
export type ImportCounts = {
    create: number;
    update: number;
    skip: number;
    error: number;
};
export type ImportBatch = {
    id: string;
    headers: string[];
    samples: string[][];
    total: number;
    mapping: Record<string, number | null>;
    duplicateMode: string;
    expiresAt: string;
    completed: boolean;
    previewToken: string | null;
    preview: null | {
        counts: ImportCounts;
        rows: {
            number: number;
            cells: string[];
            action: string;
            errors: string[];
        }[];
    };
};
