<?php

namespace App\Policies;

use App\Models\User;

/**
 * Only admins manage users. An admin can't delete themself or change
 * their own role (so the site always keeps an administrator).
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, User $model): bool
    {
        return $user->isAdmin();
    }

    public function changeRole(User $user, User $model): bool
    {
        return $user->isAdmin() && ! $user->is($model);
    }

    public function delete(User $user, User $model): bool
    {
        return $user->isAdmin() && ! $user->is($model);
    }
}
