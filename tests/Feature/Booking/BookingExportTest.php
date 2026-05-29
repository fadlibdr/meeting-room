<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Livewire\Booking\BookingList;
use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class BookingExportTest extends TestCase
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

    private function userWithRole(string $roleCode): User
    {
        $user = User::factory()->create();
        $role = Role::where('code', $roleCode)->firstOrFail();
        $user->roles()->sync([$role->id]);

        return $user;
    }

    public function test_export_triggers_a_csv_download(): void
    {
        $user = $this->userWithRole('requester');
        Booking::factory()->approved()->create(['requester_user_id' => $user->id]);

        Livewire::actingAs($user)
            ->test(BookingList::class)
            ->call('export')
            ->assertFileDownloaded();
    }

    public function test_export_writes_an_audit_log_entry(): void
    {
        $user = $this->userWithRole('requester');
        Booking::factory()->approved()->create(['requester_user_id' => $user->id]);

        Livewire::actingAs($user)->test(BookingList::class)->call('export');

        $log = ActivityLog::query()
            ->where('module', 'bookings')
            ->where('event', 'export')
            ->where('actor_user_id', $user->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame('csv', $log->context['format']);
    }

    public function test_export_row_count_respects_own_scope(): void
    {
        $requester = $this->userWithRole('requester');
        $other = User::factory()->create();
        Booking::factory()->approved()->create(['requester_user_id' => $requester->id]);
        Booking::factory()->approved()->create(['requester_user_id' => $other->id]);

        Livewire::actingAs($requester)->test(BookingList::class)->call('export');

        $log = ActivityLog::query()->where('event', 'export')->latest('id')->firstOrFail();
        $this->assertSame(1, $log->context['row_count']);
    }

    public function test_export_row_count_includes_all_for_view_all(): void
    {
        $admin = $this->userWithRole('super_admin');
        $a = User::factory()->create();
        $b = User::factory()->create();
        Booking::factory()->approved()->create(['requester_user_id' => $a->id]);
        Booking::factory()->approved()->create(['requester_user_id' => $b->id]);

        Livewire::actingAs($admin)->test(BookingList::class)->call('export');

        $log = ActivityLog::query()->where('event', 'export')->latest('id')->firstOrFail();
        $this->assertSame(2, $log->context['row_count']);
    }

    public function test_export_row_count_reflects_status_filter(): void
    {
        $admin = $this->userWithRole('super_admin');
        $u = User::factory()->create();
        Booking::factory()->approved()->create(['requester_user_id' => $u->id]);
        Booking::factory()->submitted()->create(['requester_user_id' => $u->id]);

        Livewire::actingAs($admin)
            ->test(BookingList::class)
            ->set('statusFilter', 'approved')
            ->call('export');

        $log = ActivityLog::query()->where('event', 'export')->latest('id')->firstOrFail();
        $this->assertSame(1, $log->context['row_count']);
        $this->assertSame('approved', $log->context['filters']['status']);
    }

    public function test_export_button_is_visible_on_the_list(): void
    {
        $user = $this->userWithRole('requester');

        Livewire::actingAs($user)->test(BookingList::class)->assertSee('Export CSV');
    }
}
