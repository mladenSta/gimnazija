<?php

namespace App\Policies;

use App\Models\Post;
use App\Models\User;

/**
 * Admin and editor: all posts. Author: only their own posts.
 */
class PostPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Post $post): bool
    {
        return $this->owns($user, $post);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Post $post): bool
    {
        return $this->owns($user, $post);
    }

    public function delete(User $user, Post $post): bool
    {
        return $this->owns($user, $post);
    }

    public function restore(User $user, Post $post): bool
    {
        return $this->owns($user, $post);
    }

    public function forceDelete(User $user, Post $post): bool
    {
        return $this->owns($user, $post);
    }

    private function owns(User $user, Post $post): bool
    {
        return $user->canManageAllPosts() || $post->user_id === $user->id;
    }
}
