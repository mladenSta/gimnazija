<?php

namespace App\Policies;

use App\Models\Media;
use App\Models\User;

/**
 * Every staff role (admin, editor, author) can use the media library.
 * Deleting an image that is in use is blocked separately (MediaDeleter).
 */
class MediaPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Media $media): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Media $media): bool
    {
        return true;
    }

    public function delete(User $user, Media $media): bool
    {
        return true;
    }
}
