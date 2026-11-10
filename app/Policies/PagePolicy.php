<?php

namespace App\Policies;

use App\Models\Page;
use App\Models\User;

/**
 * All staff roles (admin, editor, author) manage all pages.
 */
class PagePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Page $page): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Page $page): bool
    {
        return true;
    }

    public function delete(User $user, Page $page): bool
    {
        return true;
    }

    public function restore(User $user, Page $page): bool
    {
        return true;
    }

    public function forceDelete(User $user, Page $page): bool
    {
        return true;
    }
}
