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

    public const HOME_ABOUT = 'home-about';

    /**
     * Where each system gallery appears (shown in the admin).
     *
     * @var array<string, string>
     */
    public const USAGE = [
        self::HOME_SLIDER => 'Veliki slajder na vrhu početne stranice.',
        self::HOME_TOP => 'Početna: traka „jedan dan u našoj gimnaziji" (sve) i velika fotografija pored poruka bivših učenika (4.).',
        self::HOME_ABOUT => 'Početna: kolaž u odjeljku „Tradicija koja gleda naprijed" (prve 3).',
        self::HOME_BOTTOM => 'Početna: galerija „Uspomene u kadru" sa filterima po oznakama.',
    ];

    public function usage(): ?string
    {
        return $this->is_system ? (self::USAGE[$this->slug] ?? null) : null;
    }

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
