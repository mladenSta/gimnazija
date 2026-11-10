/**
 * Same rules as App\Support\Slug (server): Serbian Latin → ASCII, lowercase, hyphens.
 * The server re-checks and makes the slug unique on save.
 */
const map: Record<string, string> = {
    č: 'c',
    ć: 'c',
    š: 's',
    ž: 'z',
    đ: 'dj',
    dž: 'dz',
    Č: 'c',
    Ć: 'c',
    Š: 's',
    Ž: 'z',
    Đ: 'dj',
    DŽ: 'dz',
    Dž: 'dz',
};

export function slugify(value: string): string {
    return value
        .replace(/dž|Dž|DŽ|[čćšžđČĆŠŽĐ]/g, (ch) => map[ch] ?? ch)
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .slice(0, 190);
}
