<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Models\Booking;
use App\Models\BookingAttachment;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BookingAttachmentManageTest extends TestCase
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

    private function attachment(Booking $booking, string $path, string $name): BookingAttachment
    {
        Storage::disk('local_private')->put($path, 'X');

        return BookingAttachment::factory()->create([
            'booking_id' => $booking->id,
            'disk' => 'local_private',
            'path' => $path,
            'original_name' => $name,
        ]);
    }

    public function test_owner_can_upload_an_attachment(): void
    {
        $user = $this->userWithRole('requester');
        $booking = Booking::factory()->approved()->create(['requester_user_id' => $user->id]);

        $this->actingAs($user)
            ->post(route('bookings.attachments.store', $booking->id), [
                'attachment' => UploadedFile::fake()->create('agenda.pdf', 120, 'application/pdf'),
            ])
            ->assertRedirect(route('bookings.show', $booking->id));

        $this->assertDatabaseHas('booking_attachments', [
            'booking_id' => $booking->id,
            'original_name' => 'agenda.pdf',
            'disk' => 'local_private',
            'uploaded_by_user_id' => $user->id,
        ]);
        $row = BookingAttachment::where('booking_id', $booking->id)->firstOrFail();
        Storage::disk('local_private')->assertExists($row->path);
        $this->assertDatabaseHas('activity_logs', [
            'module' => 'bookings',
            'event' => 'attach',
            'actor_user_id' => $user->id,
        ]);
    }

    public function test_upload_requires_a_file(): void
    {
        $user = $this->userWithRole('requester');
        $booking = Booking::factory()->approved()->create(['requester_user_id' => $user->id]);

        $this->actingAs($user)
            ->post(route('bookings.attachments.store', $booking->id), [])
            ->assertSessionHasErrors('attachment');
    }

    public function test_upload_rejects_files_over_10mb(): void
    {
        $user = $this->userWithRole('requester');
        $booking = Booking::factory()->approved()->create(['requester_user_id' => $user->id]);

        $this->actingAs($user)
            ->post(route('bookings.attachments.store', $booking->id), [
                'attachment' => UploadedFile::fake()->create('big.pdf', 11000, 'application/pdf'),
            ])
            ->assertSessionHasErrors('attachment');
        $this->assertSame(0, BookingAttachment::where('booking_id', $booking->id)->count());
    }

    public function test_stranger_cannot_upload(): void
    {
        $stranger = $this->userWithRole('requester');
        $owner = User::factory()->create();
        $booking = Booking::factory()->approved()->create(['requester_user_id' => $owner->id]);

        $this->actingAs($stranger)
            ->post(route('bookings.attachments.store', $booking->id), [
                'attachment' => UploadedFile::fake()->create('x.pdf', 100, 'application/pdf'),
            ])
            ->assertForbidden();
    }

    public function test_cannot_upload_to_cancelled_booking(): void
    {
        $user = $this->userWithRole('requester');
        $booking = Booking::factory()->cancelled()->create(['requester_user_id' => $user->id]);

        $this->actingAs($user)
            ->post(route('bookings.attachments.store', $booking->id), [
                'attachment' => UploadedFile::fake()->create('x.pdf', 100, 'application/pdf'),
            ])
            ->assertForbidden();
    }

    public function test_owner_can_delete_an_attachment(): void
    {
        $user = $this->userWithRole('requester');
        $booking = Booking::factory()->approved()->create(['requester_user_id' => $user->id]);
        $attachment = $this->attachment($booking, 'booking-attachments/a.pdf', 'a.pdf');

        $this->actingAs($user)
            ->delete(route('bookings.attachments.destroy', [$booking->id, $attachment->id]))
            ->assertRedirect(route('bookings.show', $booking->id));

        $this->assertDatabaseMissing('booking_attachments', ['id' => $attachment->id]);
        Storage::disk('local_private')->assertMissing('booking-attachments/a.pdf');
        $this->assertDatabaseHas('activity_logs', ['module' => 'bookings', 'event' => 'detach']);
    }

    public function test_stranger_cannot_delete_an_attachment(): void
    {
        $stranger = $this->userWithRole('requester');
        $owner = User::factory()->create();
        $booking = Booking::factory()->approved()->create(['requester_user_id' => $owner->id]);
        $attachment = $this->attachment($booking, 'booking-attachments/b.pdf', 'b.pdf');

        $this->actingAs($stranger)
            ->delete(route('bookings.attachments.destroy', [$booking->id, $attachment->id]))
            ->assertForbidden();
        $this->assertDatabaseHas('booking_attachments', ['id' => $attachment->id]);
    }

    public function test_delete_attachment_from_another_booking_is_not_found(): void
    {
        $user = $this->userWithRole('requester');
        $bookingA = Booking::factory()->approved()->create(['requester_user_id' => $user->id]);
        $bookingB = Booking::factory()->approved()->create(['requester_user_id' => $user->id]);
        $attachmentB = $this->attachment($bookingB, 'booking-attachments/c.pdf', 'c.pdf');

        $this->actingAs($user)
            ->delete(route('bookings.attachments.destroy', [$bookingA->id, $attachmentB->id]))
            ->assertNotFound();
    }
}
