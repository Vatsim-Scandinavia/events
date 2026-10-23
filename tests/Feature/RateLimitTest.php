<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_signed_in_people_are_limited_per_minute_each(): void
    {
        $controller = User::factory()->controller()->create();

        for ($i = 0; $i < 120; $i++) {
            $this->actingAs($controller)->delete('/bookings/999999')->assertNotFound();
        }

        $this->actingAs($controller)->delete('/bookings/999999')->assertTooManyRequests();

        // Someone else still gets through.
        $this->actingAs(User::factory()->controller()->create())->delete('/bookings/999999')->assertNotFound();

        $this->travel(1)->minute();
        $this->actingAs($controller)->delete('/bookings/999999')->assertNotFound();
    }
}
