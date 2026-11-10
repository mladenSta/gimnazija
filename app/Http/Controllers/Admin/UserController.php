<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserRequest;
use App\Models\Media;
use App\Models\Page;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', User::class);

        /** @var User $me */
        $me = $request->user();

        $users = User::query()
            ->withCount(['posts' => fn ($q) => $q->withTrashed(), 'pages' => fn ($q) => $q->withTrashed()])
            ->orderBy('name')
            ->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->role->value,
                'role_label' => $u->role->label(),
                'posts_count' => $u->posts_count,
                'pages_count' => $u->pages_count,
                'created_at' => $u->created_at?->format('d.m.Y.'),
                'is_me' => $u->is($me),
                'two_factor' => $u->two_factor_confirmed_at !== null,
            ]);

        return Inertia::render('admin/users/Index', [
            'users' => $users,
            'roles' => Role::options(),
        ]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $user = new User([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => $data['role'],
        ]);
        // Created by an admin, so the address counts as verified.
        $user->email_verified_at = now();
        $user->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Korisnik je dodat.']);

        return to_route('admin.users.index');
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();

        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $data['role'],
        ]);

        if (filled($data['password'] ?? null)) {
            $user->password = $data['password'];
        }

        $user->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Korisnik je sačuvan.']);

        return to_route('admin.users.index');
    }

    /**
     * Delete a user. Their posts, pages and uploads can be handed to another user;
     * otherwise they stay on the site without an author.
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('delete', $user);

        $data = $request->validate([
            'reassign_to' => ['nullable', 'integer', 'exists:users,id', 'not_in:'.$user->id],
        ]);

        DB::transaction(function () use ($user, $data) {
            $to = $data['reassign_to'] ?? null;

            if ($to !== null) {
                Post::withTrashed()->where('user_id', $user->id)->update(['user_id' => $to]);
                Page::withTrashed()->where('user_id', $user->id)->update(['user_id' => $to]);
                Media::query()->where('user_id', $user->id)->update(['user_id' => $to]);
            }

            $user->delete();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Korisnik je obrisan.']);

        return to_route('admin.users.index');
    }
}
