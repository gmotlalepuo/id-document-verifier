<?php
namespace Tests\Feature;

use Tests\TestCase;

class ApiKeyAuthenticationTest extends TestCase
{
    public function test_verification_requires_x_api_key_header(): void
    {
        config(['ocr.api_key' => 'test-document-key']);

        $this->postJson('/api/v1/verifications')->assertUnauthorized();
    }

    public function test_verification_accepts_configured_x_api_key_header(): void
    {
        config(['ocr.api_key' => 'test-document-key']);

        $this->postJson('/api/v1/verifications', [], [
            'X-API-Key' => 'test-document-key',
        ])->assertUnprocessable();
    }
}
