<?php

namespace Tests\Feature;

use App\Auth\Controllers;
use App\Auth\RoleAssignments;
use App\Auth\Roles;
use App\Auth\Where;
use App\Enums\ControllerRating;
use App\Enums\SignInProvider;
use App\Jobs\SyncMemberDetails;
use App\Models\ControllerGrant;
use App\Models\SignInToken;
use App\Models\User;
use GuzzleHttp\Exception\TransferException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class SyncMemberDetailsTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, mixed> What /api/user answers per access token: a member, or an HTTP status. */
    private array $apiUser = [];

    /** @var list<array<string, mixed>|int> What the token endpoint answers next, in turn. */
    private array $refreshes = [];

    protected function setUp(): void
    {
        parent::setUp();

        config(['session.driver' => 'database']);

        Http::fake(function (Request $request) {
            if (str_ends_with($request->url(), '/api/user')) {
                $member = $this->apiUser[str_replace('Bearer ', '', $request->header('Authorization')[0] ?? '')] ?? 401;

                return $this->answer(is_array($member) ? ['data' => $member] : $member);
            }

            if (str_ends_with($request->url(), '/oauth/token')) {
                return $this->answer(array_shift($this->refreshes) ?? 500);
            }

            return null;
        });
    }

    public function test_the_daily_command_queues_a_sync_for_each_account_with_tokens(): void
    {
        Queue::fake();
        $withTokens = User::factory()->count(2)->create()->each(fn (User $user) => $this->giveTokens($user, "access-{$user->id}"));
        User::factory()->create();

        $this->artisan('users:sync-vatsim')->expectsOutput('Queued a sync for 2 accounts.')->assertSuccessful();

        Queue::assertPushed(SyncMemberDetails::class, 2);
        foreach ($withTokens as $user) {
            Queue::assertPushed(SyncMemberDetails::class, fn (SyncMemberDetails $job) => $job->userId === $user->id);
        }
    }

    public function test_an_account_gets_its_full_details_from_the_sign_in_provider(): void
    {
        $member = User::factory()->rated(ControllerRating::S1)->create(['vatsim_cid' => 1000001, 'name' => 'Old Name', 'email' => 'old@example.com', 'subdivision' => 'SCA']);
        $this->giveTokens($member, 'access-1');
        $this->apiUser = ['access-1' => $this->apiMember(1000001, 'New Name', ControllerRating::S3, null)];

        $this->sync($member);

        $member->refresh();
        $this->assertSame(['New Name', 'New', '1000001@example.com', ControllerRating::S3, null], [$member->name, $member->first_name, $member->email, $member->rating, $member->subdivision]);
        $this->assertNotNull($member->details_synced_at);
    }

    public function test_an_expired_token_is_refreshed_first_and_a_missing_refresh_token_keeps_the_old_one(): void
    {
        $member = User::factory()->create(['vatsim_cid' => 1000001]);
        $this->giveTokens($member, 'access-1', expiresAt: now()->subDay());
        $this->refreshes = [['expires_in' => 604800, 'access_token' => 'access-2']];
        $this->apiUser = ['access-2' => $this->apiMember(1000001, 'Kim', ControllerRating::S2, 'SCA')];

        $this->sync($member);

        $token = SignInToken::query()->sole();
        $this->assertSame(['access-2', 'refresh-for-access-1'], [$token->access_token, $token->refresh_token]);
        $this->assertTrue($token->expires_at->isAfter(now()->addDays(6)));
        $this->assertSame('Kim', $member->fresh()->name);
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/oauth/token') && $request['grant_type'] === 'refresh_token' && $request['refresh_token'] === 'refresh-for-access-1');
    }

    public function test_a_token_turned_down_is_refreshed_once_and_dropped_if_turned_down_again(): void
    {
        $member = User::factory()->create(['vatsim_cid' => 1000001, 'name' => 'Kim']);
        $this->giveTokens($member, 'access-1');
        $this->refreshes = [['expires_in' => 604800, 'access_token' => 'access-2', 'refresh_token' => 'refresh-2']];

        $this->sync($member);

        $this->assertSame(0, SignInToken::query()->count());
        $this->assertSame('Kim', $member->fresh()->name);
        $this->assertNull($member->fresh()->details_synced_at);
    }

    public function test_a_refused_refresh_drops_the_token_and_leaves_the_account_alone(): void
    {
        $member = User::factory()->rated(ControllerRating::S2)->create(['vatsim_cid' => 1000001]);
        $this->giveTokens($member, 'access-1', expiresAt: now()->subDay());
        // Connect answers a revoked refresh token with 401 invalid_request.
        $this->refreshes = [401];

        $this->sync($member);

        $this->assertSame(0, SignInToken::query()->count());
        $this->assertSame(ControllerRating::S2, $member->fresh()->rating);
    }

    #[TestWith(['refresh', 503])]
    #[TestWith(['refresh', 429])]
    #[TestWith(['api/user', 503])]
    #[TestWith(['api/user', 429])]
    public function test_an_outage_or_a_rate_limit_is_retried_later_and_keeps_the_token(string $failing, int $status): void
    {
        $member = User::factory()->create(['vatsim_cid' => 1000001]);
        $this->giveTokens($member, 'access-1', expiresAt: $failing === 'refresh' ? now()->subDay() : null);
        $this->refreshes = [$status];
        $this->apiUser = ['access-1' => $status];

        try {
            $this->sync($member);
            $this->fail('The job should fail, so the queue retries it.');
        } catch (TransferException) {
            // Retried with a backoff by the queue.
        }

        $this->assertSame('access-1', SignInToken::query()->sole()->access_token);
    }

    public function test_tokens_from_the_other_provider_are_dropped(): void
    {
        $member = User::factory()->create(['vatsim_cid' => 1000001]);
        $this->giveTokens($member, 'access-1', provider: SignInProvider::Handover);

        $this->sync($member);

        $this->assertSame(0, SignInToken::query()->count());
        Http::assertNothingSent();
    }

    public function test_details_for_another_member_or_with_something_withheld_are_ignored(): void
    {
        $member = User::factory()->create(['vatsim_cid' => 1000001, 'name' => 'Kim']);
        $this->giveTokens($member, 'access-1');
        $this->apiUser = ['access-1' => $this->apiMember(1000002, 'Someone Else', ControllerRating::S2, 'SCA')];

        $this->sync($member);
        $this->assertSame('Kim', $member->fresh()->name);

        $this->apiUser = ['access-1' => [...$this->apiMember(1000001, 'New Name', ControllerRating::S2, 'SCA'), 'oauth' => ['token_valid' => 'false']]];

        $this->sync($member);
        $this->assertSame('Kim', $member->fresh()->name);
    }

    public function test_a_suspended_member_is_signed_out_but_keeps_their_roles_and_grants(): void
    {
        $member = User::factory()->rated(ControllerRating::S3)->create(['vatsim_cid' => 1000001, 'subdivision' => 'SCA']);
        $this->giveTokens($member, 'access-1');
        app(RoleAssignments::class)->give($member, Roles::EVENT_STAFF);
        ControllerGrant::factory()->for($member)->create();
        DB::table('sessions')->insert(['id' => 'their-session', 'user_id' => $member->id, 'payload' => '', 'last_activity' => now()->timestamp]);
        DB::table('sessions')->insert(['id' => 'someone-else', 'user_id' => null, 'payload' => '', 'last_activity' => now()->timestamp]);
        $this->apiUser = ['access-1' => $this->apiMember(1000001, 'Kim', ControllerRating::Suspended, 'SCA')];

        $this->sync($member);

        $this->assertSame(['someone-else'], DB::table('sessions')->pluck('id')->all());
        $this->assertSame(ControllerRating::Suspended, $member->fresh()->rating);
        $this->assertSame(1, $member->roleAssignments()->count());
        $this->assertTrue(app(Controllers::class)->isController($member->fresh(), Where::hub()));
    }

    private function sync(User $user): void
    {
        (new SyncMemberDetails($user->id))->handle();
    }

    private function giveTokens(User $user, string $accessToken, mixed $expiresAt = null, SignInProvider $provider = SignInProvider::Vatsim): void
    {
        SignInToken::query()->create([
            'user_id' => $user->id,
            'provider' => $provider,
            'access_token' => $accessToken,
            'refresh_token' => "refresh-for-{$accessToken}",
            'expires_at' => $expiresAt ?? now()->addDays(3),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function apiMember(int $cid, string $name, ControllerRating $rating, ?string $subdivision): array
    {
        return [
            'cid' => (string) $cid,
            'personal' => ['name_first' => strtok($name, ' '), 'name_full' => $name, 'email' => "{$cid}@example.com"],
            'vatsim' => ['rating' => ['id' => $rating->value], 'subdivision' => ['id' => $subdivision]],
            'oauth' => ['token_valid' => 'true'],
        ];
    }

    /**
     * @param  array<string, mixed>|int  $answer  A JSON body, or an HTTP status to fail with.
     */
    private function answer(array|int $answer): mixed
    {
        return is_int($answer) ? Http::response(['error' => 'refused'], $answer) : Http::response($answer);
    }
}
