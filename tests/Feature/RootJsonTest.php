<?php

namespace Tests\Feature;

use Tests\TestCase;

class RootJsonTest extends TestCase
{
    public function test_root_returns_json(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Billiard API',
            ]);
    }
}