<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $content = $response->getContent();

        // Verify Vite outputs root-relative URLs (/build/assets/...) rather than absolute domain URLs
        // which avoids port mismatch, CORS, and wrong IP issues across reverse proxies and custom ports.
        $this->assertStringContainsString('/build/assets/', $content);
        $this->assertStringNotContainsString('http://localhost:8000/build/', $content);
    }
}
