<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\Booking;
use App\Services\SettingsService;
use Database\Seeders\AppSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBookingScenarios;
use Tests\TestCase;

class AutoReleaseToggleTest extends TestCase
{
    use CreatesBookingScenarios;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AppSettingsSeeder::class);
    }

    public function test_disabled_setting_skips_auto_release(): void
    {
        app(SettingsService::class)->set('booking.auto_release_enabled', false);

        $room = $this->createRoomWithStandardHours();
        // An ongoing, approved, never-checked-in booking that WOULD be released.
        $booking = $this->createBooking(
            $room,
            now()->subHour()->format('Y-m-d H:i:s'),
            now()->addHour()->format('Y-m-d H:i:s'),
            'approved',
        );

        $this->artisan('bookings:auto-release')->expectsOutputToContain('disabled')->assertSuccessful();

        $this->assertNull($booking->fresh()->released_at);
    }

    public function test_enabled_setting_runs_auto_release(): void
    {
        app(SettingsService::class)->set('booking.auto_release_enabled', true);
        app(SettingsService::class)->set('booking.auto_release_grace_minutes', 0);

        $room = $this->createRoomWithStandardHours();
        $booking = $this->createBooking(
            $room,
            now()->subHour()->format('Y-m-d H:i:s'),
            now()->addHour()->format('Y-m-d H:i:s'),
            'approved',
        );

        $this->artisan('bookings:auto-release')->assertSuccessful();

        $this->assertNotNull($booking->fresh()->released_at);
    }
}
