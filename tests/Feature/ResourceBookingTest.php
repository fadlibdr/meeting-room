<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\SubmitBookingAction;
use App\Enums\BookingStatus;
use App\Enums\ResourceType;
use App\Exceptions\BookingConflictException;
use App\Models\Resource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Stage 3 E2 — the booking/conflict engine is generalized to any Resource
 * type, not just rooms. These exercise the real domain pipeline (no UI).
 */
class ResourceBookingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{resource_id: int, subject: string, attendee_count: int, starts_at: string, ends_at: string}
     */
    private function slot(Resource $resource, string $start, string $end): array
    {
        return [
            'resource_id' => $resource->id,
            'subject' => 'Pinjam '.$resource->name,
            'attendee_count' => 1,
            'starts_at' => $start,
            'ends_at' => $end,
        ];
    }

    public function test_a_non_room_resource_books_through_the_pipeline(): void
    {
        $requester = User::factory()->create();
        $vehicle = Resource::factory()->ofType(ResourceType::Vehicle)->create();

        $booking = app(SubmitBookingAction::class)->execute(
            $requester,
            $this->slot($vehicle, '2026-07-01 09:00:00', '2026-07-01 11:00:00'),
            notify: false,
        );

        // approval_mode None on the resource → auto-approved.
        $this->assertSame(BookingStatus::Approved, $booking->status);
        $this->assertSame($vehicle->id, $booking->resource_id);
        $this->assertSame(ResourceType::Vehicle, $booking->resource->type);
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'resource_id' => $vehicle->id,
            'status' => BookingStatus::Approved->value,
        ]);
    }

    public function test_conflict_detection_applies_to_non_room_resources(): void
    {
        $requester = User::factory()->create();
        $equipment = Resource::factory()->ofType(ResourceType::Equipment)->create();
        $action = app(SubmitBookingAction::class);

        $action->execute(
            $requester,
            $this->slot($equipment, '2026-07-02 09:00:00', '2026-07-02 11:00:00'),
            notify: false,
        );

        // An overlapping second booking on the same resource is rejected.
        $this->expectException(BookingConflictException::class);
        $action->execute(
            $requester,
            $this->slot($equipment, '2026-07-02 10:00:00', '2026-07-02 12:00:00'),
            notify: false,
        );
    }
}
