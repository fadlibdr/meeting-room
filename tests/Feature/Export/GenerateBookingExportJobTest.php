<?php

declare(strict_types=1);

namespace Tests\Feature\Export;

use App\Enums\ExportFormat;
use App\Enums\ExportStatus;
use App\Jobs\GenerateBookingExportJob;
use App\Models\Booking;
use App\Models\Export;
use App\Models\Room;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\ExportReadyNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GenerateBookingExportJobTest extends TestCase
{
    use RefreshDatabase;

    private function bookingFor(User $owner, string $subject = 'Rapat'): Booking
    {
        return Booking::factory()->approved()->create([
            'resource_id' => Room::factory(),
            'requester_unit_id' => Unit::factory(),
            'requester_user_id' => $owner->id,
            'subject' => $subject,
        ]);
    }

    private function runJob(Export $export): void
    {
        app()->call([new GenerateBookingExportJob($export), 'handle']);
    }

    public function test_job_writes_file_marks_completed_and_notifies(): void
    {
        Storage::fake(Export::DISK);
        Notification::fake();

        $user = User::factory()->create();
        $this->bookingFor($user, 'Rapat A');
        $this->bookingFor($user, 'Rapat B');

        $export = Export::factory()->create([
            'user_id' => $user->id,
            'format' => ExportFormat::Xlsx,
            'scope' => 'own',
            'status' => ExportStatus::Pending,
            'filters' => [],
        ]);

        $this->runJob($export);

        $export->refresh();
        $this->assertSame(ExportStatus::Completed, $export->status);
        $this->assertSame(2, $export->row_count);
        $this->assertNotNull($export->path);
        $this->assertNotNull($export->completed_at);
        $this->assertTrue($export->expires_at?->isFuture());
        Storage::disk(Export::DISK)->assertExists($export->path);

        Notification::assertSentTo($user, ExportReadyNotification::class);
    }

    public function test_own_scope_excludes_other_users_bookings(): void
    {
        Storage::fake(Export::DISK);
        Notification::fake();

        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->bookingFor($user, 'Mine');
        $this->bookingFor($other, 'Theirs');

        $export = Export::factory()->create([
            'user_id' => $user->id,
            'format' => ExportFormat::Csv,
            'scope' => 'own',
            'status' => ExportStatus::Pending,
            'filters' => [],
        ]);

        $this->runJob($export);

        $this->assertSame(1, $export->refresh()->row_count);
    }

    public function test_all_scope_includes_every_booking(): void
    {
        Storage::fake(Export::DISK);
        Notification::fake();

        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->bookingFor($user, 'Mine');
        $this->bookingFor($other, 'Theirs');

        $export = Export::factory()->create([
            'user_id' => $user->id,
            'format' => ExportFormat::Csv,
            'scope' => 'all',
            'status' => ExportStatus::Pending,
            'filters' => [],
        ]);

        $this->runJob($export);

        $this->assertSame(2, $export->refresh()->row_count);
    }
}
