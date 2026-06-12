<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    public function test_baseline_security_headers_are_present_on_a_web_response(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->assertNotNull($response->headers->get('Permissions-Policy'));
    }

    public function test_csp_is_enforced_by_default(): void
    {
        $response = $this->get(route('login'));

        // Enforcing CSP present (script/style stay permissive for Livewire/Alpine,
        // but frame-ancestors/base-uri/form-action/object-src are enforced).
        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertNotNull($csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString("frame-ancestors 'self'", $csp);
        $this->assertNull($response->headers->get('Content-Security-Policy-Report-Only'));
    }

    public function test_csp_falls_back_to_report_only_when_toggle_disabled(): void
    {
        config(['security.csp_enforce' => false]);

        $response = $this->get(route('login'));

        // Kill switch: enforcing header absent, Report-Only present.
        $this->assertNotNull($response->headers->get('Content-Security-Policy-Report-Only'));
        $this->assertNull($response->headers->get('Content-Security-Policy'));
    }

    public function test_hsts_is_omitted_over_plain_http(): void
    {
        $response = $this->get(route('login'));

        // Local/test requests are http → no HSTS pin.
        $this->assertNull($response->headers->get('Strict-Transport-Security'));
    }

    public function test_headers_also_apply_to_api_responses(): void
    {
        $response = $this->getJson('/api/v1/rooms'); // 401 (unauthenticated) still carries headers

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
    }
}
