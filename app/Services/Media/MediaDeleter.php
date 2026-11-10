<?php

namespace App\Services\Media;

use App\Exceptions\MediaInUseException;
use App\Models\Media;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class MediaDeleter
{
    /**
     * Delete the row and its files. Refuses when the image is still used.
     *
     * @throws MediaInUseException
     */
    public function delete(Media $media): void
    {
        if ($media->isInUse()) {
            throw new MediaInUseException;
        }

        $paths = [$media->path, ...array_column($media->variants ?? [], 'path')];

        try {
            DB::transaction(fn () => $media->delete());
        } catch (QueryException) {
            // A foreign key still points at it (race with another save).
            throw new MediaInUseException;
        }

        Storage::disk($media->disk)->delete($paths);
    }
}
