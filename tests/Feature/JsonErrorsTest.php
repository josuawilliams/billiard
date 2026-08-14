<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class JsonErrorsTest extends TestCase
{
    public function test_404_returns_json_without_accept_header(): void
    {
        $response = $this->get('/this-route-does-not-exist');

        $response->assertNotFound()
            ->assertHeader('content-type', 'application/json');
    }

    public function test_unauthenticated_api_returns_json(): void
    {
        $response = $this->postJson('/api/bookings');

        $response->assertUnauthorized()
            ->assertJson(['success' => false]);
    }
}