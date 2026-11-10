<?php

namespace App\Policies;

use App\Models\Gallery;
use App\Models\User;

/**
 * All staff roles manage galleries. The home-page system galleries
 * can be edited but never deleted.
 */
class GalleryPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Gallery $gallery): bool
    {
        return true;
    }

    public function delete(User $user, Gallery $gallery): bool
    {
        return ! $gallery->is_system;
    }
}
