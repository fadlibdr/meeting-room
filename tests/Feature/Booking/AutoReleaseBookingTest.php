<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Actions\ReleaseNoShowBookingAction;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use App\Notifications\BookingAutoReleasedNotification;
use App\Services\SettingsService;
use Database\Seeders\AppSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AutoReleaseBookingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AppSettingsSeeder::class);
        // "Now" = 10:30 WIB (03:30 UTC). Default grace 15m -> cutoff 03:15 UTC.
        Carbon::setTestNow('2026-06-08 03:30:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $attrs
     */
    private function booking(string $utcStart, string $utcEnd, array $attrs = []): Booking
    {
        return Booking::factory()->approved()->create(array_merge([
            'room_id' => Room::factory(),
            'requester_user_id' => User::factory(),
            'starts_at' => $utcStart,
            'ends_at' => $utcEnd,
        ], $attrs));
    }

    public function test_ongoing_no_show_is_released_and_requester_notified(): void
    {
        Notification::fake();
        $requester = User::factory()->create();
        // Started 03:00 (> 15m ago), ends 04:00 (still ongoing), no check-in.
        $booking = $this->booking('2026-06-08 03:00:00', '2026-06-08 04:00:00', [
            'requester_user_id' => $requester->id,
        ]);

        $this->artisan('bookings:auto-release')->assertSuccessful();

        $booking->refresh();
        $this->assertSame(BookingStatus::Cancelled, $booking->status);
        $this->assertNotNull($booking->released_at);
        $this->assertNotNull($booking->cancelled_at);
        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'status' => 'cancelled']);
        $this->assertDatabaseHas('activity_logs', ['event' => 'auto-release', 'actor_user_id' => null]);
        Notification::assertSentTo($requester, BookingAutoReleasedNotification::class);
    }

    public function test_checked_in_booking_is_skipped(): void
    {
        $booking = $this->booking('2026-06-08 03:00:00', '2026-06-08 04:00:00', [
            'checked_in_at' => '2026-06-08 03:05:00',
        ]);

        $this->artisan('bookings:auto-release')->assertSuccessful();

        $this->assertSame(BookingStatus::Approved, $booking->refresh()->status);
        $this->assertNull($booking->released_at);
    }

    public function test_already_ended_booking_is_not_swept_retroactively(): void
    {
        // Started 02:00, ended 03:25 (before now 03:30) — a past no-show.
        $booking = $this->booking('2026-06-08 02:00:00', '2026-06-08 03:25:00');

        $this->artisan('bookings:auto-release')->assertSuccessful();

        $this->assertSame(BookingStatus::Approved, $booking->refresh()->status);
        $this->assertNull($booking->released_at);
    }

    public function test_booking_within_grace_window_is_respected(): void
    {
        // Started 03:20 — only 10 min ago, inside the 15-min grace.
        $booking = $this->booking('2026-06-08 03:20:00', '2026-06-08 04:20:00');

        $this->artisan('bookings:auto-release')->assertSuccessful();

        $this->assertSame(BookingStatus::Approved, $booking->refresh()->status);
        $this->assertNull($booking->released_at);
    }

    public function test_custom_grace_setting_is_honored(): void
    {
        app(SettingsService::class)->set('booking.auto_release_grace_minutes', 45);
        // Started 03:00 — 30 min ago, which is now inside the widened 45-min grace.
        $booking = $this->booking('2026-06-08 03:00:00', '2026-06-08 04:00:00');

        $this->artisan('bookings:auto-release')->assertSuccessful();

        $this->assertNull($booking->refresh()->released_at);
    }

    public function test_is_idempotent_and_notifies_once(): void
    {
        Notification::fake();
        $requester = User::factory()->create();
        $booking = $this->booking('2026-06-08 03:00:00', '2026-06-08 04:00:00', [
            'requester_user_id' => $requester->id,
        ]);

        $this->artisan('bookings:auto-release')->assertSuccessful();
        $firstReleasedAt = $booking->refresh()->released_at;

        // A second pass must not re-release or re-notify (released_at guard).
        $this->artisan('bookings:auto-release')->assertSuccessful();

        $this->assertEquals($firstReleasedAt, $booking->refresh()->released_at);
        Notification::assertSentTimes(BookingAutoReleasedNotification::class, 1);
    }

    public function test_action_skips_a_non_approved_booking(): void
    {
        $booking = $this->booking('2026-06-08 03:00:00', '2026-06-08 04:00:00');
        $booking->update(['status' => BookingStatus::Cancelled->value]);

        $result = app(ReleaseNoShowBookingAction::class)->execute($booking);

        $this->assertNull($result->released_at);
        $this->assertFalse($result->isAutoReleased());
    }

    public function test_auto_released_scope_returns_only_released(): void
    {
        $released = $this->booking('2026-06-08 03:00:00', '2026-06-08 04:00:00');
        $this->booking('2026-06-08 03:20:00', '2026-06-08 04:20:00'); // within grace, not released

        $this->artisan('bookings:auto-release')->assertSuccessful();

        $ids = Booking::autoReleased()->pluck('id')->all();
        $this->assertSame([$released->id], $ids);
    }
}
