<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_sent_to_sign_in(): void
    {
        $this->get('/settings')->assertRedirect('/auth/vatsim');
        $this->put('/settings', ['timezone' => 'Europe/Copenhagen'])->assertRedirect('/auth/vatsim');
    }

    public function test_the_page_shows_zulu_until_a_time_zone_is_picked(): void
    {
        $this->actingAs(User::factory()->create())->get('/settings')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Settings/Edit')
            ->where('settings.timezone', 'UTC')
            ->has('timezones')
        );

        $this->actingAs(User::factory()->withTimezone('Europe/Copenhagen')->create())->get('/settings')
            ->assertInertia(fn (Assert $page) => $page->where('settings.timezone', 'Europe/Copenhagen'));
    }

    public function test_a_user_can_pick_a_time_zone(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->from('/settings')->put('/settings', ['timezone' => 'Europe/Copenhagen'])
            ->assertRedirect('/settings');

        $this->assertSame('Europe/Copenhagen', $user->fresh()->timezone);
    }

    public function test_picking_utc_goes_back_to_the_default(): void
    {
        $user = User::factory()->withTimezone('Europe/Copenhagen')->create();

        $this->actingAs($user)->put('/settings', ['timezone' => 'UTC'])->assertSessionHasNoErrors();

        $this->assertNull($user->fresh()->timezone);
    }

    public function test_a_time_zone_must_come_from_the_list(): void
    {
        $user = User::factory()->withTimezone('Europe/Copenhagen')->create();

        $this->actingAs($user)->put('/settings', ['timezone' => 'Mars/Olympus_Mons'])
            ->assertSessionHasErrors(['timezone' => 'Pick a time zone from the list.']);

        $this->assertSame('Europe/Copenhagen', $user->fresh()->timezone);
    }
}
