import type { Pagination } from '@/types/pagination';

export type MediaUsage = {
    id: number;
    title: string;
    url: string | null;
    trashed: boolean;
};

export type MediaItem = {
    id: string;
    folder_id: number | null;
    title: string;
    original_name: string;
    mime_type: string;
    size: number;
    alt_text: string | null;
    visibility: 'private' | 'public';
    tags: string[];
    is_image: boolean;
    created_at: string | null;
    uploader: string | null;
    download_url: string;
    thumbnail_url: string | null;
    usages: MediaUsage[];
    can: { update: boolean; delete: boolean };
};

export type MediaType = 'image' | 'document';

export type MediaSort = 'created_at' | 'title' | 'size';

export type MediaFilters = {
    search: string | null;
    type: MediaType | null;
    visibility: MediaItem['visibility'] | null;
    tag: string | null;
    folder: number | 'none' | null;
    sort: MediaSort;
    direction: 'asc' | 'desc';
    per_page: number;
};

export type MediaStats = { count: number; size: number; unfiled: number };

export type MediaFolder = {
    id: number;
    name: string;
    parent_id: number | null;
    depth: number;
    path: string;
    files_count: number;
};

export type MediaPagination = Pagination<MediaItem>;

export type MediaBulkAction = 'public' | 'private' | 'delete' | 'move';

export type MediaFolderDialogMode =
    | { type: 'create'; parentId: number | null }
    | { type: 'edit'; folder: MediaFolder };
