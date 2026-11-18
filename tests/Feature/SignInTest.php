<?php

namespace Tests\Feature;

use App\Auth\OAuthRequestFailed;
use App\Auth\SignInTokens;
use App\Enums\ControllerRating;
use App\Enums\NameDisplay;
use App\Enums\SignInProvider;
use App\Models\SignInToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\Concerns\SignsInThroughTheProvider;
use Tests\TestCase;

class SignInTest extends TestCase
{
    use RefreshDatabase;
    use SignsInThroughTheProvider;

    /**
     * @return array<string, array{string, string}>
     */
    public static function providers(): array
    {
        return [
            'VATSIM Connect' => ['vatsim', 'VATSIM'],
            'Handover' => ['handover', 'VATSIM Scandinavia'],
        ];
    }

    #[TestWith(['vatsim', 'https://auth.vatsim.net', 'handover'])]
    #[TestWith(['handover', 'https://handover.vatsim-scandinavia.org', 'vatsim'])]
    public function test_only_the_configured_provider_answers(string $configured, string $server, string $other): void
    {
        config(['auth.sign_in_provider' => $configured]);

        $this->assertStringStartsWith("{$server}/oauth/authorize?", $this->get("/auth/{$configured}")->headers->get('Location'));
        $this->get("/auth/{$other}")->assertNotFound();
        $this->get("/auth/{$other}/callback?code=code&state=state")->assertNotFound();
        $this->get('/auth/unknown')->assertNotFound();
    }

    #[TestWith(['vatsim', 'https://auth-dev.vatsim.net/', 'https://auth-dev.vatsim.net'])]
    #[TestWith(['handover', 'https://handover.example.org/', 'https://handover.example.org'])]
    public function test_sign_in_asks_the_configured_server_for_what_we_need_with_state_and_pkce(string $provider, string $baseUrl, string $server): void
    {
        config(['auth.sign_in_provider' => $provider, "services.{$provider}.client_id" => 'client-id', "services.{$provider}.base_url" => $baseUrl]);

        $location = $this->get("/auth/{$provider}")->assertRedirect()->headers->get('Location');

        $this->assertStringStartsWith("{$server}/oauth/authorize?", $location);
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        $this->assertSame('client-id', $query['client_id']);
        $this->assertSame('full_name vatsim_details email', $query['scope']);
        $this->assertSame(url("/auth/{$provider}/callback"), $query['redirect_uri']);
        $this->assertSame(session('sign_in.state'), $query['state']);
        $this->assertSame('S256', $query['code_challenge_method']);
        $this->assertArrayNotHasKey('prompt', $query);
    }

