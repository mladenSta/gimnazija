<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SocialNetwork;
use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\User;
use App\Services\Social\SocialSharer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * "Objavi na FB / IG / LinkedIn" buttons in the post editor.
 */
class PostShareController extends Controller
{
    public function store(Request $request, Post $post, SocialNetwork $network, SocialSharer $sharer): RedirectResponse
    {
        Gate::authorize('share', $post);

        /** @var User $user */
        $user = $request->user();
        $post->loadMissing('featuredImage');
        $share = $sharer->share($post, $network, $user);

        Inertia::flash('toast', [
            'type' => $share->success ? 'success' : 'error',
            'message' => $share->message,
        ]);

        return to_route('admin.posts.edit', $post);
    }
}
