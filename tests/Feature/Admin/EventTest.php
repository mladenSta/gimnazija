<?php

namespace Tests\Feature\Admin;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EventTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_role_can_manage_events(): void
    {
        foreach ([User::factory()->admin()->create(), User::factory()->editor()->create(), User::factory()->author()->create()] as $user) {
            $this->actingAs($user)->get(route('admin.events.index'))->assertOk();
            $this->actingAs($user)->post(route('admin.events.store'), [
                'title' => "Takmičenje {$user->id}", 'date' => today()->addWeek()->format('Y-m-d'), 'is_published' => true,
            ])->assertRedirect();
        }

        $this->assertSame(3, Event::query()->count());
    }

    public function test_create_with_all_fields_and_display_format(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('admin.events.store'), [
            'title' => 'Premijera predstave',
            'short_description' => 'Dramska sekcija',
            'date' => '2026-11-25',
            'time' => '18:00',
            'location' => 'Svečana sala',
            'label' => 'Sekcije',
            'is_published' => true,
        ]);

        $this->travelTo('2026-11-10');

        $this->actingAs($user)->get(route('admin.events.index'))
            ->assertInertia(fn (Assert $p) => $p->component('admin/events/Index')
                ->where('events.data.0.date_display', '25.11.2026.')
                ->where('events.data.0.time', '18:00')
                ->where('events.data.0.label', 'Sekcije')
                ->where('labels', ['Sekcije']));
    }

    public function test_upcoming_and_past_tabs(): void
    {
        Event::factory()->create(['title' => 'Uskoro', 'date' => today()->addDay()]);
        Event::factory()->create(['title' => 'Danas', 'date' => today()]);
        Event::factory()->create(['title' => 'Prošlo', 'date' => today()->subDay()]);

        $user = User::factory()->create();

        $this->actingAs($user)->get(route('admin.events.index'))
            ->assertInertia(fn (Assert $p) => $p->where('tab', 'upcoming')->has('events.data', 2)
                ->where('events.data.0.title', 'Danas')->where('counts.past', 1)->has('events.meta.links'));

        $this->actingAs($user)->get(route('admin.events.index', ['tab' => 'past']))
            ->assertInertia(fn (Assert $p) => $p->has('events.data', 1)->where('events.data.0.title', 'Prošlo'));
    }

    public function test_validation_update_and_delete(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create();

        $this->actingAs($user)->post(route('admin.events.store'), ['title' => '', 'date' => '25.11.2026'])
            ->assertSessionHasErrors(['title' => 'Polje naslov je obavezno.', 'date']);

        $this->actingAs($user)->put(route('admin.events.update', $event), [
            'title' => 'Izmijenjeno', 'date' => '2026-12-05', 'time' => null, 'is_published' => false,
        ])->assertRedirect();
        $this->assertSame('Izmijenjeno', $event->fresh()?->title);
        $this->assertFalse($event->fresh()?->is_published);

        $this->actingAs($user)->delete(route('admin.events.destroy', $event))->assertRedirect();
        $this->assertModelMissing($event);
    }
}
