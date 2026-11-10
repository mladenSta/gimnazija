<?php

namespace App\Models\Concerns;

use App\Enums\MediaRole;
use App\Models\Media;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * Featured image + "other images" (bottom gallery) + inline content images.
 *
 * @property int|null $featured_media_id
 */
trait HasMediaAttachments
{
    public static function bootHasMediaAttachments(): void
    {
        // mediables is polymorphic, so the database can't cascade it.
        static::forceDeleting(function (self $model): void {
            $model->allMedia()->detach();
        });
    }

    /**
     * @return BelongsTo<Media, $this>
     */
    public function featuredImage(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'featured_media_id');
    }

    /**
     * Every attached media row, regardless of role.
     *
     * @return MorphToMany<Media, $this>
     */
    public function allMedia(): MorphToMany
    {
        return $this->morphToMany(Media::class, 'mediable')
            ->withPivot(['role', 'sort_order']);
    }

    /**
     * "Other images" shown as a lightbox gallery below the content.
     *
     * @return MorphToMany<Media, $this>
     */
    public function galleryImages(): MorphToMany
    {
        return $this->allMedia()
            ->wherePivot('role', MediaRole::Gallery->value)
            ->orderByPivot('sort_order');
    }

    /**
     * Images referenced inside the content HTML.
     *
     * @return MorphToMany<Media, $this>
     */
    public function inlineImages(): MorphToMany
    {
        return $this->allMedia()->wherePivot('role', MediaRole::Inline->value);
    }

    /**
     * Replace the bottom gallery with the given media ids, in order.
     *
     * @param  list<int>  $mediaIds
     */
    public function syncGalleryImages(array $mediaIds): void
    {
        $this->syncMediaRole(MediaRole::Gallery, $mediaIds);
    }

    /**
     * @param  list<int>  $mediaIds
     */
    public function syncInlineImages(array $mediaIds): void
    {
        $this->syncMediaRole(MediaRole::Inline, $mediaIds);
    }

    /**
     * @param  list<int>  $mediaIds
     */
    protected function syncMediaRole(MediaRole $role, array $mediaIds): void
    {
        $this->allMedia()->wherePivot('role', $role->value)->detach();

        $rows = [];
        foreach (array_values(array_unique($mediaIds)) as $index => $id) {
            $rows[$id] = ['role' => $role->value, 'sort_order' => $index];
        }

        $this->allMedia()->attach($rows);
    }
}
