export type TemplateField = {
    key: string;
    label: string;
    type:
        | 'text'
        | 'textarea'
        | 'number'
        | 'boolean'
        | 'select'
        | 'image'
        | 'collection';
    required: boolean;
    options?: string[];
};
export type PageFieldSet = {
    id: number;
    name: string;
    key: string;
    fields: TemplateField[];
    templates_count?: number;
};
export type PageTemplate = {
    id: number;
    name: string;
    renderer: string;
    field_sets: PageFieldSet[];
    pages_count?: number;
};
export type TemplateQuery = {
    source: string;
    limit: number;
    order_by: string;
    direction: string;
};
export type TemplateValue = string | number | boolean | null | TemplateQuery;
export type TemplateValues = Record<string, Record<string, TemplateValue>>;
export type TemplateSource = {
    key: string;
    label: string;
    orders: Record<string, string>;
};
export type TemplateItem = {
    title: string;
    url: string;
    image: string | null;
    excerpt: string | null;
};
export type TemplateCollections = Record<
    string,
    Record<string, TemplateItem[]>
>;
