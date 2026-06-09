<?php

declare(strict_types=1);

namespace Tests\Feature\Rooms;

use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicRoomPhotoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_room_photo_renders_on_the_public_room_cards(): void
    {
        $room = Room::factory()->create([
            'status' => 'active',
            'photo_path' => 'room-photos/example.jpg',
        ]);

        $this->actingAs(User::factory()->create(['is_active' => true]))
            ->get(route('rooms.index'))
            ->assertOk()
            ->assertSee(Storage::disk('public')->url('room-photos/example.jpg'), false);
    }

    public function test_room_without_photo_shows_the_placeholder(): void
    {
        Room::factory()->create(['status' => 'active', 'photo_path' => null]);

        $this->actingAs(User::factory()->create(['is_active' => true]))
            ->get(route('rooms.index'))
            ->assertOk()
            ->assertSee('Foto ruangan', false);
    }
}
