<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Actions\DeleteBookingAction;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\BookingAttachment;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BookingDeleteAttachmentCleanupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_deleting_a_draft_booking_removes_its_attachment_files(): void
    {
        Storage::fake('local_private');

        $booking = Booking::factory()->create(['status' => BookingStatus::Draft]);
        $attachment = BookingAttachment::factory()->create([
            'booking_id' => $booking->id,
            'disk' => 'local_private',
            'path' => 'attachments/cleanup-test.pdf',
        ]);
        Storage::disk('local_private')->put($attachment->path, 'pdf-bytes');
        Storage::disk('local_private')->assertExists($attachment->path);

        app(DeleteBookingAction::class)->execute($booking, User::factory()->create());

        $this->assertDatabaseMissing('bookings', ['id' => $booking->id]);
        $this->assertDatabaseMissing('booking_attachments', ['id' => $attachment->id]);
        Storage::disk('local_private')->assertMissing($attachment->path);
    }

    public function test_deleting_a_draft_booking_without_attachments_succeeds(): void
    {
        Storage::fake('local_private');

        $booking = Booking::factory()->create(['status' => BookingStatus::Draft]);

        app(DeleteBookingAction::class)->execute($booking, User::factory()->create());

        $this->assertDatabaseMissing('bookings', ['id' => $booking->id]);
    }
}
