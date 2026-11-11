<?php

namespace App\Models;

use App\Enums\ContentStatus;
use App\Models\Concerns\HasMediaAttachments;
use App\Models\Concerns\HasVideos;
use App\Models\Concerns\Publishable;
use Carbon\CarbonImmutable;
use Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $title
 * @property string $slug
 * @property string|null $excerpt
 * @property string|null $content
 * @property string|null $video_url_1
 * @property string|null $video_url_2
 * @property int|null $featured_media_id
 * @property ContentStatus $status
 * @property CarbonImmutable|null $published_at
 * @property string|null $meta_title
 * @property string|null $meta_description
 * @property int|null $wp_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read Category|null $primary_category
 */
#[Fillable([
    'user_id', 'title', 'slug', 'excerpt', 'content', 'video_url_1', 'video_url_2', 'featured_media_id',
    'status', 'published_at', 'meta_title', 'meta_description', 'wp_id',
])]
class Post extends Model
{
    /** @use HasFactory<PostFactory> */
    use HasFactory, HasMediaAttachments, HasVideos, Publishable, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ContentStatus::class,
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Ordered: the first category is the primary one (breadcrumb).
     *
     * @return BelongsToMany<Category, $this>
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class)
            ->withPivot('sort_order')
            ->orderByPivot('sort_order');
    }

    /**
     * Replace the categories, keeping the given order (first = primary).
     *
     * @param  list<int>  $categoryIds
     */
    public function syncCategories(array $categoryIds): void
    {
        $rows = [];
        foreach (array_values(array_unique($categoryIds)) as $index => $id) {
            $rows[$id] = ['sort_order' => $index];
        }

        $this->categories()->sync($rows);
    }

    /**
     * Public path, WordPress structure: /slug/
     */
    public function path(): string
    {
        return "/{$this->slug}/";
    }

    /**
     * Excerpt, or the beginning of the text.
     */
    public function summary(int $length = 160): string
    {
        $text = filled($this->excerpt) ? (string) $this->excerpt : strip_tags((string) preg_replace('/<[^>]+>/', ' $0', (string) $this->content));
        $text = trim((string) preg_replace('/\s+/u', ' ', html_entity_decode($text, ENT_QUOTES | ENT_HTML5)));

        return Str::limit($text, $length);
    }

    public function getPrimaryCategoryAttribute(): ?Category
    {
        return $this->categories->first();
    }
}
