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

    public function test_locally_it_offers_a_link_per_sandbox_member_with_what_each_holds(): void
    {
        User::factory()->admin()->create(['vatsim_cid' => 10000001, 'name' => 'Hub Admin']);

        $this->get('/sign-in')->assertInertia(fn (Assert $page) => $page
            ->has('devLinks', 10)
            ->where('devLinks.0', ['cid' => 10000001, 'name' => 'Hub Admin', 'roles' => ['Administrator']])
            ->where('devLinks.9', ['cid' => 10000010, 'name' => null, 'roles' => []]));
    }

    public function test_a_dev_link_signs_in_creating_the_account_if_needed_and_returns_where_they_were(): void
    {
        $this->post('/sign-in/dev/10000004', ['return' => '/events/15'])->assertRedirect('/events/15');

        $user = User::query()->sole();
        $this->assertSame([10000004, 'VATSIM 10000004'], [$user->vatsim_cid, $user->name]);
        $this->assertAuthenticatedAs($user);
    }

    public function test_a_dev_link_is_only_for_the_sandbox_members(): void
    {
        $this->post('/sign-in/dev/1234567')->assertNotFound();
        $this->post('/sign-in/dev/10000011')->assertNotFound();
        $this->assertGuest();
    }

    #[TestWith(['production'])]
    #[TestWith(['staging'])]
    #[TestWith(['anything-else'])]
    public function test_dev_links_are_never_offered_outside_local_development(string $environment): void
    {
        $this->app->detectEnvironment(fn () => $environment);

        $this->get('/sign-in')->assertInertia(fn (Assert $page) => $page->where('devLinks', null));
        // CSRF checks only skip themselves in the testing environment, so send a valid token.
        $this->withSession(['_token' => 'token'])->post('/sign-in/dev/10000001', ['_token' => 'token'])->assertNotFound();
        $this->assertGuest();
    }

    #[TestWith(['http://hub.example.org'])]
    #[TestWith(['http://127.0.0.1.example.org'])]
    #[TestWith(['http://test.example.org'])]
    public function test_dev_links_are_never_offered_on_a_host_that_is_not_local_even_in_local_development(string $origin): void
    {
        $this->app->detectEnvironment(fn () => 'local');

        $this->get("{$origin}/sign-in")->assertInertia(fn (Assert $page) => $page->where('devLinks', null));
        $this->withSession(['_token' => 'token'])->post("{$origin}/sign-in/dev/10000001", ['_token' => 'token'])->assertNotFound();
        $this->assertGuest();
    }

    #[TestWith(['http://localhost'])]
    #[TestWith(['http://127.0.0.1:8000'])]
    #[TestWith(['http://events.test'])]
    public function test_dev_links_are_offered_on_a_local_host_in_local_development(string $origin): void
    {
        $this->app->detectEnvironment(fn () => 'local');

        $this->get("{$origin}/sign-in")->assertInertia(fn (Assert $page) => $page->has('devLinks', 10));
    }
}
