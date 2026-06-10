<?php

declare(strict_types=1);

namespace Tests\Feature\Rooms;

use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBookingScenarios;
use Tests\TestCase;

class RoomDetailPageTest extends TestCase
{
    use CreatesBookingScenarios;
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create(['is_active' => true]);
    }

    public function test_room_detail_page_renders(): void
    {
        $room = $this->createRoomWithStandardHours();

        $this->actingAs($this->user())
            ->get(route('rooms.show', $room))
            ->assertOk()
            ->assertSee($room->name, false)
            ->assertSee('Jam Operasional', false)
            ->assertSee('Kapasitas', false);
    }

    public function test_inactive_room_404s(): void
    {
        $room = Room::factory()->create(['status' => 'inactive']);

        $this->actingAs($this->user())
            ->get(route('rooms.show', $room))
            ->assertNotFound();
    }

    public function test_public_room_cards_link_to_the_detail_page(): void
    {
        $room = Room::factory()->create(['status' => 'active']);

        $this->actingAs($this->user())
            ->get(route('rooms.index'))
            ->assertOk()
            ->assertSee(route('rooms.show', $room), false);
    }
}
