<?php

namespace App\Http\Resources;

use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Post data for the admin editor.
 *
 * @mixin Post
 */
class PostFormResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'excerpt' => $this->excerpt,
            'content' => $this->content,
            'status' => $this->status->value,
            // For <input type="datetime-local">, in the app timezone.
            'published_at' => $this->published_at?->format('Y-m-d\TH:i'),
            'is_scheduled' => $this->isScheduled(),
            // resolve(): plain arrays (Inertia would otherwise wrap nested resources in "data").
            'featured_image' => $this->featuredImage ? MediaResource::make($this->featuredImage)->resolve($request) : null,
            'gallery' => MediaResource::collection($this->galleryImages)->resolve($request),
            'category_ids' => $this->categories->pluck('id')->all(),
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'author' => $this->author?->name,
            'trashed' => $this->trashed(),
            'updated_at' => $this->updated_at?->format('d.m.Y. H:i'),
        ];
    }
}
