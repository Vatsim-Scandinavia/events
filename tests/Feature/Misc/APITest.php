<?php

namespace Tests\Feature\Misc;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class APITest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Test a system administrator can create an API key with readonly rights.
     */
    public function test_an_admin_can_create_an_api_key_with_readonly()
    {
        $name = $this->faker->sentence(1);

        $this->artisan('create:apikey')
            ->expectsQuestion('Should the API key have edit rights?', 'NO, read only')
            ->expectsQuestion('What should we name the API Key?', $name)
            ->assertExitCode(0);

        // Assert that API key is created in the database with readonly set to true
        $this->assertDatabaseHas('api_keys', [
            'name' => $name,
            'readonly' => 1,
        ]);
    }

    /**
     * Test a system administrator can create an API key with edit rights
     */
    public function test_an_admin_can_create_an_api_key_with_edit_rights()
    {
        $name = $this->faker->sentence(1);

        // Mock user input
        $this->artisan('create:apikey')
            ->expectsQuestion('Should the API key have edit rights?', 1)  // Providing numeric choice
            ->expectsQuestion('What should we name the API Key?', $name)
            ->assertExitCode(0);

        // Assert that the API key was created with edit rights
        $this->assertDatabaseHas('api_keys', [
            'name' => $name,
            'readonly' => 0,
        ]);
    }
}
