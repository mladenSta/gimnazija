<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\MediaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Facades\Storage;

/**
 * An uploaded image. Files live on the "uploads" disk
 * (public/wp-content/uploads/YYYY/MM/...).
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $disk
 * @property string $path
 * @property string $original_name
 * @property string $mime
 * @property int|null $width
 * @property int|null $height
 * @property int $size
 * @property string|null $alt
 * @property string|null $caption
 * @property array<string, array{path: string, width: int, height: int}>|null $variants
 * @property int|null $wp_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable([
    'user_id', 'disk', 'path', 'original_name', 'mime', 'width', 'height',
    'size', 'alt', 'caption', 'variants', 'wp_id',
])]
class Media extends Model
{
    /** @use HasFactory<MediaFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'variants' => 'array',
            'width' => 'integer',
            'height' => 'integer',
            'size' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * URL of a variant, falling back to the stored original.
     */
    public function url(?string $variant = null): string
    {
        $path = $variant !== null
            ? ($this->variants[$variant]['path'] ?? $this->path)
            : $this->path;

        return Storage::disk($this->disk)->url($path);
    }

    /**
     * "url 400w, url 800w, …, main 1920w" for <img srcset>. Empty when there are no variants.
     */
    public function srcset(): string
    {
        if (empty($this->variants) || $this->width === null) {
            return '';
        }

        $disk = Storage::disk($this->disk);

        return collect($this->variants)
            ->sortBy('width')
            ->map(fn (array $v) => $disk->url($v['path']).' '.$v['width'].'w')
            ->push($disk->url($this->path).' '.$this->width.'w')
            ->implode(', ');
    }

    public function isPortrait(): bool
    {
        return $this->width !== null && $this->height !== null && $this->height > $this->width;
    }

    // ---- Usage (an image in use can't be deleted) -------------------------

    /**
     * @return HasMany<Post, $this>
     */
    public function featuredInPosts(): HasMany
    {
        return $this->hasMany(Post::class, 'featured_media_id')->withTrashed();
    }

    /**
     * @return HasMany<Page, $this>
     */
    public function featuredInPages(): HasMany
    {
        return $this->hasMany(Page::class, 'featured_media_id')->withTrashed();
    }

    /**
     * @return MorphToMany<Post, $this>
     */
    public function attachedToPosts(): MorphToMany
    {
        return $this->morphedByMany(Post::class, 'mediable')->withPivot(['role', 'sort_order'])->withTrashed();
    }

    /**
     * @return MorphToMany<Page, $this>
     */
    public function attachedToPages(): MorphToMany
    {
        return $this->morphedByMany(Page::class, 'mediable')->withPivot(['role', 'sort_order'])->withTrashed();
    }

    /**
     * @return HasMany<GalleryItem, $this>
     */
    public function galleryItems(): HasMany
    {
        return $this->hasMany(GalleryItem::class);
    }

    /**
     * @return HasMany<AlumniTestimonial, $this>
     */
    public function alumniTestimonials(): HasMany
    {
        return $this->hasMany(AlumniTestimonial::class, 'photo_media_id');
    }

    /**
     * Where this image is used, for the admin "used in" list.
     *
     * @return list<array{type: string, title: string, trashed: bool}>
     */
    public function usages(): array
    {
        $usages = [];

        foreach ($this->featuredInPosts()->get(['id', 'title', 'deleted_at']) as $post) {
            $usages[] = ['type' => 'Naslovna slika objave', 'title' => $post->title, 'trashed' => $post->trashed()];
        }
        foreach ($this->featuredInPages()->get(['id', 'title', 'deleted_at']) as $page) {
            $usages[] = ['type' => 'Naslovna slika stranice', 'title' => $page->title, 'trashed' => $page->trashed()];
        }
        foreach ($this->attachedToPosts()->get(['posts.id', 'title', 'deleted_at']) as $post) {
            $usages[] = ['type' => 'Objava', 'title' => $post->title, 'trashed' => $post->trashed()];
        }
        foreach ($this->attachedToPages()->get(['pages.id', 'title', 'deleted_at']) as $page) {
            $usages[] = ['type' => 'Stranica', 'title' => $page->title, 'trashed' => $page->trashed()];
        }
        foreach ($this->galleryItems()->with('gallery:id,name')->get() as $item) {
            $usages[] = ['type' => 'Galerija', 'title' => $item->gallery->name, 'trashed' => false];
        }
        foreach ($this->alumniTestimonials()->get(['id', 'name']) as $alumni) {
            $usages[] = ['type' => 'Bivši učenik', 'title' => $alumni->name, 'trashed' => false];
        }

        return $usages;
    }

    public function isInUse(): bool
    {
        return $this->featuredInPosts()->exists()
            || $this->featuredInPages()->exists()
            || $this->attachedToPosts()->exists()
            || $this->attachedToPages()->exists()
            || $this->galleryItems()->exists()
            || $this->alumniTestimonials()->exists();
    }
}
