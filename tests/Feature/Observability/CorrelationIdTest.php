<?php

declare(strict_types=1);

namespace Tests\Feature\Observability;

use App\Models\User;
use Illuminate\Support\Facades\Context;
use Tests\TestCase;

class CorrelationIdTest extends TestCase
{
    public function test_response_carries_a_generated_request_id(): void
    {
        $response = $this->get(route('login'));

        $id = $response->headers->get('X-Request-Id');
        $this->assertNotNull($id);
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', (string) $id);
    }

    public function test_inbound_request_id_is_honoured_and_echoed(): void
    {
        $response = $this->withHeaders(['X-Request-Id' => 'trace-abc-123'])->get(route('login'));

        $response->assertHeader('X-Request-Id', 'trace-abc-123');
    }

    public function test_request_id_and_actor_are_added_to_log_context(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('dashboard'))->assertOk();

        // Context is populated during the request and attached to every log line.
        $this->assertSame($user->id, Context::get('actor_id'));
        $this->assertNotNull(Context::get('request_id'));
    }
}
