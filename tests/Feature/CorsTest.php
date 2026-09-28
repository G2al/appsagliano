<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CorsTest extends TestCase
{
    use RefreshDatabase;

    public function test_preflight_from_frontend_origin_is_allowed_for_bearer_and_document_password_headers(): void
    {
        $response = $this->call('OPTIONS', '/api/trips', [], [], [], [
            'HTTP_ORIGIN' => 'http://localhost:3000',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'authorization,x-document-password,content-type',
        ]);

        $response->assertSuccessful();
        $response->assertHeader('Access-Control-Allow-Origin', 'http://localhost:3000');

        $allowedHeaders = strtolower((string) $response->headers->get('Access-Control-Allow-Headers'));
        $this->assertStringContainsString('authorization', $allowedHeaders);
        $this->assertStringContainsString('x-document-password', $allowedHeaders);
    }

    public function test_unknown_origin_gets_no_cors_headers(): void
    {
        $response = $this->call('OPTIONS', '/api/trips', [], [], [], [
            'HTTP_ORIGIN' => 'https://evil.example',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
        ]);

        $this->assertNull($response->headers->get('Access-Control-Allow-Origin'));
    }
}
