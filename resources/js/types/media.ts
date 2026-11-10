export type MediaUsage = {
    type: string;
    title: string;
    trashed: boolean;
};

export type MediaItem = {
    id: number;
    url: string;
    thumb_url: string;
    medium_url: string;
    srcset: string;
    width: number | null;
    height: number | null;
    size: number;
    mime: string;
    original_name: string;
    alt: string | null;
    caption: string | null;
    uploaded_at: string | null;
    uploader?: string | null;
    usages?: MediaUsage[];
};

export type RecommendedSize = {
    label: string;
    width: number | null;
    height: number | null;
    min_width: number | null;
    min_height: number | null;
};

export type RecommendedSizeKey = 'slider' | 'featured' | 'gallery' | 'alumni';

export type UploadSettings = {
    maxKb: number;
    accept: string;
    recommended: Record<RecommendedSizeKey, RecommendedSize>;
};

export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

export type Paginated<T> = {
    data: T[];
    meta: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number | null;
        to: number | null;
        links: PaginationLink[];
    };
};
