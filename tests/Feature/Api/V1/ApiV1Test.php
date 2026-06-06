<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\RoomApprovalMode;
use App\Models\Booking;
use App\Models\Role;
use App\Models\Room;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiV1Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Carbon::setTestNow('2026-05-05 09:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function requester(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->sync([Role::where('code', 'requester')->firstOrFail()->id]);

        return $user;
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/v1/rooms')->assertUnauthorized();
    }

    public function test_read_endpoint_requires_the_read_ability(): void
    {
        Room::factory()->create(['status' => 'active']);
        $user = $this->requester();

        // Token WITHOUT read ability.
        Sanctum::actingAs($user, ['booking:write']);
        $this->getJson('/api/v1/rooms')->assertForbidden();

        // Token WITH read ability.
        Sanctum::actingAs($user, ['read']);
        $this->getJson('/api/v1/rooms')->assertOk()->assertJsonStructure(['data' => [['id', 'code', 'name']]]);
    }

    public function test_availability_reports_conflicts(): void
    {
        $room = Room::factory()->create(['status' => 'active', 'approval_mode' => RoomApprovalMode::None->value]);
        Booking::factory()->approved()->create([
            'room_id' => $room->id,
            'starts_at' => '2026-05-06 02:00:00',
            'ends_at' => '2026-05-06 03:00:00',
        ]);
        Sanctum::actingAs($this->requester(), ['read']);

        $this->getJson("/api/v1/rooms/{$room->id}/availability?starts_at=2026-05-06T02:30:00Z&ends_at=2026-05-06T02:45:00Z")
            ->assertOk()->assertJsonPath('data.available', false);

        $this->getJson("/api/v1/rooms/{$room->id}/availability?starts_at=2026-05-06T08:00:00Z&ends_at=2026-05-06T09:00:00Z")
            ->assertOk()->assertJsonPath('data.available', true);
    }

    public function test_bookings_index_returns_only_the_token_owners_bookings(): void
    {
        $user = $this->requester();
        $other = $this->requester();
        $mine = Booking::factory()->approved()->create(['requester_user_id' => $user->id]);
        Booking::factory()->approved()->create(['requester_user_id' => $other->id]);

        Sanctum::actingAs($user, ['read']);
        $this->getJson('/api/v1/bookings')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $mine->id);
    }

    public function test_create_booking_requires_booking_write_ability(): void
    {
        $room = Room::factory()->create(['status' => 'active', 'approval_mode' => RoomApprovalMode::None->value]);
        Sanctum::actingAs($this->requester(), ['read']); // read only

        $this->postJson('/api/v1/bookings', [
            'room_id' => $room->id, 'subject' => 'X', 'attendee_count' => 2,
            'starts_at' => '2026-05-07 02:00:00', 'ends_at' => '2026-05-07 03:00:00',
        ])->assertForbidden();
    }

    public function test_create_booking_succeeds_and_obeys_conflicts(): void
    {
        $room = Room::factory()->create(['status' => 'active', 'approval_mode' => RoomApprovalMode::None->value]);
        $user = $this->requester();
        Sanctum::actingAs($user, ['read', 'booking:write']);

        $this->postJson('/api/v1/bookings', [
            'room_id' => $room->id, 'subject' => 'Rapat API', 'attendee_count' => 3,
            'starts_at' => '2026-05-07 02:00:00', 'ends_at' => '2026-05-07 03:00:00',
        ])->assertStatus(201)->assertJsonPath('data.subject', 'Rapat API');

        $this->assertDatabaseHas('bookings', ['subject' => 'Rapat API', 'source' => 'api', 'requester_user_id' => $user->id]);

        // Overlapping slot is rejected by the conflict service.
        $this->postJson('/api/v1/bookings', [
            'room_id' => $room->id, 'subject' => 'Bentrok', 'attendee_count' => 3,
            'starts_at' => '2026-05-07 02:30:00', 'ends_at' => '2026-05-07 03:30:00',
        ])->assertStatus(422);
    }

    public function test_create_booking_forbidden_without_create_permission(): void
    {
        $room = Room::factory()->create(['status' => 'active', 'approval_mode' => RoomApprovalMode::None->value]);
        $user = User::factory()->create(['is_active' => true]); // no role -> no bookings.create
        Sanctum::actingAs($user, ['read', 'booking:write']);

        $this->postJson('/api/v1/bookings', [
            'room_id' => $room->id, 'subject' => 'X', 'attendee_count' => 2,
            'starts_at' => '2026-05-07 06:00:00', 'ends_at' => '2026-05-07 07:00:00',
        ])->assertForbidden();
    }
}
