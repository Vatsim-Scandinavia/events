<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeding_gives_people_to_sign_in_as_and_published_events_to_see(): void
    {
        $this->seed();

        $this->assertTrue(User::query()->where('name', 'Hub Admin')->firstOrFail()->isAdministrator());
        $this->assertSame(6, Event::query()->published()->count());
        $this->assertSame(['Winter Wonderland Fly-in'], Event::query()->whereNull('published_at')->pluck('name')->all());

        $nightOps = Event::query()->where('name', 'Scandinavian Night Ops')->firstOrFail();
        $sections = $nightOps->sectionsFor($positions = $nightOps->positions()->get());
        $this->assertSame(
            ['Copenhagen', 'Copenhagen', 'Copenhagen', 'Stockholm', 'Oslo', 'Helsinki'],
            $positions->map(fn ($position) => $sections[$position->id]?->label)->all(),
        );
    }
}
