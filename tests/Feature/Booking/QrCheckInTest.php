<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Role;
use App\Models\Room;
use App\Models\User;
use App\Support\BookingCheckInLink;
use Database\Seeders\AppSettingsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class QrCheckInTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AppSettingsSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);
        // 10:30 WIB (03:30 UTC) — inside a 03:00–04:00 UTC meeting.
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
    private function approvedBooking(array $attrs = []): Booking
    {
        return Booking::factory()->approved()->create(array_merge([
            'resource_id' => Room::factory(),
            'requester_user_id' => User::factory(),
            'starts_at' => '2026-06-08 03:00:00',
            'ends_at' => '2026-06-08 04:00:00',
        ], $attrs));
    }

    private function url(Booking $booking): string
    {
        return app(BookingCheckInLink::class)->signedUrl($booking);
    }

    public function test_valid_signed_url_checks_in_within_window(): void
    {
        $booking = $this->approvedBooking();

        $this->get($this->url($booking))
            ->assertOk()
            ->assertSee('Check-in Berhasil');

        $this->assertNotNull($booking->refresh()->checked_in_at);
        $this->assertDatabaseHas('activity_logs', ['event' => 'check-in']);
    }

    public function test_tampered_url_is_rejected(): void
    {
        $booking = $this->approvedBooking();
        $tampered = $this->url($booking).'x'; // corrupt the signature

        $this->get($tampered)->assertForbidden();
        $this->assertNull($booking->refresh()->checked_in_at);
    }

    public function test_expired_url_is_rejected(): void
    {
        $booking = $this->approvedBooking();
        $url = $this->url($booking); // expires at ends_at = 04:00

        Carbon::setTestNow('2026-06-08 05:00:00'); // past expiry

        $this->get($url)->assertForbidden();
        $this->assertNull($booking->refresh()->checked_in_at);
    }

    public function test_check_in_before_window_is_too_early(): void
    {
        $booking = $this->approvedBooking();
        $url = $this->url($booking);

        Carbon::setTestNow('2026-06-08 02:00:00'); // > 30 min before start

        $this->get($url)->assertStatus(422)->assertSee('Belum Waktunya');
        $this->assertNull($booking->refresh()->checked_in_at);
    }

    public function test_already_checked_in_is_idempotent(): void
    {
        $booking = $this->approvedBooking(['checked_in_at' => '2026-06-08 03:05:00']);
        $original = $booking->checked_in_at;

        $this->get($this->url($booking))->assertOk()->assertSee('Sudah Check-in');

        $this->assertEquals($original, $booking->refresh()->checked_in_at);
    }

    public function test_released_booking_cannot_be_checked_in(): void
    {
        $booking = $this->approvedBooking([
            'status' => BookingStatus::Cancelled->value,
            'released_at' => '2026-06-08 03:20:00',
        ]);

        $this->get($this->url($booking))->assertStatus(422)->assertSee('Tidak Dapat Check-in');
        $this->assertNull($booking->refresh()->checked_in_at);
    }

    public function test_qr_check_in_cancels_auto_release_eligibility(): void
    {
        $booking = $this->approvedBooking();

        // Self-check-in, then the auto-release sweep must skip this booking.
        $this->get($this->url($booking))->assertOk();
        $this->artisan('bookings:auto-release')->assertSuccessful();

        $booking->refresh();
        $this->assertNotNull($booking->checked_in_at);
        $this->assertNull($booking->released_at);
        $this->assertSame(BookingStatus::Approved, $booking->status);
    }

    public function test_booking_show_renders_the_qr_for_eligible_booking(): void
    {
        $owner = User::factory()->create();
        $owner->roles()->sync([Role::where('code', 'requester')->firstOrFail()->id]);
        $booking = $this->approvedBooking(['requester_user_id' => $owner->id]);

        $this->actingAs($owner)
            ->get(route('bookings.show', $booking))
            ->assertOk()
            ->assertSee('QR Check-in')
            ->assertSee('<svg', false);
    }
}
