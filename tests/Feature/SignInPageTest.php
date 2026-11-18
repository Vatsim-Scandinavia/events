<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class SignInPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_sent_to_the_sign_in_page_and_land_where_they_were_going(): void
    {
        $this->get('/profile')->assertRedirect('/sign-in');

        $this->get('/sign-in')->assertInertia(fn (Assert $page) => $page
            ->component('Auth/SignIn')
            ->where('provider', ['label' => 'Sign in with VATSIM', 'url' => '/auth/vatsim'])
            ->where('returnTo', null));
    }

    public function test_the_page_carries_a_return_path_on_this_site_and_drops_any_other(): void
    {
        $this->get('/sign-in?return='.urlencode('/events/15'))->assertInertia(fn (Assert $page) => $page->where('returnTo', '/events/15'));
        $this->get('/sign-in?return='.urlencode('//evil.example'))->assertInertia(fn (Assert $page) => $page->where('returnTo', null));
    }

    public function test_signed_in_people_do_not_see_it(): void
    {
        $this->actingAs(User::factory()->create())->get('/sign-in')->assertRedirect('/');
    }

    public function test_locally_it_offers_the_sandbox_members_to_sign_in_as(): void
    {
        $this->app->detectEnvironment(fn () => 'local');

        $this->get('/sign-in')->assertInertia(fn (Assert $page) => $page->where('devCids', range(10000001, 10000010)));
    }

    public function test_signing_in_as_one_creates_the_account_if_needed_and_returns_where_they_were(): void
    {
        $this->app->detectEnvironment(fn () => 'local');
        // CSRF checks only skip themselves in the testing environment, so send a valid token.
        $this->withSession(['_token' => 'token'])->post('/sign-in/dev/10000004', ['_token' => 'token', 'return' => '/events/15'])->assertRedirect('/events/15');

        $user = User::query()->sole();
        $this->assertSame([10000004, 'VATSIM 10000004'], [$user->vatsim_cid, $user->name]);
        $this->assertAuthenticatedAs($user);
    }

    public function test_only_the_sandbox_members_can_be_signed_in_as(): void
    {
        $this->app->detectEnvironment(fn () => 'local');
        $this->withSession(['_token' => 'token'])->post('/sign-in/dev/1234567', ['_token' => 'token'])->assertNotFound();
        $this->withSession(['_token' => 'token'])->post('/sign-in/dev/10000011', ['_token' => 'token'])->assertNotFound();
        $this->assertGuest();
    }

    #[TestWith(['production'])]
    #[TestWith(['staging'])]
    #[TestWith(['testing'])]
    #[TestWith(['anything-else'])]
    public function test_dev_sign_in_is_never_offered_outside_local_development(string $environment): void
    {
        $this->app->detectEnvironment(fn () => $environment);

        $this->get('/sign-in')->assertInertia(fn (Assert $page) => $page->where('devCids', null));
        // CSRF checks only skip themselves in the testing environment, so send a valid token.
        $this->withSession(['_token' => 'token'])->post('/sign-in/dev/10000001', ['_token' => 'token'])->assertNotFound();
        $this->assertGuest();
    }
}
