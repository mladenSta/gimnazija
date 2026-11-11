<?php

namespace App\Services\Social;

use App\Models\Media;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\ImageManager;
use Throwable;

/**
 * Instagram and LinkedIn don't take WebP, so the featured image is copied as
 * JPEG to uploads/social/{media id}.jpg (public, the network downloads it).
 *
 * Instagram also needs an aspect ratio between 4:5 and 1.91:1 and at most
 * 1440 px wide, so taller/wider photos are cropped around the centre.
 */
class JpegCopy
{
    private const MIN_RATIO = 0.8;

    private const MAX_RATIO = 1.91;

    /**
     * @return array{url: string, path: string} public URL and local file path
     *
     * @throws SocialPublishException
     */
    public function make(Media $media): array
    {
        $source = Storage::disk($media->disk);
        $target = Storage::disk((string) config('cms.images.disk'));
        $path = "social/{$media->id}.jpg";

        try {
            $image = (new ImageManager(ImagickDriver::class, strip: true))->decodePath($source->path($media->path));
            $image->scaleDown(1440, 1440);

            $width = $image->width();
            $height = $image->height();
            $ratio = $width / max(1, $height);

            if ($ratio < self::MIN_RATIO) {
                $image->cover($width, (int) floor($width / self::MIN_RATIO));
            } elseif ($ratio > self::MAX_RATIO) {
                $image->cover((int) floor($height * self::MAX_RATIO), $height);
            }

            $target->put($path, (string) $image->encode(new JpegEncoder(quality: 88)));
        } catch (Throwable $e) {
            report($e);

            throw new SocialPublishException('Naslovnu sliku nije moguće pripremiti za objavu (JPEG).');
        }

        // ?v= so the network doesn't reuse an older copy of the file.
        return ['url' => $target->url($path).'?v='.time(), 'path' => $target->path($path)];
    }
}
