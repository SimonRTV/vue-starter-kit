import type { Pagination } from './pagination';

export type PageStatus = 'draft' | 'published';

export type PageStatusFilter = PageStatus | null;

export type PageSort = 'title' | 'is_published' | 'published_at' | 'updated_at';

export type PageSortDirection = 'asc' | 'desc';

export type PageIndexFilters = {
    search: string | null;
    status: PageStatusFilter;
    sort: PageSort;
    direction: PageSortDirection;
    per_page: 10 | 25 | 50;
};

export type PageSummary = {
    id: number;
    title: string;
    slug: string;
    is_published: boolean;
    status: PageStatus;
    published_at: string | null;
    updated_at: string | null;
};

export type PageDetail = PageSummary & {
    excerpt: string | null;
    body: string | null;
    created_at: string | null;
};

export type PublicPage = {
    title: string;
    slug: string;
    excerpt: string | null;
    body: string | null;
    published_at: string;
    updated_at: string | null;
};

export type PagePagination = Pagination<PageSummary>;
