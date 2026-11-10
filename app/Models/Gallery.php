<?php

namespace App\Models;

use App\Enums\GalleryType;
use Carbon\CarbonImmutable;
use Database\Factories\GalleryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property GalleryType $type
 * @property bool $is_system
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read int|null $items_count
 */
#[Fillable(['name', 'slug', 'type', 'is_system'])]
class Gallery extends Model
{
    /** @use HasFactory<GalleryFactory> */
    use HasFactory;

    /** System galleries used on the home page (can't be deleted). */
    public const HOME_SLIDER = 'home-slider';

    public const HOME_TOP = 'home-top';

    public const HOME_BOTTOM = 'home-bottom';

    protected static function booted(): void
    {
        static::deleting(function (Gallery $gallery): void {
            if ($gallery->is_system) {
                throw new LogicException("System gallery [{$gallery->slug}] can't be deleted.");
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => GalleryType::class,
            'is_system' => 'boolean',
        ];
    }

    /**
     * @return HasMany<GalleryItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(GalleryItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public static function findBySlug(string $slug): ?self
    {
        return static::query()->where('slug', $slug)->first();
    }
}
