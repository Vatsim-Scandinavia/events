<?php

namespace Tests\Feature\Admin;

use App\Models\Event;
use App\Models\Fir;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FirManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_people_who_manage_settings_can_open_the_fir_pages(): void
    {
        $fir = Fir::factory()->create(['icao' => 'EKDK', 'name' => 'Copenhagen']);
        Event::factory()->count(2)->for($fir)->create();

        $this->get('/admin/firs')->assertRedirect('/auth/vatsim');
        $this->actingAs(User::factory()->staff()->create())->get('/admin/firs')->assertForbidden();

        $this->actingAs(User::factory()->admin()->create())->get('/admin/firs')->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Firs/Index')
            ->has('firs', 1)
            ->where('firs.0.icao', 'EKDK')
            ->where('firs.0.name', 'Copenhagen')
            ->where('firs.0.events', 2)
        );
    }

    public function test_an_admin_can_add_a_fir_typed_in_any_case(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post('/admin/firs', ['icao' => ' enor ', 'name' => 'Polaris'])
            ->assertRedirect('/admin/firs');

        $this->assertDatabaseHas('firs', ['icao' => 'ENOR', 'name' => 'Polaris']);
    }

    public function test_an_admin_can_rename_a_fir(): void
    {
        $fir = Fir::factory()->create(['icao' => 'EKDK', 'name' => 'EKDK']);

        $this->actingAs(User::factory()->admin()->create())
            ->put("/admin/firs/{$fir->id}", ['icao' => 'EKDK', 'name' => 'Copenhagen'])
            ->assertRedirect('/admin/firs')
            ->assertSessionHasNoErrors();

        $this->assertSame('Copenhagen', $fir->fresh()->name);
    }

    /**
     * @return array<string, array{array<string, string>, string, string}>
     */
    public static function invalidFirs(): array
    {
        return [
            'a missing code' => [['icao' => '', 'name' => 'Copenhagen'], 'icao', 'Enter the FIR’s ICAO code.'],
            'a code that is not ICAO' => [['icao' => 'EK1', 'name' => 'Copenhagen'], 'icao', 'Use a four-letter ICAO code, like EKDK.'],
            'a code another FIR has' => [['icao' => 'esaa', 'name' => 'Sweden'], 'icao', 'There’s already a FIR with this code.'],
            'a missing name' => [['icao' => 'EKDK', 'name' => ''], 'name', 'Enter the FIR’s name.'],
        ];
    }

    /**
     * @param  array<string, string>  $payload
     */
    #[DataProvider('invalidFirs')]
    public function test_a_fir_needs_a_unique_icao_code_and_a_name(array $payload, string $field, string $message): void
    {
        Fir::factory()->create(['icao' => 'ESAA']);

        $this->actingAs(User::factory()->admin()->create())
            ->post('/admin/firs', $payload)
            ->assertSessionHasErrors([$field => $message]);

        $this->assertDatabaseCount('firs', 1);
    }

    public function test_a_fir_without_events_can_be_deleted(): void
    {
        $fir = Fir::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->delete("/admin/firs/{$fir->id}")
            ->assertRedirect('/admin/firs');

        $this->assertModelMissing($fir);
    }

    public function test_a_fir_that_runs_events_cannot_be_deleted(): void
    {
        $fir = Fir::factory()->create(['icao' => 'EKDK']);
        Event::factory()->count(2)->for($fir)->create();

        $this->actingAs(User::factory()->admin()->create())
            ->delete("/admin/firs/{$fir->id}")
            ->assertSessionHasErrors(['fir' => 'EKDK runs 2 events. Move them to another FIR first.']);

        $this->assertModelExists($fir);
    }

    public function test_the_site_lists_the_firs_as_they_change(): void
    {
        $admin = User::factory()->admin()->create();
        $fir = Fir::factory()->create(['icao' => 'EKDK', 'name' => 'Copenhagen']);

        $this->get('/')->assertInertia(fn (Assert $page) => $page->where('vacc.firs', [['icao' => 'EKDK', 'name' => 'Copenhagen']]));

        $this->actingAs($admin)->post('/admin/firs', ['icao' => 'ENOR', 'name' => 'Polaris']);
        $this->actingAs($admin)->delete("/admin/firs/{$fir->id}");

        $this->get('/')->assertInertia(fn (Assert $page) => $page->where('vacc.firs', [['icao' => 'ENOR', 'name' => 'Polaris']]));
    }
}
