<?php

namespace Tests\Feature\Admin;

use App\Models\AlumniTestimonial;
use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AlumniTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admins_manage_alumni(): void
    {
        $item = AlumniTestimonial::factory()->create();

        foreach ([User::factory()->editor()->create(), User::factory()->author()->create()] as $user) {
            $this->actingAs($user)->get(route('admin.alumni.index'))->assertForbidden();
            $this->actingAs($user)->post(route('admin.alumni.store'), ['name' => 'X', 'quote' => 'Y'])->assertForbidden();
            $this->actingAs($user)->delete(route('admin.alumni.destroy', $item))->assertForbidden();
        }

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.alumni.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('admin/alumni/Index')->has('testimonials', 1));
    }

    public function test_create_appends_to_the_end_with_photo(): void
    {
        $admin = User::factory()->admin()->create();
        AlumniTestimonial::factory()->create(['sort_order' => 5]);
        $photo = Media::factory()->create();

        $this->actingAs($admin)->post(route('admin.alumni.store'), [
            'name' => 'Ana Anić', 'generation' => 2015, 'profession' => 'ljekarka',
            'quote' => 'Profesori su me naučili da razmišljam svojom glavom.', 'photo_media_id' => $photo->id, 'is_published' => true,
        ])->assertRedirect();

        $created = AlumniTestimonial::query()->where('name', 'Ana Anić')->sole();
        $this->assertSame(6, $created->sort_order);
        $this->assertSame($photo->id, $created->photo_media_id);
        $this->assertTrue($photo->isInUse());
    }

    public function test_validation(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.alumni.store'), ['name' => '', 'quote' => '', 'generation' => 1800])
            ->assertSessionHasErrors(['name', 'quote', 'generation']);
    }

    public function test_reorder_and_update(): void
    {
        $admin = User::factory()->admin()->create();
        [$a, $b, $c] = AlumniTestimonial::factory()->count(3)->sequence(['sort_order' => 0], ['sort_order' => 1], ['sort_order' => 2])->create();

        $this->actingAs($admin)->put(route('admin.alumni.reorder'), ['ids' => [$c->id, $a->id, $b->id]])->assertRedirect();
        $this->assertSame([$c->id, $a->id, $b->id], AlumniTestimonial::query()->published()->pluck('id')->all());

        $this->actingAs($admin)->put(route('admin.alumni.update', $a), ['name' => 'Novo ime', 'quote' => 'Q', 'is_published' => false]);
        $this->assertSame('Novo ime', $a->fresh()?->name);
        $this->assertSame([$c->id, $b->id], AlumniTestimonial::query()->published()->pluck('id')->all());
    }
}
