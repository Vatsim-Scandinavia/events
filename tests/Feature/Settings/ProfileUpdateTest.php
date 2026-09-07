<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_displays_provider_details_without_tokens(): void
    {
        $user = User::factory()->create(['oauth_access_token' => 'secret-access', 'oauth_refresh_token' => 'secret-refresh']);

        $this->actingAs($user)->get(route('profile.edit'))->assertInertia(fn (Assert $page) => $page
            ->component('settings/profile')
            ->where('auth.user.cid', $user->cid)
            ->where('auth.user.name_full', $user->name_full)
            ->where('auth.user.controller_rating', $user->controller_rating)
            ->missing('auth.user.oauth_access_token')
            ->missing('auth.user.oauth_refresh_token')
            ->missing('auth.user.remember_token'));
    }

    public function test_guests_cannot_view_the_profile(): void
    {
        $this->get(route('profile.edit'))->assertRedirect(route('login'));
    }

    public function test_provider_profile_cannot_be_overwritten_locally(): void
    {
        $user = User::factory()->create()->refresh();

        $this->actingAs($user)->patch('/settings/profile', [
            'cid' => 1234567, 'name_full' => 'Changed Name', 'email' => 'changed@example.com', 'controller_rating' => 12,
        ])->assertMethodNotAllowed();

        $this->assertSame($user->getAttributes(), $user->fresh()->getAttributes());
    }

    public function test_password_based_account_deletion_is_not_available(): void
    {
        $user = User::factory()->create()->refresh();

        $this->actingAs($user)->delete('/settings/profile', ['password' => 'password'])->assertMethodNotAllowed();

        $this->assertModelExists($user);
    }
}
