<?php

namespace Tests\Feature;

use Tests\TestCase;

/** T-01 evidence: GET /api/v1/health responds {"status":"ok"} without a token. */
class HealthTest extends TestCase
{
    public function test_health_returns_ok_without_token(): void
    {
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertExactJson(['status' => 'ok']);
    }
}
