<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;

/**
 * Categories are managed by admins only. (Everyone can still assign
 * existing categories to posts.)
 */
class CategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Category $category): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Category $category): bool
    {
        return $user->isAdmin();
    }
}
