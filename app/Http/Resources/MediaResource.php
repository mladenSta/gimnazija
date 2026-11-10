<?php

namespace App\Http\Resources;

use App\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Media
 */
class MediaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'url' => $this->url(),
            'thumb_url' => $this->url('thumb'),
            'medium_url' => $this->url('medium'),
            'srcset' => $this->srcset(),
            'width' => $this->width,
            'height' => $this->height,
            'size' => $this->size,
            'mime' => $this->mime,
            'original_name' => $this->original_name,
            'alt' => $this->alt,
            'caption' => $this->caption,
            'uploaded_at' => $this->created_at?->format('d.m.Y.'),
            'uploader' => $this->whenLoaded('uploader', fn () => $this->uploader?->name),
            'usages' => $this->when($request->routeIs('admin.media.show'), fn () => $this->usages()),
        ];
    }
}
