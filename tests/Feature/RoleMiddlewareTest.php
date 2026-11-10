<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RoleMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'auth', 'role:admin'])->get('/_test/admin-only', fn () => 'ok');
        Route::middleware(['web', 'auth', 'role:admin,editor'])->get('/_test/admin-or-editor', fn () => 'ok');
    }

    public function test_new_users_default_to_author(): void
    {
        $user = User::factory()->create();

        $this->assertSame(Role::Author, $user->fresh()->role);
    }

    public function test_admin_passes_admin_only_route(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get('/_test/admin-only')
            ->assertOk();
    }

    public function test_editor_and_author_are_blocked_from_admin_only_route(): void
    {
        $this->actingAs(User::factory()->editor()->create())
            ->get('/_test/admin-only')
            ->assertForbidden();

        $this->actingAs(User::factory()->author()->create())
            ->get('/_test/admin-only')
            ->assertForbidden();
    }

    public function test_multiple_roles_are_accepted(): void
    {
        $this->actingAs(User::factory()->editor()->create())
            ->get('/_test/admin-or-editor')
            ->assertOk();

        $this->actingAs(User::factory()->author()->create())
            ->get('/_test/admin-or-editor')
            ->assertForbidden();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/_test/admin-only')->assertRedirect('/login');
    }

    public function test_public_registration_is_disabled(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register', [])->assertNotFound();
    }
}
