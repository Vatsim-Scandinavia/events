<?php

namespace Tests\Feature\Admin;

use App\Enums\IntegrationRunStatus;
use App\Integrations\Configuration;
use App\Integrations\ConnectionFailed;
use App\Integrations\Integrations;
use App\Integrations\RunSync;
use App\Integrations\SyncContext;
use App\Integrations\SyncResult;
use App\Models\Fir;
use App\Models\IntegrationRun;
use App\Models\IntegrationSetting;
use App\Models\User;
use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\Fakes\FakeIntegration;
use Tests\Fakes\FakeRosterSync;
use Tests\Fakes\OtherIntegration;
use Tests\TestCase;

class IntegrationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['integrations.registered' => [FakeIntegration::class, OtherIntegration::class], 'integrations.settings' => []]);
        FakeRosterSync::$roster = [];
        FakeRosterSync::$sync = null;
    }

    public function test_only_administrators_can_open_or_change_integrations(): void
    {
        $this->get('/admin/integrations')->assertRedirect('/auth/vatsim');

        $staff = User::factory()->staff()->create();
        $this->actingAs($staff)->get('/admin/integrations')->assertForbidden();
        $this->actingAs($staff)->get('/admin/integrations/fake')->assertForbidden();
        $this->actingAs($staff)->put('/admin/integrations/fake', $this->payload())->assertForbidden();
        $this->actingAs($staff)->post('/admin/integrations/fake/features/roster/sync')->assertForbidden();
        $this->assertDatabaseCount('integration_settings', 0);

        $this->actingAs(User::factory()->admin()->create())->get('/admin/integrations')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Integrations/Index')
            ->has('integrations', 2)
            ->where('integrations.0.name', 'Fake System')
            ->where('integrations.0.enabled', false)
        );
    }

    public function test_an_unknown_integration_is_not_found(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get('/admin/integrations/nope')->assertNotFound();
    }

    public function test_an_admin_can_switch_an_integration_and_its_features_on(): void
    {
        Queue::fake();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->put('/admin/integrations/fake', $this->payload(['features' => ['bookings']]))
            ->assertSessionHasNoErrors()
            ->assertRedirect('/admin/integrations/fake');

        $integrations = app(Integrations::class);
        $fake = $integrations->find('fake');
        $this->assertTrue($integrations->isEnabled($fake));
        $this->assertTrue($integrations->isOn($fake, $fake->feature('bookings')));
        $this->assertFalse($integrations->isOn($fake, $fake->feature('roster')));
        $this->assertSame('https://fake.example', $integrations->configuration($fake)->get('url'));
    }

    public function test_switching_on_fails_when_the_connection_test_fails(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->put('/admin/integrations/fake', $this->payload(['settings' => ['url' => FakeIntegration::DOWN_URL, 'token' => 'secret']]))
            ->assertSessionHasErrors(['enabled' => 'Couldn’t connect, so it stays off: The fake system did not answer.']);

        $this->assertDatabaseCount('integration_settings', 0);
    }

    public function test_switching_on_needs_every_required_setting(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->put('/admin/integrations/fake', $this->payload(['settings' => ['url' => 'https://fake.example', 'token' => '']]))
            ->assertSessionHasErrors('settings.token');

        $this->assertDatabaseCount('integration_settings', 0);
    }

    public function test_it_can_be_set_up_while_off_without_a_connection(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->put('/admin/integrations/fake', $this->payload(['enabled' => false, 'settings' => ['url' => FakeIntegration::DOWN_URL, 'token' => '']]))
            ->assertSessionHasNoErrors();

        $this->assertFalse(app(Integrations::class)->isEnabled(app(FakeIntegration::class)));
    }

    public function test_secrets_are_encrypted_never_sent_to_the_page_and_kept_when_left_empty(): void
    {
        Queue::fake();
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->put('/admin/integrations/fake', $this->payload());

        $stored = DB::table('integration_settings')->where('key', 'fake')->value('settings');
        $this->assertStringNotContainsString('s3cret-token', $stored);

        $this->actingAs($admin)->get('/admin/integrations/fake')->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Integrations/Edit')
            ->where('integration.settings.0.value', 'https://fake.example')
            ->where('integration.settings.1.value', null)
            ->where('integration.settings.1.isSet', true)
        );
        $this->assertStringNotContainsString('s3cret-token', $this->actingAs($admin)->get('/admin/integrations/fake')->getContent());

        $this->actingAs($admin)->put('/admin/integrations/fake', $this->payload(['settings' => ['url' => 'https://fake.example', 'token' => '']]))
            ->assertSessionHasNoErrors();

        $this->assertSame('s3cret-token', app(Integrations::class)->configuration(app(FakeIntegration::class))->get('token'));
    }

    public function test_a_setting_set_in_the_environment_is_locked(): void
    {
        Queue::fake();
        config(['integrations.settings.fake.url' => 'https://server.example']);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->put('/admin/integrations/fake', $this->payload(['settings' => ['url' => 'https://hijacked.example', 'token' => 'secret']]))
            ->assertSessionHasNoErrors();

        $this->assertSame('https://server.example', app(Integrations::class)->configuration(app(FakeIntegration::class))->get('url'));
        $this->actingAs($admin)->get('/admin/integrations/fake')->assertInertia(fn (Assert $page) => $page
            ->where('integration.settings.0.lockedBy', 'FAKE_URL')
            ->where('integration.settings.0.value', 'https://server.example')
            ->where('integration.settings.1.lockedBy', null)
        );
    }

    public function test_clashing_features_cannot_both_be_on(): void
    {
        Queue::fake();
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->put('/admin/integrations/other', ['enabled' => true, 'features' => ['bookings', 'roster'], 'removal_limit' => 25])
            ->assertSessionHasNoErrors();

        $this->actingAs($admin)->put('/admin/integrations/fake', $this->payload(['features' => ['bookings']]))
            ->assertSessionHasErrors(['features' => 'Booking push can’t be on at the same time as Other System’s Network bookings.']);
        // Two Controller sources clash without saying so.
        $this->actingAs($admin)->put('/admin/integrations/fake', $this->payload(['features' => ['roster']]))
            ->assertSessionHasErrors(['features' => 'Roster sync can’t be on at the same time as Other System’s Roster sync.']);

        $this->assertDatabaseMissing('integration_settings', ['key' => 'fake']);
    }

    public function test_switching_a_sync_on_runs_it_straight_away_and_sync_now_queues_it_again(): void
    {
        Queue::fake();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->put('/admin/integrations/fake', $this->payload(['features' => ['roster']]));
        Queue::assertPushed(RunSync::class, fn (RunSync $job) => $job->integration === 'fake' && $job->feature === 'roster' && $job->approvedBy === null);

        $this->actingAs($admin)->post('/admin/integrations/fake/features/roster/sync')->assertSessionHasNoErrors();
        Queue::assertPushed(RunSync::class, 2);

        $this->actingAs($admin)->post('/admin/integrations/fake/features/bookings/sync')->assertNotFound();
    }

    public function test_sync_now_needs_the_feature_on(): void
    {
        Queue::fake();

        $this->actingAs(User::factory()->admin()->create())
            ->post('/admin/integrations/fake/features/roster/sync')
            ->assertSessionHasErrors(['sync' => 'Switch Roster sync on first.']);

        Queue::assertNothingPushed();
    }

    public function test_each_sync_is_recorded_with_what_it_changed(): void
    {
        $this->switchOn(['roster']);
        FakeRosterSync::$sync = fn () => SyncResult::succeeded(added: 3, updated: 1, removed: 2, warnings: ['Area iceland has no FIR.']);

        RunSync::dispatchSync('fake', 'roster');

        $run = IntegrationRun::query()->latest('id')->first();
        $this->assertSame(IntegrationRunStatus::Succeeded, $run->status);
        $this->assertSame([3, 1, 2], [$run->added, $run->updated, $run->removed]);
        $this->assertSame(['Area iceland has no FIR.'], $run->warnings);
        $this->assertNotNull($run->finished_at);

        $this->actingAs(User::factory()->admin()->create())->get('/admin/integrations/fake')->assertInertia(fn (Assert $page) => $page
            ->where('runs.0.feature', 'Roster sync')
            ->where('runs.0.status', 'succeeded')
            ->where('runs.0.warnings', ['Area iceland has no FIR.'])
        );
    }

    public function test_a_failed_sync_changes_nothing(): void
    {
        $this->switchOn(['roster']);
        FakeRosterSync::$sync = function () {
            Fir::factory()->create(['icao' => 'EKDK']);

            throw new ConnectionFailed('The fake system returned 500.');
        };

        RunSync::dispatchSync('fake', 'roster');

        $this->assertDatabaseMissing('firs', ['icao' => 'EKDK']);
        $run = IntegrationRun::query()->latest('id')->first();
        $this->assertSame(IntegrationRunStatus::Failed, $run->status);
        $this->assertSame('The fake system returned 500.', $run->message);
    }

    public function test_an_unexpected_error_is_recorded_without_its_details(): void
    {
        $this->switchOn(['roster']);
        FakeRosterSync::$sync = fn () => throw new RuntimeException('token s3cret-token rejected');

        RunSync::dispatchSync('fake', 'roster');

        $run = IntegrationRun::query()->latest('id')->first();
        $this->assertSame(IntegrationRunStatus::Failed, $run->status);
        $this->assertStringNotContainsString('s3cret', $run->message);
    }

    public function test_a_sync_removing_more_than_the_limit_is_held_until_approved(): void
    {
        $this->switchOn(['roster'], removalLimit: 25);
        FakeRosterSync::$sync = function (SyncContext $context) {
            if (! $context->mayRemove(30, 40)) {
                Fir::factory()->create(['icao' => 'EKDK']);

                return SyncResult::held('It would remove 30 of 40 Controller roles.');
            }

            return SyncResult::succeeded(removed: 30);
        };

        RunSync::dispatchSync('fake', 'roster');

        $held = IntegrationRun::query()->latest('id')->first();
        $this->assertSame(IntegrationRunStatus::Held, $held->status);
        $this->assertSame('It would remove 30 of 40 Controller roles.', $held->message);
        $this->assertDatabaseMissing('firs', ['icao' => 'EKDK']);

        Queue::fake();
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get('/admin/integrations/fake')->assertInertia(fn (Assert $page) => $page->where('runs.0.canApprove', true));
        $this->actingAs($admin)->post("/admin/integrations/fake/runs/{$held->id}/approve")->assertSessionHasNoErrors();

        $this->assertSame(IntegrationRunStatus::Approved, $held->fresh()->status);
        Queue::assertPushed(RunSync::class, fn (RunSync $job) => $job->approvedBy === $admin->id);

        // Approving it once is all it takes.
        $this->actingAs($admin)->post("/admin/integrations/fake/runs/{$held->id}/approve")->assertSessionHasErrors('run');

        app()->call([new RunSync('fake', 'roster', $admin->id), 'handle']);
        $approved = IntegrationRun::query()->latest('id')->first();
        $this->assertSame(IntegrationRunStatus::Succeeded, $approved->status);
        $this->assertSame(30, $approved->removed);
        $this->assertTrue($approved->approver->is($admin));
    }

    public function test_the_removal_limit_allows_removing_up_to_its_share(): void
    {
        $context = new SyncContext(new Configuration([], [], removalLimit: 25));

        $this->assertTrue($context->mayRemove(10, 40));
        $this->assertFalse($context->mayRemove(11, 40));
        $this->assertTrue($context->mayRemove(5, 0));
    }

    public function test_a_run_of_another_integration_cannot_be_approved_here(): void
    {
        $this->switchOn(['roster']);
        $run = IntegrationRun::factory()->held()->create(['integration' => 'other', 'feature' => 'roster']);

        $this->actingAs(User::factory()->admin()->create())->post("/admin/integrations/fake/runs/{$run->id}/approve")->assertNotFound();
    }

    public function test_a_sync_switched_off_since_it_was_queued_does_nothing(): void
    {
        RunSync::dispatchSync('fake', 'roster');

        $this->assertDatabaseCount('integration_runs', 0);
    }

    public function test_runs_older_than_90_days_are_pruned(): void
    {
        $old = IntegrationRun::factory()->create(['started_at' => now()->subDays(91)]);
        $recent = IntegrationRun::factory()->create(['started_at' => now()->subDays(89)]);

        $this->artisan('model:prune', ['--model' => [IntegrationRun::class]])->assertSuccessful();

        $this->assertModelMissing($old);
        $this->assertModelExists($recent);
    }

    public function test_syncs_are_scheduled_and_only_run_while_their_feature_is_on(): void
    {
        $schedule = new Schedule;
        app(Integrations::class)->schedule($schedule);

        $events = collect($schedule->events())->keyBy(fn (CallbackEvent $event) => $event->description);
        $this->assertArrayHasKey('integrations:fake.roster', $events->all());
        $this->assertArrayNotHasKey('integrations:fake.bookings', $events->all());
        $this->assertSame('0 * * * *', $events['integrations:fake.roster']->expression);

        $this->assertFalse($events['integrations:fake.roster']->filtersPass(app()));
        $this->switchOn(['roster']);
        $this->assertTrue($events['integrations:fake.roster']->filtersPass(app()));
    }

    /**
     * @param  list<string>  $features
     */
    private function switchOn(array $features, int $removalLimit = 25): void
    {
        IntegrationSetting::query()->create([
            'key' => 'fake',
            'enabled' => true,
            'features' => $features,
            'settings' => ['url' => 'https://fake.example', 'token' => 's3cret-token'],
            'removal_limit' => $removalLimit,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'enabled' => true,
            'features' => [],
            'removal_limit' => 25,
            'settings' => ['url' => 'https://fake.example', 'token' => 's3cret-token'],
            ...$overrides,
        ];
    }
}
