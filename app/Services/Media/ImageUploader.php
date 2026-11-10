<?php

namespace App\Services\Media;

use App\Models\Media;
use App\Models\User;
use App\Support\Slug;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;
use Throwable;

/**
 * Turns an uploaded photo into WebP files on the uploads disk and a Media row.
 *
 * The original is not kept. Files written to {YYYY}/{MM}/:
 *   name.webp        main file, fitted inside max_size × max_size
 *   name-1600.webp   variants, only when the image is larger than that size
 *   name-800.webp
 *   name-400.webp
 */
class ImageUploader
{
    private ImageManager $manager;

    public function __construct()
    {
        $this->manager = new ImageManager(
            ImagickDriver::class,
            autoOrientation: true,  // apply EXIF rotation before stripping
            decodeAnimation: false,
            strip: true,            // drop EXIF/GPS metadata
        );
    }

    public function store(UploadedFile $file, ?User $uploader = null): Media
    {
        $config = config('cms.images');
        $disk = Storage::disk($config['disk']);
        $dir = now()->format('Y/m');
        $base = $this->uniqueBaseName($disk, $dir, $file->getClientOriginalName());

        $image = $this->manager->decodePath($file->getRealPath());

        $written = [];

        try {
            // Main file.
            $image->scaleDown($config['max_size'], $config['max_size']);
            $mainPath = "{$dir}/{$base}.webp";
            $mainSize = $this->write($disk, $mainPath, $image, $config['quality']);
            $written[] = $mainPath;
            $width = $image->width();
            $height = $image->height();

            // Variants, largest first, each scaled down from the previous one.
            $variants = [];
            $variantSizes = $config['variants'];
            arsort($variantSizes);

            foreach ($variantSizes as $name => $size) {
                if (max($width, $height) <= $size) {
                    continue;
                }

                $image->scaleDown($size, $size);
                $path = "{$dir}/{$base}-{$size}.webp";
                $this->write($disk, $path, $image, $config['quality']);
                $written[] = $path;
                $variants[$name] = ['path' => $path, 'width' => $image->width(), 'height' => $image->height()];
            }

            return Media::query()->create([
                'user_id' => $uploader?->id,
                'disk' => $config['disk'],
                'path' => $mainPath,
                'original_name' => $file->getClientOriginalName(),
                'mime' => 'image/webp',
                'width' => $width,
                'height' => $height,
                'size' => $mainSize,
                'variants' => $variants ?: null,
            ]);
        } catch (Throwable $e) {
            $disk->delete($written);

            throw $e;
        }
    }

    private function write(Filesystem $disk, string $path, ImageInterface $image, int $quality): int
    {
        $encoded = (string) $image->encode(new WebpEncoder(quality: $quality, strip: true));
        $disk->put($path, $encoded);

        return strlen($encoded);
    }

    /**
     * "Dan otvorenih vrata.JPG" → "dan-otvorenih-vrata", then "-2", "-3"… if taken (WordPress style).
     */
    private function uniqueBaseName(Filesystem $disk, string $dir, string $originalName): string
    {
        $base = Slug::make(pathinfo($originalName, PATHINFO_FILENAME));
        $base = substr($base !== '' ? $base : 'slika', 0, 120);

        $candidate = $base;
        $n = 2;
        while ($disk->exists("{$dir}/{$candidate}.webp")
            || Media::query()->where('path', "{$dir}/{$candidate}.webp")->exists()) {
            $candidate = "{$base}-{$n}";
            $n++;
        }

        return $candidate;
    }
}
