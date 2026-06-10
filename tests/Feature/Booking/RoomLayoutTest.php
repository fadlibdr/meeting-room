<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Actions\SubmitBookingAction;
use App\Enums\RoomLayout;
use App\Models\Room;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RoomLayoutTest extends TestCase
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

    private function room(): Room
    {
        return Room::factory()->create([
            'approval_mode' => 'none', 'is_active' => true, 'status' => 'active',
            'capacity' => 10, 'booking_buffer_minutes' => 0,
        ]);
    }

    private function input(Room $room, array $overrides = []): array
    {
        return array_merge([
            'resource_id' => $room->id,
            'subject' => 'Rapat Tim',
            'agenda' => null,
            'attendee_count' => 4,
            'starts_at' => '2026-05-05 10:00:00',
            'ends_at' => '2026-05-05 11:00:00',
        ], $overrides);
    }

    public function test_room_layout_is_persisted_and_cast(): void
    {
        $requester = User::factory()->create(['unit_id' => Unit::factory()->create()->id]);

        $booking = app(SubmitBookingAction::class)->execute(
            $requester,
            $this->input($this->room(), ['room_layout' => RoomLayout::UShape->value]),
        );

        $this->assertSame(RoomLayout::UShape, $booking->fresh()->room_layout);
        $this->assertSame('Bentuk U', $booking->room_layout->label());
    }

    public function test_room_layout_is_optional(): void
    {
        $requester = User::factory()->create(['unit_id' => Unit::factory()->create()->id]);

        $booking = app(SubmitBookingAction::class)->execute($requester, $this->input($this->room()));

        $this->assertNull($booking->fresh()->room_layout);
    }
}
