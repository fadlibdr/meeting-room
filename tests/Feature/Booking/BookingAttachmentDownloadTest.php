<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Models\Booking;
use App\Models\BookingAttachment;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BookingAttachmentDownloadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('local_private');
    }

    private function userWithRole(string $roleCode): User
    {
        $user = User::factory()->create();
        $role = Role::where('code', $roleCode)->firstOrFail();
        $user->roles()->sync([$role->id]);

        return $user;
    }

    private function attachmentFor(Booking $booking, string $path, string $name): BookingAttachment
    {
        Storage::disk('local_private')->put($path, 'FILE CONTENT');

        return BookingAttachment::factory()->create([
            'booking_id' => $booking->id,
            'disk' => 'local_private',
            'path' => $path,
            'original_name' => $name,
        ]);
    }

    public function test_owner_can_download_their_attachment(): void
    {
        $user = $this->userWithRole('requester');
        $booking = Booking::factory()->create(['requester_user_id' => $user->id]);
        $attachment = $this->attachmentFor($booking, 'booking-attachments/agenda.pdf', 'agenda.pdf');

        $response = $this->actingAs($user)
            ->get(route('bookings.attachments.download', [$booking->id, $attachment->id]));

        $response->assertDownload('agenda.pdf');
        $this->assertSame('FILE CONTENT', $response->streamedContent());
    }

    public function test_view_all_admin_can_download_any_attachment(): void
    {
        $admin = $this->userWithRole('super_admin');
        $owner = User::factory()->create();
        $booking = Booking::factory()->create(['requester_user_id' => $owner->id]);
        $attachment = $this->attachmentFor($booking, 'booking-attachments/x.pdf', 'x.pdf');

        $this->actingAs($admin)
            ->get(route('bookings.attachments.download', [$booking->id, $attachment->id]))
            ->assertDownload('x.pdf');
    }

    public function test_non_viewer_cannot_download(): void
    {
        $stranger = $this->userWithRole('requester');
        $owner = User::factory()->create();
        $booking = Booking::factory()->create(['requester_user_id' => $owner->id]);
        $attachment = $this->attachmentFor($booking, 'booking-attachments/y.pdf', 'y.pdf');

        $this->actingAs($stranger)
            ->get(route('bookings.attachments.download', [$booking->id, $attachment->id]))
            ->assertForbidden();
    }

    public function test_attachment_from_another_booking_is_not_found(): void
    {
        $user = $this->userWithRole('requester');
        $bookingA = Booking::factory()->create(['requester_user_id' => $user->id]);
        $bookingB = Booking::factory()->create(['requester_user_id' => $user->id]);
        $attachmentB = $this->attachmentFor($bookingB, 'booking-attachments/z.pdf', 'z.pdf');

        $this->actingAs($user)
            ->get(route('bookings.attachments.download', [$bookingA->id, $attachmentB->id]))
            ->assertNotFound();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $booking = Booking::factory()->create();
        $attachment = $this->attachmentFor($booking, 'booking-attachments/g.pdf', 'g.pdf');

        $this->get(route('bookings.attachments.download', [$booking->id, $attachment->id]))
            ->assertRedirect(route('login'));
    }
}
