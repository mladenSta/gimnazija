<?php

namespace App\Http\Resources;

use App\Models\Page;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Page data for the admin editor.
 *
 * @mixin Page
 */
class PageFormResource extends JsonResource
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
            'content' => $this->content,
            'video_url_1' => $this->video_url_1,
            'video_url_2' => $this->video_url_2,
            'status' => $this->status->value,
            'published_at' => $this->published_at?->format('Y-m-d\TH:i'),
            'is_scheduled' => $this->isScheduled(),
            // resolve(): plain arrays (Inertia would otherwise wrap nested resources in "data").
            'featured_image' => $this->featuredImage ? MediaResource::make($this->featuredImage)->resolve($request) : null,
            'gallery' => MediaResource::collection($this->galleryImages)->resolve($request),
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'author' => $this->author?->name,
            'trashed' => $this->trashed(),
            'updated_at' => $this->updated_at?->format('d.m.Y. H:i'),
            'in_menu' => $this->menuItems()->exists(),
        ];
    }
}
