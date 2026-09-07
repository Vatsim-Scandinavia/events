<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PasswordConfirmationTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_password_confirmation_is_not_available(): void
    {
        $this->actingAs(User::factory()->create())->get('/user/confirm-password')->assertNotFound();
        $this->post('/user/confirm-password', ['password' => 'password'])->assertNotFound();
    }
}
