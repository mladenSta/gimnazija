<?php

namespace App\Support;

/**
 * Upload settings for admin screens that use the media picker.
 */
class UploadSettings
{
    /**
     * @return array{maxKb: int, accept: string, recommended: array<string, mixed>}
     */
    public static function toArray(): array
    {
        return [
            'maxKb' => (int) config('cms.images.max_upload_kb'),
            'accept' => 'image/jpeg,image/png,image/webp',
            'recommended' => config('cms.images.recommended'),
        ];
    }
}
