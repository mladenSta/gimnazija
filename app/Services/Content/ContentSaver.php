<?php

namespace App\Services\Content;

use App\Enums\ContentStatus;
use App\Models\Page;
use App\Models\Post;
use App\Models\User;
use App\Support\ContentSanitizer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Saves a post or page from validated admin form data:
 * slug, sanitized HTML, publish date, images, categories and redirects.
 */
class ContentSaver
{
    public function __construct(
        private SlugGenerator $slugs,
        private ContentSanitizer $sanitizer,
        private InlineImageExtractor $inlineImages,
        private SlugRedirector $redirector,
    ) {}

    /**
     * @param  array<string, mixed>  $data  validated input
     */
    public function save(Post|Page $model, array $data, User $user): Post|Page
    {
        return DB::transaction(function () use ($model, $data, $user) {
            $wasPublished = $model->exists && $model->isPublished();
            $oldSlug = $model->exists ? $model->slug : null;

            $slug = filled($data['slug'] ?? null)
                ? (string) $data['slug']
                : $this->slugs->unique((string) $data['title'], $model);

            $status = ContentStatus::from((string) $data['status']);
            $publishedAt = filled($data['published_at'] ?? null) ? Carbon::parse((string) $data['published_at']) : null;

            if ($status === ContentStatus::Published && $publishedAt === null) {
                $publishedAt = now();
            }

            $content = $this->sanitizer->clean($data['content'] ?? null);

            $attributes = [
                'title' => $data['title'],
                'slug' => $slug,
                'content' => $content,
                'featured_media_id' => $data['featured_media_id'] ?? null,
                'status' => $status,
                'published_at' => $publishedAt,
                'meta_title' => $data['meta_title'] ?? null,
                'meta_description' => $data['meta_description'] ?? null,
            ];

            if ($model instanceof Post) {
                $attributes['excerpt'] = $data['excerpt'] ?? null;
            }

            $model->fill($attributes);

            if (! $model->exists) {
                $model->user_id = $user->id;
            }

            $model->save();

            $model->syncGalleryImages(array_values(array_map('intval', $data['gallery_ids'] ?? [])));
            $model->syncInlineImages($this->inlineImages->mediaIds($content));

            if ($model instanceof Post && array_key_exists('category_ids', $data)) {
                $model->syncCategories(array_values(array_map('intval', $data['category_ids'] ?? [])));
            }

            if ($wasPublished && $oldSlug !== null && $oldSlug !== $slug) {
                $this->redirector->slugChanged($oldSlug, $slug);
            }

            return $model;
        });
    }
}
