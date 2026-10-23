<?php

namespace Tests\Feature;

use App\Enums\ControllerRating;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as SocialiteUser;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class VatsimConnectTest extends TestCase
{
    use RefreshDatabase;

    public function test_sign_in_sends_you_to_vatsim(): void
    {
        Socialite::fake('vatsim');

        $this->get('/auth/vatsim')->assertRedirect('https://socialite.fake/vatsim/authorize');
    }

    public function test_the_vatsim_driver_asks_the_configured_server_for_the_details_we_need(): void
    {
        config(['services.vatsim.client_id' => 'client-id', 'services.vatsim.base_url' => 'https://auth-dev.vatsim.net/']);

        $location = $this->get('/auth/vatsim')->assertRedirect()->headers->get('Location');

        $this->assertStringStartsWith('https://auth-dev.vatsim.net/oauth/authorize?', $location);
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        $this->assertSame('client-id', $query['client_id']);
        $this->assertSame('full_name vatsim_details email', $query['scope']);
        $this->assertSame(url('/auth/vatsim/callback'), $query['redirect_uri']);
    }

    public function test_first_sign_in_creates_the_user_from_their_vatsim_details(): void
    {
        Socialite::fake('vatsim', $this->vatsimUser(cid: 1234567, name: 'Jane Controller', email: 'jane@example.com', rating: ControllerRating::S2));

        $this->get('/auth/vatsim/callback?code=code&state=state')->assertRedirect('/');

        $user = User::query()->sole();
        $this->assertSame(1234567, $user->vatsim_cid);
        $this->assertSame('Jane Controller', $user->name);
        $this->assertSame('jane@example.com', $user->email);
        $this->assertSame(ControllerRating::S2, $user->rating);
        $this->assertAuthenticatedAs($user);
    }

    public function test_returning_user_is_updated_and_sent_where_they_were_going(): void
    {
        $user = User::factory()->create(['vatsim_cid' => 1234567, 'name' => 'Old Name', 'rating' => ControllerRating::S1]);
        Socialite::fake('vatsim', $this->vatsimUser(cid: 1234567, name: 'New Name', email: 'new@example.com', rating: ControllerRating::C1));

        $this->get('/admin/users')->assertRedirect('/auth/vatsim');
        $this->get('/auth/vatsim/callback?code=code&state=state')->assertRedirect('/admin/users');

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
    public function test_a_return_address_off_this_site_is_ignored(string $returnTo): void
    {
        Socialite::fake('vatsim', $this->vatsimUser(cid: 1234567, name: 'Jane Controller', email: 'jane@example.com', rating: ControllerRating::S2));

        $this->get('/auth/vatsim?return='.urlencode($returnTo));

        $this->get('/auth/vatsim/callback?code=code&state=state')->assertRedirect('/');
    }

    public function test_declining_on_vatsim_shows_an_error_instead_of_signing_in(): void
    {
        Socialite::fake('vatsim', fn () => $this->fail('The callback should not ask VATSIM for the user.'));

        $this->get('/auth/vatsim/callback?error=access_denied&state=state')
            ->assertRedirect('/')
            ->assertSessionHasErrors(['signIn' => 'Sign-in was cancelled on VATSIM.']);
        $this->assertGuest();
    }

    public function test_a_callback_with_an_invalid_state_shows_an_error_instead_of_signing_in(): void
    {
        Socialite::fake('vatsim', fn () => throw new InvalidStateException);

        $this->get('/auth/vatsim/callback?code=code&state=forged')
            ->assertRedirect('/')
            ->assertSessionHasErrors('signIn');
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
