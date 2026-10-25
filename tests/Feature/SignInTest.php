<?php

namespace Tests\Feature;

use App\Enums\ControllerRating;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as SocialiteUser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class SignInTest extends TestCase
{
    use RefreshDatabase;

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

    #[DataProvider('providers')]
    public function test_sign_in_sends_you_to_the_configured_provider(string $provider): void
    {
        config(['auth.sign_in_provider' => $provider]);
        Socialite::fake($provider);

        $this->get("/auth/{$provider}")->assertRedirect("https://socialite.fake/{$provider}/authorize");
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
    public function test_the_driver_asks_the_configured_server_for_the_details_we_need(string $provider, string $baseUrl, string $server): void
    {
        config(['auth.sign_in_provider' => $provider, "services.{$provider}.client_id" => 'client-id', "services.{$provider}.base_url" => $baseUrl]);

        $location = $this->get("/auth/{$provider}")->assertRedirect()->headers->get('Location');

        $this->assertStringStartsWith("{$server}/oauth/authorize?", $location);
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        $this->assertSame('client-id', $query['client_id']);
        $this->assertSame('full_name vatsim_details email', $query['scope']);
        $this->assertSame(url("/auth/{$provider}/callback"), $query['redirect_uri']);
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
    public function test_first_sign_in_creates_the_user_from_their_vatsim_details(string $provider): void
    {
        config(['auth.sign_in_provider' => $provider]);
        Socialite::fake($provider, $this->vatsimUser(cid: 1234567, name: 'Jane Controller', email: 'jane@example.com', rating: ControllerRating::S2));

        $this->get("/auth/{$provider}/callback?code=code&state=state")->assertRedirect('/');

        $user = User::query()->sole();
        $this->assertSame(1234567, $user->vatsim_cid);
        $this->assertSame('Jane Controller', $user->name);
        $this->assertSame('jane@example.com', $user->email);
        $this->assertSame(ControllerRating::S2, $user->rating);
        $this->assertAuthenticatedAs($user);
    }

    #[DataProvider('providers')]
    public function test_returning_user_is_updated_and_sent_where_they_were_going(string $provider): void
    {
        config(['auth.sign_in_provider' => $provider]);
        $user = User::factory()->create(['vatsim_cid' => 1234567, 'name' => 'Old Name', 'rating' => ControllerRating::S1]);
        Socialite::fake($provider, $this->vatsimUser(cid: 1234567, name: 'New Name', email: 'new@example.com', rating: ControllerRating::C1));

        $this->get('/admin/users')->assertRedirect("/auth/{$provider}");
        $this->get("/auth/{$provider}/callback?code=code&state=state")->assertRedirect('/admin/users');

        $this->assertSame(1, User::query()->count());
        $user->refresh();
        $this->assertSame('New Name', $user->name);
        $this->assertSame(ControllerRating::C1, $user->rating);
        $this->assertAuthenticatedAs($user);
    }

    public function test_signing_in_from_a_page_returns_you_to_it(): void
    {
        Socialite::fake('vatsim', $this->vatsimUser(cid: 1234567, name: 'Jane Controller', email: 'jane@example.com', rating: ControllerRating::S2));

        $this->get('/auth/vatsim?return='.urlencode('/events/15?occurrence=3'));

        $this->get('/auth/vatsim/callback?code=code&state=state')->assertRedirect('/events/15?occurrence=3');
    }

    #[TestWith(['https://evil.example/phish'])]
    #[TestWith(['//evil.example/phish'])]
    #[TestWith(['/\\evil.example/phish'])]
    #[TestWith(["/\t/evil.example/phish"])]
    public function test_a_return_address_off_this_site_is_ignored(string $returnTo): void
    {
        Socialite::fake('vatsim', $this->vatsimUser(cid: 1234567, name: 'Jane Controller', email: 'jane@example.com', rating: ControllerRating::S2));

        $this->get('/auth/vatsim?return='.urlencode($returnTo));

        $this->get('/auth/vatsim/callback?code=code&state=state')->assertRedirect('/');
    }

    #[TestWith(["/events\n/15"])]
    #[TestWith(["/events\x00/15"])]
    #[TestWith(["/events\x7F/15"])]
    public function test_a_return_address_with_control_characters_is_ignored(string $returnTo): void
    {
        Socialite::fake('vatsim', $this->vatsimUser(cid: 1234567, name: 'Jane Controller', email: 'jane@example.com', rating: ControllerRating::S2));

        $this->get('/auth/vatsim?return='.urlencode($returnTo));

        $this->get('/auth/vatsim/callback?code=code&state=state')->assertRedirect('/');
    }

    #[DataProvider('providers')]
    public function test_declining_at_the_provider_shows_an_error_instead_of_signing_in(string $provider, string $label): void
    {
        config(['auth.sign_in_provider' => $provider]);
        Socialite::fake($provider, fn () => $this->fail('The callback should not ask the provider for the user.'));

        $this->get("/auth/{$provider}/callback?error=access_denied&state=state")
            ->assertRedirect('/')
            ->assertSessionHasErrors(['signIn' => "Sign-in was cancelled on {$label}."]);
        $this->assertGuest();
    }

    #[DataProvider('providers')]
    public function test_a_callback_with_an_invalid_state_shows_an_error_instead_of_signing_in(string $provider, string $label): void
    {
        config(['auth.sign_in_provider' => $provider]);
        Socialite::fake($provider, fn () => throw new InvalidStateException);

        $this->get("/auth/{$provider}/callback?code=code&state=forged")
            ->assertRedirect('/')
            ->assertSessionHasErrors(['signIn' => "Sign-in with {$label} failed. Please try again."]);
        $this->assertGuest();
    }

    private function vatsimUser(int $cid, string $name, string $email, ControllerRating $rating): SocialiteUser
    {
        return (new SocialiteUser)
            ->setRaw([
                'cid' => (string) $cid,
                'personal' => ['name_full' => $name, 'email' => $email],
                'vatsim' => ['rating' => ['id' => $rating->value, 'short' => $rating->label()]],
            ])
            ->map(['id' => (string) $cid, 'name' => $name, 'email' => $email]);
    }
}
