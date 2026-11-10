<?php

namespace App\Policies;

use App\Models\AlumniTestimonial;
use App\Models\User;

/**
 * Alumni testimonials are managed by admins only.
 */
class AlumniTestimonialPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, AlumniTestimonial $testimonial): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, AlumniTestimonial $testimonial): bool
    {
        return $user->isAdmin();
    }
}
