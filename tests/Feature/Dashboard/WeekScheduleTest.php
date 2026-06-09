<?php

declare(strict_types=1);

namespace Tests\Feature\Dashboard;

use App\Models\Booking;
use App\Models\Role;
use App\Models\Room;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WeekScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        // Freeze to Wednesday 2026-06-17 (week: Mon 15 – Sun 21, UTC app tz).
        $this->travelTo(CarbonImmutable::parse('2026-06-17 03:00:00', 'UTC'));
    }

    private function viewer(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->sync([Role::where('code', 'requester')->firstOrFail()->id]);

        return $user;
    }

    public function test_dashboard_shows_this_week_not_just_today(): void
    {
        $room = Room::factory()->create();

        // Friday this week (after today) — must appear.
        Booking::factory()->approved()->create([
            'resource_id' => $room->id,
            'subject' => 'WEEKMEETING',
            'starts_at' => CarbonImmutable::parse('2026-06-19 02:00:00', 'UTC'),
            'ends_at' => CarbonImmutable::parse('2026-06-19 03:00:00', 'UTC'),
        ]);

        // Next week — must NOT appear.
        Booking::factory()->approved()->create([
            'resource_id' => $room->id,
            'subject' => 'NEXTWEEKMEETING',
            'starts_at' => CarbonImmutable::parse('2026-06-24 02:00:00', 'UTC'),
            'ends_at' => CarbonImmutable::parse('2026-06-24 03:00:00', 'UTC'),
        ]);

        $this->actingAs($this->viewer())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Jadwal Minggu Ini', false)
            ->assertSee('WEEKMEETING', false)
            ->assertDontSee('NEXTWEEKMEETING', false);
    }

    public function test_empty_week_shows_the_week_empty_message(): void
    {
        $this->actingAs($this->viewer())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Tidak ada jadwal minggu ini', false);
    }
}
