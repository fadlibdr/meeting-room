<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Models\Booking;
use App\Models\Role;
use App\Models\Room;
use App\Models\User;
use App\Notifications\ScheduledReportNotification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ScheduledReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Carbon::setTestNow('2026-06-15 03:00:00'); // a Monday
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function userWithRole(string $code): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->sync([Role::where('code', $code)->firstOrFail()->id]);

        return $user;
    }

    public function test_weekly_report_is_queued_to_report_viewers_only(): void
    {
        Storage::fake('local_private');
        Notification::fake();

        $gaAdmin = $this->userWithRole('ga_admin');     // has reports.view
        $requester = $this->userWithRole('requester');  // no reports.view

        $this->artisan('reports:send', ['--period' => 'weekly'])->assertSuccessful();

        Notification::assertSentTo($gaAdmin, ScheduledReportNotification::class);
        Notification::assertNotSentTo($requester, ScheduledReportNotification::class);

        // The XLSX was generated on the report disk.
        $this->assertNotEmpty(Storage::disk('local_private')->files('reports'));
    }

    public function test_monthly_period_runs(): void
    {
        Storage::fake('local_private');
        Notification::fake();
        $this->userWithRole('super_admin');

        $this->artisan('reports:send', ['--period' => 'monthly'])->assertSuccessful();

        Notification::assertSentTimes(ScheduledReportNotification::class, 1);
    }

    public function test_bi_feed_writes_a_csv_with_jakarta_labelled_times(): void
    {
        Storage::fake('local_private');

        $room = Room::factory()->create(['name' => 'Ruang Garuda']);
        Booking::factory()->approved()->create([
            'resource_id' => $room->id,
            'subject' => 'Rapat BI',
            'starts_at' => '2026-06-08 02:00:00', // 09:00 WIB
            'ends_at' => '2026-06-08 03:00:00',
        ]);

        $this->artisan('reports:bi-export')->assertSuccessful();

        Storage::disk('local_private')->assertExists('bi-exports/bookings-latest.csv');
        $csv = Storage::disk('local_private')->get('bi-exports/bookings-latest.csv');
        $this->assertStringContainsString('Rapat BI', $csv);
        $this->assertStringContainsString('2026-06-08 09:00', $csv); // Jakarta-labelled
    }
}
