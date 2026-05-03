<?php

declare(strict_types=1);

namespace Tests\Unit\Requests;

use App\Http\Requests\Booking\StoreBookingRequest;
use App\Models\Role;
use App\Models\Room;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class StoreBookingRequestTest extends TestCase
{
    use RefreshDatabase;

    private User $requester;

    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->requester = User::factory()->create();
        $role = Role::where('code', 'requester')->firstOrFail();
        $this->requester->roles()->sync([$role->id]);

        $this->room = Room::factory()->create([
            'capacity' => 10,
            'is_active' => true,
        ]);
    }

    /**
     * Validate a payload against StoreBookingRequest rules + custom validators.
     *
     * @param  array<string, mixed>  $data
     */
    private function validatePayload(array $data): \Illuminate\Validation\Validator
    {
        $request = new StoreBookingRequest;
        $request->setUserResolver(fn () => $this->requester);
        $request->merge($data);

        $validator = Validator::make($data, $request->rules(), $request->messages());

        // Trigger withValidator hooks by calling them directly
        $request->withValidator($validator);

        // Force validation to run
        $validator->passes();

        return $validator;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'room_id' => $this->room->id,
            'subject' => 'Rapat Koordinasi Tim',
            'agenda' => 'Diskusi project Q4',
            'attendee_count' => 5,
            'starts_at' => now()->addDay()->setTime(9, 0)->format('Y-m-d H:i:s'),
            'ends_at' => now()->addDay()->setTime(11, 0)->format('Y-m-d H:i:s'),
        ], $overrides);
    }

    // ─── HAPPY PATH ─────────────────────────────────────────────────

    public function test_valid_payload_passes(): void
    {
        $validator = $this->validatePayload($this->validPayload());
        $this->assertTrue($validator->passes(), $validator->errors()->first());
    }

    // ─── REQUIRED FIELDS ────────────────────────────────────────────

    public function test_room_id_is_required(): void
    {
        $validator = $this->validatePayload($this->validPayload(['room_id' => null]));
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('room_id', $validator->errors()->toArray());
    }

    public function test_subject_is_required(): void
    {
        $validator = $this->validatePayload($this->validPayload(['subject' => '']));
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('subject', $validator->errors()->toArray());
    }

    public function test_attendee_count_is_required(): void
    {
        $validator = $this->validatePayload($this->validPayload(['attendee_count' => null]));
        $this->assertTrue($validator->fails());
    }

    public function test_starts_at_is_required(): void
    {
        $validator = $this->validatePayload($this->validPayload(['starts_at' => null]));
        $this->assertTrue($validator->fails());
    }

    public function test_ends_at_is_required(): void
    {
        $validator = $this->validatePayload($this->validPayload(['ends_at' => null]));
        $this->assertTrue($validator->fails());
    }

    // ─── FORMAT / TYPE VALIDATION ───────────────────────────────────

    public function test_subject_max_length_150(): void
    {
        $validator = $this->validatePayload($this->validPayload(['subject' => str_repeat('a', 151)]));
        $this->assertTrue($validator->fails());
    }

    public function test_attendee_count_must_be_at_least_1(): void
    {
        $validator = $this->validatePayload($this->validPayload(['attendee_count' => 0]));
        $this->assertTrue($validator->fails());
    }

    public function test_room_id_must_exist(): void
    {
        $validator = $this->validatePayload($this->validPayload(['room_id' => 99999]));
        $this->assertTrue($validator->fails());
    }

    // ─── CROSS-FIELD VALIDATION ─────────────────────────────────────

    public function test_starts_at_must_be_in_future(): void
    {
        $validator = $this->validatePayload($this->validPayload([
            'starts_at' => now()->subDay()->format('Y-m-d H:i:s'),
            'ends_at' => now()->subDay()->addHour()->format('Y-m-d H:i:s'),
        ]));
        $this->assertTrue($validator->fails());
    }

    public function test_ends_at_must_be_after_starts_at(): void
    {
        $start = now()->addDay()->setTime(11, 0);
        $end = now()->addDay()->setTime(9, 0);
        $validator = $this->validatePayload($this->validPayload([
            'starts_at' => $start->format('Y-m-d H:i:s'),
            'ends_at' => $end->format('Y-m-d H:i:s'),
        ]));
        $this->assertTrue($validator->fails());
    }

    // ─── CUSTOM BUSINESS RULES ──────────────────────────────────────

    public function test_duration_cannot_exceed_max_hours(): void
    {
        $start = now()->addDay()->setTime(8, 0);
        $end = now()->addDay()->setTime(17, 0); // 9 hours, exceeds 8h max
        $validator = $this->validatePayload($this->validPayload([
            'starts_at' => $start->format('Y-m-d H:i:s'),
            'ends_at' => $end->format('Y-m-d H:i:s'),
        ]));
        $this->assertTrue($validator->fails());
        $this->assertStringContainsString(
            '8 jam',
            $validator->errors()->first('ends_at')
        );
    }

    public function test_duration_at_max_hours_is_valid(): void
    {
        $start = now()->addDay()->setTime(9, 0);
        $end = now()->addDay()->setTime(17, 0); // exactly 8 hours
        $validator = $this->validatePayload($this->validPayload([
            'starts_at' => $start->format('Y-m-d H:i:s'),
            'ends_at' => $end->format('Y-m-d H:i:s'),
        ]));
        $this->assertTrue($validator->passes(), $validator->errors()->first());
    }

    public function test_attendee_count_cannot_exceed_room_capacity(): void
    {
        $validator = $this->validatePayload($this->validPayload([
            'attendee_count' => 11, // room capacity is 10
        ]));
        $this->assertTrue($validator->fails());
        $this->assertStringContainsString(
            'kapasitas ruangan',
            $validator->errors()->first('attendee_count')
        );
    }

    public function test_attendee_count_at_room_capacity_is_valid(): void
    {
        $validator = $this->validatePayload($this->validPayload([
            'attendee_count' => 10, // exactly at capacity
        ]));
        $this->assertTrue($validator->passes(), $validator->errors()->first());
    }

    public function test_inactive_room_rejected(): void
    {
        $inactiveRoom = Room::factory()->create([
            'is_active' => false,
            'capacity' => 10,
        ]);

        $validator = $this->validatePayload($this->validPayload([
            'room_id' => $inactiveRoom->id,
        ]));
        $this->assertTrue($validator->fails());
    }
}
