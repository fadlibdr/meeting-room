<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiDocsTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_the_docs_page(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('api-docs.page'))
            ->assertOk()
            ->assertSee('<redoc', false)
            ->assertSee('vendor/redoc/redoc.standalone.js', false);
    }

    public function test_spec_endpoint_serves_the_openapi_yaml(): void
    {
        $response = $this->actingAs(User::factory()->create())->get(route('api-docs.spec'));

        $response->assertOk();
        $this->assertStringContainsString('application/yaml', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('openapi: 3.1', $response->getContent());
        $this->assertStringContainsString('webhooks:', $response->getContent());
        $this->assertStringContainsString('booking.auto_released', $response->getContent());
        $this->assertStringContainsString('/rooms/{room}/availability', $response->getContent());
    }

    public function test_guest_is_redirected(): void
    {
        $this->get(route('api-docs.page'))->assertRedirect(route('login'));
    }
}