    #[DataProvider('providers')]
    public function test_pages_show_the_configured_provider_on_the_sign_in_button(string $provider, string $label): void
    {
        config(['auth.sign_in_provider' => $provider]);

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('auth.signIn.url', "/auth/{$provider}")
            ->where('auth.signIn.label', "Sign in with {$label}"));
    }

    #[DataProvider('providers')]
    public function test_first_sign_in_creates_the_account_from_their_member_details(string $provider): void
    {
        config(['auth.sign_in_provider' => $provider]);

        $this->signInThroughProvider(self::connectMember(1234567, ControllerRating::S2, 'SCA', name: 'Mary Ann Controller', firstName: 'Mary Ann', email: 'jane@example.com'))
            ->assertRedirect('/');

        $user = User::query()->sole();
        $this->assertSame([1234567, 'Mary Ann Controller', 'Mary Ann', 'jane@example.com', ControllerRating::S2, 'SCA'], [$user->vatsim_cid, $user->name, $user->first_name, $user->email, $user->rating, $user->subdivision]);
        // Others see only the first name until they choose otherwise.
        $this->assertSame(NameDisplay::FirstName, $user->name_display);
        $this->assertAuthenticatedAs($user);
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/oauth/token') && $request['code'] === 'the-code' && strlen($request['code_verifier']) === 43);
    }

    public function test_handover_sends_the_cid_as_a_number(): void
    {
        config(['auth.sign_in_provider' => 'handover']);

        $this->signInThroughProvider([...self::connectMember(1234567), 'cid' => 1234567])->assertRedirect('/');

        $this->assertSame(1234567, User::query()->sole()->vatsim_cid);
    }

    public function test_every_sign_in_brings_the_account_up_to_date_and_sends_them_where_they_were_going(): void
    {
        $user = User::factory()->create(['vatsim_cid' => 1234567, 'name' => 'Old Name', 'rating' => ControllerRating::S1, 'subdivision' => 'SCA']);

        $this->get('/admin/users')->assertRedirect('/auth/vatsim');
        $this->signInThroughProvider(self::connectMember(1234567, ControllerRating::C1, null, name: 'New Name'))->assertRedirect('/admin/users');

        $this->assertSame(1, User::query()->count());
        $user->refresh();
        $this->assertSame(['New Name', ControllerRating::C1, null], [$user->name, $user->rating, $user->subdivision]);
    }

    public function test_a_missing_name_and_email_are_not_needed(): void
    {
        $member = self::connectMember(1234567);
        unset($member['personal']);

        $this->signInThroughProvider($member)->assertRedirect('/');

        $this->assertSame(['VATSIM 1234567', null], [User::query()->sole()->name, User::query()->sole()->email]);
    }

    public function test_signing_in_from_a_page_returns_you_to_it(): void
    {
        $this->signInThroughProvider(self::connectMember(1234567), returnTo: '/events/15?occurrence=3')->assertRedirect('/events/15?occurrence=3');
    }

    #[TestWith(['https://evil.example/phish'])]
    #[TestWith(['//evil.example/phish'])]
    #[TestWith(['/\\evil.example/phish'])]
    #[TestWith(["/\t/evil.example/phish"])]
    #[TestWith(["/events\n/15"])]
    #[TestWith(["/events\x00/15"])]
    #[TestWith(["/events\x7F/15"])]
    public function test_a_return_address_off_this_site_or_with_control_characters_is_ignored(string $returnTo): void
    {
        $this->signInThroughProvider(self::connectMember(1234567), returnTo: $returnTo)->assertRedirect('/');
    }

    public function test_the_tokens_are_kept_encrypted_and_never_in_the_session(): void
    {
        $this->signInThroughProvider(self::connectMember(1234567));

        $token = SignInToken::query()->sole();
        $this->assertSame(['access-token', 'refresh-token', SignInProvider::Vatsim], [$token->access_token, $token->refresh_token, $token->provider]);
        $stored = DB::table('sign_in_tokens')->sole();
        $this->assertNotSame('access-token', $stored->access_token);
        $this->assertNotSame('refresh-token', $stored->refresh_token);
        $this->assertStringNotContainsString('access-token', serialize(session()->all()));
        $this->assertNull(session('sign_in.state'));
        $this->assertNull(session('sign_in.verifier'));
    }

    public function test_signing_in_again_replaces_the_tokens_and_signing_out_leaves_them(): void
    {
        $this->signInThroughProvider(self::connectMember(1234567), stay: false);
        $this->providerTokensAnswer(['expires_in' => 60, 'access_token' => 'newer-access', 'refresh_token' => 'newer-refresh']);
        $this->signInThroughProvider(self::connectMember(1234567));

        $this->post('/logout')->assertRedirect('/');

        $this->assertSame('newer-refresh', SignInToken::query()->sole()->refresh_token);
    }

    #[DataProvider('providers')]
    public function test_declining_at_the_provider_lands_back_with_a_retry(string $provider, string $label): void
    {
        config(['auth.sign_in_provider' => $provider]);
        Http::fake();

        $this->get("/auth/{$provider}?return=".urlencode('/events/15'));
        $this->get("/auth/{$provider}/callback?error=access_denied&state=".session('sign_in.state'))
            ->assertRedirect('/events/15')
            ->assertSessionHasErrors(['signIn' => "Sign-in was cancelled on {$label}."]);

        $this->assertGuest();
        Http::assertNothingSent();
        $this->assertTrue(Inertia::getFlashed()['signInRetry'] ?? false);
    }

    public function test_a_callback_whose_state_does_not_match_is_expired(): void
    {
        Log::spy();
        Http::fake();
        $this->get('/auth/vatsim');

        $this->get('/auth/vatsim/callback?code=code&state=forged')
            ->assertRedirect('/')
            ->assertSessionHasErrors(['signIn' => 'Your sign-in took too long or was started in another tab. Please try again.']);
        // The state is used once: the right one doesn't work after a wrong one either.
        $this->get('/auth/vatsim/callback?code=code&state=forged')->assertSessionHasErrors('signIn');

        $this->assertGuest();
        Http::assertNothingSent();
        Log::shouldHaveReceived('info')->with('Sign-in callback with a missing or mismatched state.');
    }

    public function test_a_provider_that_fails_is_unavailable_and_is_logged_without_any_secrets(): void
    {
        Log::spy();
        $this->providerTokensAnswer(['error' => 'server_error'], 503);

        $this->signInThroughProvider(self::connectMember(1234567))
            ->assertSessionHasErrors(['signIn' => 'VATSIM isn’t responding right now. Please try again in a few minutes.']);

        $this->assertGuest();
        $this->assertSame(0, User::query()->count());
        Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context) => $context === ['provider' => 'vatsim', 'status' => 503, 'error' => 'server_error']);
    }

    public function test_a_member_who_did_not_share_their_vatsim_details_is_turned_away(): void
    {
        $member = self::connectMember(1234567);
        unset($member['vatsim']);

        $this->signInThroughProvider($member)
            ->assertSessionHasErrors(['signIn' => 'VATSIM didn’t share your VATSIM details. Please allow access to them when you sign in.']);

        $this->assertGuest();
        $this->assertSame(0, User::query()->count());
    }

    #[TestWith([ControllerRating::Suspended])]
    #[TestWith([ControllerRating::Inactive])]
    public function test_suspended_and_inactive_members_are_turned_away_without_a_retry(ControllerRating $rating): void
    {
        $this->signInThroughProvider(self::connectMember(1234567, $rating))
            ->assertSessionHasErrors(['signIn' => 'Your VATSIM account is suspended or inactive, so you can’t sign in.']);

        $this->assertGuest();
        $this->assertArrayNotHasKey('signInRetry', Inertia::getFlashed());
    }

    public function test_an_expired_token_is_refreshed_once_and_rotated(): void
    {
        $this->signInThroughProvider(self::connectMember(1234567));
        $user = User::query()->sole();
        $this->travel(8)->days();
        $this->providerTokensAnswer(['expires_in' => 604800, 'access_token' => 'access-2', 'refresh_token' => 'refresh-2']);

        $tokens = app(SignInTokens::class);
        $this->assertSame('access-2', $tokens->accessToken($user));
        $this->assertSame('access-2', $tokens->accessToken($user));

        $this->assertSame('refresh-2', SignInToken::query()->sole()->refresh_token);
        Http::assertSentCount(3); // the code exchange, /api/user, and one refresh
    }

    public function test_a_refused_refresh_deletes_the_token_but_an_outage_keeps_it(): void
    {
        $this->signInThroughProvider(self::connectMember(1234567));
        $user = User::query()->sole();
        $this->travel(8)->days();
        $tokens = app(SignInTokens::class);

        $this->providerTokensAnswer(['error' => 'server_error'], 502);
        try {
            $tokens->accessToken($user);
            $this->fail('An outage should be reported.');
        } catch (OAuthRequestFailed $failure) {
            $this->assertFalse($failure->isPermanent());
        }
        $this->assertSame(1, SignInToken::query()->count());

        // Connect answers a revoked refresh token with 401 invalid_request.
        $this->providerTokensAnswer(['error' => 'invalid_request', 'hint' => 'Token has been revoked'], 401);
        $this->assertNull($tokens->accessToken($user));
        $this->assertSame(0, SignInToken::query()->count());
        $this->assertAuthenticatedAs($user);
    }

    public function test_tokens_from_the_other_provider_are_dropped(): void
    {
        config(['auth.sign_in_provider' => 'handover']);
        $this->signInThroughProvider(self::connectMember(1234567));
        config(['auth.sign_in_provider' => 'vatsim']);

        $this->assertNull(app(SignInTokens::class)->accessToken(User::query()->sole()));
        $this->assertSame(0, SignInToken::query()->count());
    }
}
