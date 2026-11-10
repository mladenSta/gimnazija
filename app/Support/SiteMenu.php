<?php

namespace App\Support;

use App\Enums\MenuItemType;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Page;
use Illuminate\Support\Facades\Cache;

/**
 * The public main menu as a plain array, cached until menu items,
 * pages or categories change.
 */
class SiteMenu
{
    public const CACHE_KEY = 'site.menu';

    /**
     * @return list<array{label: string, href: string|null, children: list<array{label: string, href: string|null}>}>
     */
    public static function tree(): array
    {
        /** @var list<array{label: string, href: string|null, children: list<array{label: string, href: string|null}>}> */
        return Cache::rememberForever(self::CACHE_KEY, function () {
            $items = MenuItem::query()
                ->roots()
                ->with(['page', 'category', 'children.page', 'children.category'])
                ->get();

            $tree = [];
            foreach ($items as $item) {
                if (! self::visible($item)) {
                    continue;
                }

                $children = [];
                foreach ($item->children as $child) {
                    if (self::visible($child)) {
                        $children[] = ['label' => $child->display_label, 'href' => $child->href];
                    }
                }

                // A heading without link and without visible children is useless.
                if ($item->href === null && $children === []) {
                    continue;
                }

                $tree[] = ['label' => $item->display_label, 'href' => $item->href, 'children' => $children];
            }

            return $tree;
        });
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Clear the cache whenever something the menu shows changes.
     */
    public static function registerCacheInvalidation(): void
    {
        foreach ([MenuItem::class, Page::class, Category::class] as $model) {
            $model::saved(fn () => self::flush());
            $model::deleted(fn () => self::flush());
        }

        Page::restored(fn () => self::flush());
    }

    private static function visible(MenuItem $item): bool
    {
        return match ($item->type) {
            MenuItemType::Page => $item->page !== null && $item->page->isPublished(),
            MenuItemType::Category => $item->category !== null,
            default => true,
        };
    }
}
