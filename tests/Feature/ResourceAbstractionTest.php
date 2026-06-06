<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ResourceType;
use App\Models\Resource;
use App\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResourceAbstractionTest extends TestCase
{
    use RefreshDatabase;

    public function test_rooms_live_on_the_resources_table_typed_as_room(): void
    {
        $room = Room::factory()->create();

        $this->assertDatabaseHas('resources', [
            'id' => $room->id,
            'type' => ResourceType::Room->value,
        ]);
        $this->assertSame(ResourceType::Room, $room->fresh()->type);
    }

    public function test_new_rooms_are_stamped_room_even_without_explicit_type(): void
    {
        $room = new Room;
        $room->forceFill(Room::factory()->raw());
        $room->type = null;
        $room->save();

        $this->assertSame(ResourceType::Room, $room->fresh()->type);
    }

    public function test_room_global_scope_hides_non_room_resources(): void
    {
        Room::factory()->count(2)->create();
        Resource::factory()->ofType(ResourceType::Vehicle)->create();
        Resource::factory()->ofType(ResourceType::Equipment)->create();

        // Room is type-scoped; Resource sees every type.
        $this->assertSame(2, Room::count());
        $this->assertSame(4, Resource::count());
    }

    public function test_non_room_resource_carries_typed_attributes(): void
    {
        $vehicle = Resource::factory()->ofType(ResourceType::Vehicle)->create([
            'metadata' => ['plate' => 'B 1234 ABC', 'seats' => 7],
        ]);

        $fresh = $vehicle->fresh();
        $this->assertSame(ResourceType::Vehicle, $fresh->type);
        $this->assertSame('B 1234 ABC', $fresh->metadata['plate']);
        $this->assertSame(7, $fresh->metadata['seats']);
    }
}
