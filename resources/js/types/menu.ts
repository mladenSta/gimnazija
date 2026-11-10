export type MenuItemType = 'page' | 'category' | 'custom' | 'posts-index';

export type MenuNode = {
    /** Client-side key (stable across drags). */
    key: string;
    id: number | null;
    type: MenuItemType;
    page_id: number | null;
    category_id: number | null;
    url: string | null;
    label: string | null;
    original_label: string | null;
    href: string | null;
    warning: string | null;
    children: MenuNode[];
    open?: boolean;
};
