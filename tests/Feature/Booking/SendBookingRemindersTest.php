<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Models\Booking;
use App\Models\User;
use App\Notifications\BookingReminderNotification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SendBookingRemindersTest extends TestCase
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

    public function test_sends_reminder_for_approved_booking_within_window(): void
    {
        Notification::fake();
        $requester = User::factory()->create();
        $booking = Booking::factory()->approved()->create([
            'requester_user_id' => $requester->id,
            'starts_at' => Carbon::parse('2026-05-05 15:00:00'),
            'ends_at' => Carbon::parse('2026-05-05 16:00:00'),
            'reminder_sent_at' => null,
        ]);

        $this->artisan('bookings:send-reminders')->assertSuccessful();

        Notification::assertSentTo(
            $requester,
            BookingReminderNotification::class,
            fn (BookingReminderNotification $n) => $n->toArray($requester)['booking_id'] === $booking->id,
        );
        $this->assertNotNull($booking->fresh()->reminder_sent_at);
    }

    public function test_does_not_remind_booking_outside_window(): void
    {
        Notification::fake();
        $requester = User::factory()->create();
        Booking::factory()->approved()->create([
            'requester_user_id' => $requester->id,
            'starts_at' => Carbon::parse('2026-05-10 15:00:00'),
            'ends_at' => Carbon::parse('2026-05-10 16:00:00'),
            'reminder_sent_at' => null,
        ]);

        $this->artisan('bookings:send-reminders')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_does_not_remind_past_booking(): void
    {
        Notification::fake();
        $requester = User::factory()->create();
        Booking::factory()->approved()->create([
            'requester_user_id' => $requester->id,
            'starts_at' => Carbon::parse('2026-05-05 07:00:00'),
            'ends_at' => Carbon::parse('2026-05-05 08:00:00'),
            'reminder_sent_at' => null,
        ]);

        $this->artisan('bookings:send-reminders')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_does_not_remind_unapproved_booking(): void
    {
        Notification::fake();
        $requester = User::factory()->create();
        Booking::factory()->submitted()->create([
            'requester_user_id' => $requester->id,
            'starts_at' => Carbon::parse('2026-05-05 15:00:00'),
            'ends_at' => Carbon::parse('2026-05-05 16:00:00'),
            'reminder_sent_at' => null,
        ]);

        $this->artisan('bookings:send-reminders')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_does_not_resend_when_already_reminded(): void
    {
        Notification::fake();
        $requester = User::factory()->create();
        Booking::factory()->approved()->create([
            'requester_user_id' => $requester->id,
            'starts_at' => Carbon::parse('2026-05-05 15:00:00'),
            'ends_at' => Carbon::parse('2026-05-05 16:00:00'),
            'reminder_sent_at' => Carbon::parse('2026-05-05 08:00:00'),
        ]);

        $this->artisan('bookings:send-reminders')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_stamp_prevents_double_send_across_runs(): void
    {
        $requester = User::factory()->create();
        $booking = Booking::factory()->approved()->create([
            'requester_user_id' => $requester->id,
            'starts_at' => Carbon::parse('2026-05-05 15:00:00'),
            'ends_at' => Carbon::parse('2026-05-05 16:00:00'),
            'reminder_sent_at' => null,
        ]);

        Notification::fake();
        $this->artisan('bookings:send-reminders')->assertSuccessful();
        Notification::assertSentTo($requester, BookingReminderNotification::class);
        $this->assertNotNull($booking->fresh()->reminder_sent_at);

        Notification::fake();
        $this->artisan('bookings:send-reminders')->assertSuccessful();
        Notification::assertNothingSent();
    }
}
