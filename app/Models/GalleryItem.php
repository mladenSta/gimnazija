<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\GalleryItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property int $gallery_id
 * @property int $media_id
 * @property string|null $caption
 * @property int $sort_order
 * @property int $focal_x 0–100
 * @property int $focal_y 0–100
 * @property string|null $badge
 * @property string|null $title
 * @property string|null $subtitle
 * @property string|null $link_label
 * @property string|null $link_url
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read string $object_position
 */
#[Fillable([
    'gallery_id', 'media_id', 'caption', 'sort_order', 'focal_x', 'focal_y',
    'badge', 'title', 'subtitle', 'link_label', 'link_url',
])]
class GalleryItem extends Model
{
    /** @use HasFactory<GalleryItemFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'focal_x' => 'integer',
            'focal_y' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Gallery, $this>
     */
    public function gallery(): BelongsTo
    {
        return $this->belongsTo(Gallery::class);
    }

    /**
     * @return BelongsTo<Media, $this>
     */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    /**
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    /**
     * CSS object-position for front-end cropping, e.g. "30% 60%".
     */
    public function getObjectPositionAttribute(): string
    {
        return $this->focal_x.'% '.$this->focal_y.'%';
    }
}
