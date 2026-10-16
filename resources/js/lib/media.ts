import type { MediaItem } from '@/types/media';

export function formatFileSize(bytes: number): string {
    if (bytes >= 1073741824) {
        return `${(bytes / 1073741824).toFixed(1)} Go`;
    }

    return bytes >= 1048576
        ? `${(bytes / 1048576).toFixed(1)} Mo`
        : `${Math.max(1, Math.ceil(bytes / 1024))} Ko`;
}

export function formatMediaDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Intl.DateTimeFormat('fr-CH', { dateStyle: 'medium' }).format(
        new Date(value),
    );
}

export function fileExtension(item: Pick<MediaItem, 'original_name'>): string {
    const extension = item.original_name.split('.').pop() ?? '';

    return extension.toUpperCase();
}

export function isMissingAltText(
    item: Pick<MediaItem, 'is_image' | 'alt_text'>,
): boolean {
    return item.is_image && !item.alt_text?.trim();
}

export const MEDIA_DRAG_TYPE = 'application/x-media-ids';
