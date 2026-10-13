<?php

namespace Tests\Feature;

use Tests\TestCase;

final class ErrorBodyTest extends TestCase
{
    public function test_unknown_route_uses_single_error_body(): void
    {
        $response = $this->getJson('/api/v1/does-not-exist');

        $response->assertNotFound()
            ->assertExactJson(['code' => 'NOT_FOUND', 'message' => 'El recurso no existe.', 'details' => []]);
    }

    public function test_route_outside_api_v1_is_not_exposed(): void
    {
        $this->getJson('/up')->assertNotFound()->assertJsonStructure(['code', 'message', 'details']);
        $this->getJson('/')->assertNotFound()->assertJsonStructure(['code', 'message', 'details']);
    }
}
