<?php

namespace App\Models;

use App\Enums\MenuItemType;
use Carbon\CarbonImmutable;
use Database\Factories\MenuItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Main menu item. Nesting (parent_id) creates submenus.
 *
 * @property int $id
 * @property int|null $parent_id
 * @property int $sort_order
 * @property MenuItemType $type
 * @property int|null $page_id
 * @property int|null $category_id
 * @property string|null $url
 * @property string|null $label
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read string $display_label
 * @property-read string|null $href
 */
#[Fillable(['parent_id', 'sort_order', 'type', 'page_id', 'category_id', 'url', 'label'])]
class MenuItem extends Model
{
    /** @use HasFactory<MenuItemFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => MenuItemType::class,
            'sort_order' => 'integer',
        ];
    }

    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function roots(Builder $query): void
    {
        $query->whereNull('parent_id')->orderBy('sort_order');
    }

    /**
     * @return BelongsTo<MenuItem, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'parent_id');
    }

    /**
     * @return HasMany<MenuItem, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(MenuItem::class, 'parent_id')->orderBy('sort_order');
    }

    /**
     * @return BelongsTo<Page, $this>
     */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** Deepest allowed nesting: 1 = top level, 2 = submenu. */
    public const MAX_DEPTH = 2;

    /**
     * Public URL (WordPress structure). Null for a heading without a link.
     * Expects page/category to be eager-loaded.
     */
    public function getHrefAttribute(): ?string
    {
        return match ($this->type) {
            MenuItemType::Page => $this->page ? "/{$this->page->slug}/" : null,
            MenuItemType::Category => $this->category ? "/category/{$this->category->slug}/" : null,
            MenuItemType::PostsIndex => '/vijesti/',
            MenuItemType::Custom => filled($this->url) ? (string) $this->url : null,
        };
    }

    /**
     * Custom label, or the linked page/category title.
     */
    public function getDisplayLabelAttribute(): string
    {
        if (filled($this->label)) {
            return (string) $this->label;
        }

        return match ($this->type) {
            MenuItemType::Page => $this->page->title ?? '',
            MenuItemType::Category => $this->category->name ?? '',
            MenuItemType::PostsIndex => 'Vijesti',
            MenuItemType::Custom => (string) $this->url,
        };
    }
}
