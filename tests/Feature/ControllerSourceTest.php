<?php

namespace Tests\Feature;

use App\Auth\Roles;
use App\Enums\AssignmentSource;
use App\Enums\ControllerRating;
use App\Models\IntegrationSetting;
use App\Models\Role;
use App\Models\User;
use App\Services\VaccIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\Fakes\FakeIntegration;
use Tests\Fakes\FakeRosterSync;
use Tests\TestCase;

/**
 * An Integration feature taking over the Controller role from the hub's S1-and-subdivision rule.
 */
class ControllerSourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['vacc.subdivision' => null, 'integrations.registered' => [FakeIntegration::class], 'integrations.settings' => []]);
        FakeRosterSync::$roster = [];
        FakeRosterSync::$sync = null;

        $identity = app(VaccIdentity::class);
        $identity->save(['subdivision' => 'SCA']);
        $identity->setControllerRoleAutomatic(true);
    }

    public function test_while_a_source_is_in_charge_the_hubs_rule_gives_nothing_at_sign_in(): void
    {
        $this->switchSourceOn();
        FakeRosterSync::$roster = [1000002];

        $this->signIn(1000001, ControllerRating::S2, 'SCA');
        $this->signIn(1000002, ControllerRating::OBS, 'GER');

        $this->assertFalse($this->holdsController(1000001, AssignmentSource::Automatic));
        $this->assertTrue($this->holdsController(1000002, AssignmentSource::Automatic));
    }

    public function test_the_settings_page_says_which_source_hands_out_the_role(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get('/admin/settings')->assertInertia(fn (Assert $page) => $page->where('controllerSource', null));

        $this->switchSourceOn();

        $this->actingAs($admin)->get('/admin/settings')->assertInertia(fn (Assert $page) => $page->where('controllerSource', 'Fake System roster sync'));
    }

    public function test_switching_the_hubs_rule_off_leaves_the_sources_roles_alone(): void
    {
        $this->switchSourceOn();
        $controller = User::factory()->create(['vatsim_cid' => 1000001]);
        $controller->assignRole(Roles::CONTROLLER, null, AssignmentSource::Automatic);

        $this->actingAs(User::factory()->admin()->create())
            ->post('/admin/settings', ['name' => 'Hub', 'subdivision' => 'SCA', 'auto_controller' => false])
            ->assertSessionHasNoErrors();

        $this->assertTrue($this->holdsController(1000001, AssignmentSource::Automatic));
    }

    public function test_switching_the_source_off_hands_its_roles_to_admins_and_the_hubs_rule_takes_over(): void
    {
        Queue::fake();
        $this->switchSourceOn();
        $fromSource = User::factory()->create(['vatsim_cid' => 1000001, 'subdivision' => 'GER']);
        $fromSource->assignRole(Roles::CONTROLLER, null, AssignmentSource::Automatic);
        User::factory()->rated(ControllerRating::S2)->create(['vatsim_cid' => 1000002, 'subdivision' => 'SCA']);

        $this->actingAs(User::factory()->admin()->create())
            ->put('/admin/integrations/fake', [
                'enabled' => true,
                'features' => [],
                'removal_limit' => 25,
                'settings' => ['url' => 'https://fake.example', 'token' => ''],
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue($this->holdsController(1000001, AssignmentSource::Manual));
        $this->assertTrue($this->holdsController(1000002, AssignmentSource::Automatic));
    }

    public function test_the_vatsim_sync_steps_aside_for_a_source(): void
    {
        Http::fake();
        $this->switchSourceOn();
        User::factory()->rated(ControllerRating::S2)->create(['subdivision' => 'SCA']);

        $this->artisan('users:sync-vatsim')
            ->expectsOutput('The Controller role comes from Fake System roster sync; nothing to sync.')
            ->assertSuccessful();

        Http::assertNothingSent();
    }

    private function switchSourceOn(): void
    {
        IntegrationSetting::query()->create([
            'key' => 'fake',
            'enabled' => true,
            'features' => ['roster'],
            'settings' => ['url' => 'https://fake.example', 'token' => 's3cret-token'],
        ]);
    }

    private function signIn(int $cid, ControllerRating $rating, string $subdivision): void
    {
        Socialite::fake('vatsim', (new SocialiteUser)
            ->setRaw([
                'cid' => (string) $cid,
                'personal' => ['name_full' => 'Kim Controller', 'email' => "{$cid}@example.com"],
                'vatsim' => ['rating' => ['id' => $rating->value], 'subdivision' => ['id' => $subdivision]],
            ])
            ->map(['id' => (string) $cid, 'name' => 'Kim Controller', 'email' => "{$cid}@example.com"]));

        $this->get('/auth/vatsim/callback?code=code&state=state')->assertRedirect();
        auth()->logout();
    }

    private function holdsController(int $cid, AssignmentSource $source): bool
    {
        return User::query()->where('vatsim_cid', $cid)->sole()->roleAssignments()
            ->where('role_id', Role::findByName(Roles::CONTROLLER)->id)
            ->whereNull('fir_id')
            ->where('source', $source)
            ->exists();
    }
}
