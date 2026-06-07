<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Models\Booking;
use App\Models\Role;
use App\Models\Room;
use App\Models\User;
use App\Notifications\BookingApprovedNotification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingCalendarDownloadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function requester(): User
    {
        $user = User::factory()->create();
        $user->roles()->sync([Role::where('code', 'requester')->firstOrFail()->id]);

        return $user;
    }

    private function bookingFor(User $owner): Booking
    {
        $room = Room::factory()->create(['name' => 'Ruang Garuda']);

        return Booking::factory()->create([
            'resource_id' => $room->id,
            'requester_user_id' => $owner->id,
            'status' => 'approved',
            'starts_at' => '2026-06-08 03:00:00',
            'ends_at' => '2026-06-08 04:00:00',
        ]);
    }

    public function test_owner_can_download_the_ics(): void
    {
        $owner = $this->requester();
        $booking = $this->bookingFor($owner);

        $response = $this->actingAs($owner)->get(route('bookings.calendar', $booking->id));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/calendar; charset=utf-8');
        $this->assertStringContainsString('BEGIN:VCALENDAR', (string) $response->getContent());
        $response->assertHeader('content-disposition', 'attachment; filename="reservasi-'.$booking->booking_code.'.ics"');
    }

    public function test_unauthorised_user_gets_403(): void
    {
        $owner = $this->requester();
        $stranger = $this->requester(); // a different requester — not the owner, no view-all
        $booking = $this->bookingFor($owner);

        $this->actingAs($stranger)
            ->get(route('bookings.calendar', $booking->id))
            ->assertForbidden();
    }

    public function test_approval_mail_attaches_the_ics(): void
    {
        $owner = $this->requester();
        $booking = $this->bookingFor($owner);

        $mail = (new BookingApprovedNotification($booking))->toMail($owner);

        $this->assertNotEmpty($mail->rawAttachments);
        $attachment = $mail->rawAttachments[0];
        $this->assertSame('reservasi-'.$booking->booking_code.'.ics', $attachment['name']);
        $this->assertStringContainsString('BEGIN:VCALENDAR', $attachment['data']);
        $this->assertStringContainsString('method=PUBLISH', $attachment['options']['mime']);
    }
}
