<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_oauth_users_do_not_need_local_email_verification(): void
    {
        $this->actingAs(User::factory()->create())->get(route('dashboard'))->assertOk();
        $this->get('/email/verify')->assertNotFound();
    }
}
