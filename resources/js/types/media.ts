export type MediaItem = {
    id: string;
    title: string;
    original_name: string;
    mime_type: string;
    size: number;
    alt_text: string | null;
    visibility: 'private' | 'public';
    download_url: string;
    thumbnail_url: string | null;
    can: { update: boolean; delete: boolean };
};
