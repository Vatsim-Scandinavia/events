<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class VerificationNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_oauth_users_cannot_request_local_verification_emails(): void
    {
        $user = User::factory()->create();
        Notification::fake();

        $this->actingAs($user)->post('/email/verification-notification')->assertNotFound();

        Notification::assertNothingSent();
    }
}
