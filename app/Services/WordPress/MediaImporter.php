<?php

namespace App\Services\WordPress;

use App\Models\Media;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * Registers WordPress images (already copied to public/wp-content/uploads)
 * in the media library. Files are not copied or converted: the main file is
 * the uploaded image (WordPress already scaled big photos to ≤2560px), and
 * WordPress's proportional resized copies become the srcset variants.
 */
class MediaImporter
{
    /** Proportional WordPress sizes (cropped ones like "thumbnail" are skipped). */
    private const SIZES = ['medium', 'medium_large', 'large', '1536x1536', '2048x2048'];

    /** @var array<int, Media|null> */
    private array $cache = [];

    /** @var list<string> */
    public array $missing = [];

    private Filesystem $disk;

    public function __construct(private AttachmentIndex $attachments)
    {
        $this->disk = Storage::disk(config('cms.images.disk'));
    }

    public function media(int $attachmentId): ?Media
    {
        if (array_key_exists($attachmentId, $this->cache)) {
            return $this->cache[$attachmentId];
        }

        return $this->cache[$attachmentId] = $this->import($attachmentId);
    }

    private function import(int $id): ?Media
    {
        $attachment = $this->attachments->get($id);

        if ($attachment === null) {
            $this->missing[] = "#{$id} (nije slika ili ne postoji u WordPress bazi)";

            return null;
        }

        $path = $attachment['file'];

        if (! $this->disk->exists($path)) {
            $this->missing[] = $path;

            return null;
        }

        $meta = $attachment['meta'];
        $dir = trim(dirname($path), '.');
        $dir = $dir === '' ? '' : $dir.'/';

        $width = (int) ($meta['width'] ?? 0) ?: null;
        $height = (int) ($meta['height'] ?? 0) ?: null;

        // Proportional copies that exist on disk, smallest first.
        $sizes = [];
        foreach (self::SIZES as $name) {
            $size = $meta['sizes'][$name] ?? null;

            if (! is_array($size) || empty($size['file'])) {
                continue;
            }

            $file = $dir.$size['file'];
            $w = (int) ($size['width'] ?? 0);

            if ($w > 0 && ($width === null || $w < $width) && $this->disk->exists($file)) {
                $sizes[$w] = ['path' => $file, 'width' => $w, 'height' => (int) ($size['height'] ?? 0)];
            }
        }
        ksort($sizes);

        $variants = [];
        $byWidth = array_values($sizes);
        // thumb ≈ 300, medium ≈ 768, large ≈ 1536 (or the biggest available); others kept for srcset.
        foreach ($byWidth as $i => $variant) {
            $key = match (true) {
                $variant['width'] <= 400 && ! isset($variants['thumb']) => 'thumb',
                $variant['width'] <= 900 && ! isset($variants['medium']) => 'medium',
                default => 'wp-'.$variant['width'],
            };
            $variants[$key] = $variant;
        }
        $largest = end($byWidth);
        if ($largest !== false && $largest['width'] > 900) {
            $preferred = collect($byWidth)->first(fn ($v) => $v['width'] >= 1400) ?? $largest;
            unset($variants['wp-'.$preferred['width']]);
            $variants['large'] = $preferred;
        }

        $original = (string) ($meta['original_image'] ?? basename($path));

        return Media::query()->updateOrCreate(
            ['wp_id' => $id],
            [
                'user_id' => null,
                'disk' => config('cms.images.disk'),
                'path' => $path,
                'original_name' => $original,
                'mime' => $attachment['mime'],
                'width' => $width,
                'height' => $height,
                'size' => (int) $this->disk->size($path),
                'alt' => $attachment['alt'],
                'caption' => $attachment['caption'],
                'variants' => $variants ?: null,
            ],
        );
    }
}
