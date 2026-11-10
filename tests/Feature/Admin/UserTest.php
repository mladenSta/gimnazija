<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Models\Media;
use App\Models\Page;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'Marko Marković',
            'email' => 'marko@gimnazija.test',
            'role' => 'editor',
            'password' => 'tajna-lozinka-1',
            'password_confirmation' => 'tajna-lozinka-1',
            ...$overrides,
        ];
    }

    public function test_only_admins_can_manage_users(): void
    {
        $target = User::factory()->create();

        foreach ([User::factory()->editor()->create(), User::factory()->author()->create()] as $user) {
            $this->actingAs($user)->get(route('admin.users.index'))->assertForbidden();
            $this->actingAs($user)->post(route('admin.users.store'), $this->payload())->assertForbidden();
            $this->actingAs($user)->put(route('admin.users.update', $target), $this->payload())->assertForbidden();
            $this->actingAs($user)->delete(route('admin.users.destroy', $target))->assertForbidden();
        }

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get(route('admin.users.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('admin/users/Index')->has('users', 4)->has('roles', 3));
    }

    public function test_admin_creates_a_verified_user_who_can_log_in(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.users.store'), $this->payload())
            ->assertRedirect(route('admin.users.index'));

        $user = User::query()->where('email', 'marko@gimnazija.test')->sole();
        $this->assertSame(Role::Editor, $user->role);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue(Hash::check('tajna-lozinka-1', $user->password));

        auth()->logout();
        $this->post(route('login.store'), ['email' => 'marko@gimnazija.test', 'password' => 'tajna-lozinka-1'])
            ->assertRedirect('/admin');
        $this->get('/admin')->assertOk();
    }

    public function test_validation_on_create(): void
    {
        User::factory()->create(['email' => 'zauzet@gimnazija.test']);

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.users.store'), $this->payload([
                'email' => 'zauzet@gimnazija.test',
                'role' => 'superadmin',
                'password' => 'kratka',
                'password_confirmation' => 'druga',
            ]))
            ->assertSessionHasErrors([
                'email' => 'Korisnik sa ovom email adresom već postoji.',
                'role',
                'password',
            ]);
    }

    public function test_update_keeps_password_when_empty_and_changes_it_when_given(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->author()->create(['password' => 'stara-lozinka-1']);

        $this->actingAs($admin)->put(route('admin.users.update', $user), $this->payload([
            'email' => $user->email, 'role' => 'editor', 'password' => '', 'password_confirmation' => '',
        ]))->assertRedirect();

        $user->refresh();
        $this->assertSame(Role::Editor, $user->role);
        $this->assertTrue(Hash::check('stara-lozinka-1', $user->password));

        $this->actingAs($admin)->put(route('admin.users.update', $user), $this->payload([
            'email' => $user->email, 'password' => 'nova-lozinka-12', 'password_confirmation' => 'nova-lozinka-12',
        ]));
        $this->assertTrue(Hash::check('nova-lozinka-12', $user->fresh()?->password));
    }

    public function test_admin_cannot_change_own_role_or_delete_themself(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->admin()->create(); // another admin exists

        $this->actingAs($admin)->put(route('admin.users.update', $admin), $this->payload([
            'email' => $admin->email, 'role' => 'author', 'password' => '', 'password_confirmation' => '',
        ]))->assertSessionHasErrors(['role' => 'Ne možete promijeniti svoju ulogu.']);
        $this->assertSame(Role::Admin, $admin->fresh()?->role);

        $this->actingAs($admin)->delete(route('admin.users.destroy', $admin))->assertForbidden();
        $this->assertModelExists($admin);
    }

    public function test_admin_can_change_another_admins_role(): void
    {
        $me = User::factory()->admin()->create();
        $other = User::factory()->admin()->create();

        $this->actingAs($me)->put(route('admin.users.update', $other), $this->payload([
            'email' => $other->email, 'role' => 'editor', 'password' => '', 'password_confirmation' => '',
        ]))->assertSessionDoesntHaveErrors();
        $this->assertSame(Role::Editor, $other->fresh()?->role);
    }

    public function test_delete_reassigns_content_to_another_user(): void
    {
        $admin = User::factory()->admin()->create();
        $leaving = User::factory()->author()->create();
        $post = Post::factory()->for($leaving, 'author')->create();
        $trashed = Post::factory()->for($leaving, 'author')->create();
        $trashed->delete();
        $page = Page::factory()->for($leaving, 'author')->create();
        $media = Media::factory()->create(['user_id' => $leaving->id]);

        $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $leaving), ['reassign_to' => $admin->id])
            ->assertRedirect(route('admin.users.index'));

        $this->assertModelMissing($leaving);
        $this->assertSame($admin->id, $post->fresh()?->user_id);
        $this->assertSame($admin->id, Post::withTrashed()->find($trashed->id)?->user_id);
        $this->assertSame($admin->id, $page->fresh()?->user_id);
        $this->assertSame($admin->id, $media->fresh()?->user_id);
    }

    public function test_delete_without_reassign_keeps_content_without_author(): void
    {
        $admin = User::factory()->admin()->create();
        $leaving = User::factory()->author()->create();
        $post = Post::factory()->for($leaving, 'author')->create();

        $this->actingAs($admin)->delete(route('admin.users.destroy', $leaving))->assertRedirect();

        $this->assertModelExists($post);
        $this->assertNull($post->fresh()?->user_id);
    }
}
