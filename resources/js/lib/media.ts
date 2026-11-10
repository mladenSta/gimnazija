import type { MediaItem, RecommendedSize } from '@/types/media';

export function formatBytes(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${Math.round(bytes / 1024)} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(1).replace('.', ',')} MB`;
}

export function formatDimensions(
    media: Pick<MediaItem, 'width' | 'height'>,
): string {
    return media.width && media.height
        ? `${media.width} × ${media.height}`
        : '—';
}

/**
 * "Preporučeno: 1920 × 1080 px" style hint.
 */
export function recommendedHint(size: RecommendedSize): string {
    if (size.width && size.height) {
        return `Preporučeno: ${size.width} × ${size.height} px`;
    }

    return `Preporučeno: najmanje ${size.width ?? size.min_width} px po dužoj strani`;
}

/**
 * Warning when an image is smaller than the minimum for its use, else null.
 * Images that don't match the ratio are fine — the site crops them.
 */
export function sizeWarning(
    media: Pick<MediaItem, 'width' | 'height'>,
    size: RecommendedSize,
): string | null {
    if (!media.width || !media.height) {
        return null;
    }

    if (size.min_height === null) {
        const longSide = Math.max(media.width, media.height);

        return size.min_width !== null && longSide < size.min_width
            ? `Slika je mala (${media.width} × ${media.height}). Preporučeno je najmanje ${size.min_width} px.`
            : null;
    }

    if (
        (size.min_width !== null && media.width < size.min_width) ||
        media.height < size.min_height
    ) {
        return `Slika je mala (${media.width} × ${media.height}). Preporučeno je najmanje ${size.min_width} × ${size.min_height} px.`;
    }

    return null;
}
