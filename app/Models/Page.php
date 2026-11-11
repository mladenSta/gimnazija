<?php

namespace App\Models;

use App\Enums\ContentStatus;
use App\Models\Concerns\HasMediaAttachments;
use App\Models\Concerns\HasVideos;
use App\Models\Concerns\Publishable;
use Carbon\CarbonImmutable;
use Database\Factories\PageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $title
 * @property string $slug
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
 * @property-read int|null $menu_items_count
 */
#[Fillable([
    'user_id', 'title', 'slug', 'content', 'video_url_1', 'video_url_2', 'featured_media_id',
    'status', 'published_at', 'meta_title', 'meta_description', 'wp_id',
])]
class Page extends Model
{
    /** @use HasFactory<PageFactory> */
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
     * Public path, WordPress structure: /slug/
     */
    public function path(): string
    {
        return "/{$this->slug}/";
    }

    public function summary(int $length = 160): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) preg_replace('/<[^>]+>/', ' $0', (string) $this->content)), ENT_QUOTES | ENT_HTML5)));

        return Str::limit($text, $length);
    }

    /**
     * @return HasMany<MenuItem, $this>
     */
    public function menuItems(): HasMany
    {
        return $this->hasMany(MenuItem::class);
    }
}
