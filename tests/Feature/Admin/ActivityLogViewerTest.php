<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Livewire\Admin\ActivityLogViewer;
use App\Models\ActivityLog;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class ActivityLogViewerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function userWithRole(string $roleCode): User
    {
        $user = User::factory()->create();
        $role = Role::where('code', $roleCode)->firstOrFail();
        $user->roles()->sync([$role->id]);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $attrs
     */
    private function log(array $attrs = []): ActivityLog
    {
        return ActivityLog::create(array_merge([
            'actor_user_id' => null,
            'module' => 'bookings',
            'event' => 'submit',
            'description' => 'Entri log uji',
            'created_at' => Carbon::parse('2026-05-15 10:00:00'),
        ], $attrs));
    }

    public function test_super_admin_can_view_logs(): void
    {
        $this->actingAs($this->userWithRole('super_admin'))->get(route('admin.logs.index'))->assertOk();
    }

    public function test_system_admin_can_view_logs(): void
    {
        $this->actingAs($this->userWithRole('system_admin'))->get(route('admin.logs.index'))->assertOk();
    }

    public function test_ga_admin_cannot_view_logs(): void
    {
        $this->actingAs($this->userWithRole('ga_admin'))->get(route('admin.logs.index'))->assertForbidden();
    }

    public function test_requester_cannot_view_logs(): void
    {
        $this->actingAs($this->userWithRole('requester'))->get(route('admin.logs.index'))->assertForbidden();
    }

    public function test_viewer_lists_log_entries(): void
    {
        $this->log(['description' => 'Booking ABC dibuat']);
        $this->actingAs($this->userWithRole('super_admin'));

        Livewire::test(ActivityLogViewer::class)->assertSee('Booking ABC dibuat');
    }

    public function test_filter_by_module(): void
    {
        $this->log(['module' => 'rooms', 'description' => 'Ruang diblokir Garuda']);
        $this->log(['module' => 'bookings', 'description' => 'Reservasi dibuat Elang']);
        $this->actingAs($this->userWithRole('super_admin'));

        Livewire::test(ActivityLogViewer::class)
            ->set('moduleFilter', 'rooms')
            ->assertSee('Ruang diblokir Garuda')
            ->assertDontSee('Reservasi dibuat Elang');
    }

    public function test_filter_by_event(): void
    {
        $this->log(['event' => 'block-create', 'description' => 'Blokir dibuat XYZ']);
        $this->log(['event' => 'submit', 'description' => 'Diajukan ABC']);
        $this->actingAs($this->userWithRole('super_admin'));

        Livewire::test(ActivityLogViewer::class)
            ->set('eventFilter', 'block-create')
            ->assertSee('Blokir dibuat XYZ')
            ->assertDontSee('Diajukan ABC');
    }

    public function test_search_by_description(): void
    {
        $this->log(['description' => 'Pemeliharaan AC ruang Garuda']);
        $this->log(['description' => 'Reservasi rapat tim Elang']);
        $this->actingAs($this->userWithRole('super_admin'));

        Livewire::test(ActivityLogViewer::class)
            ->set('search', 'Garuda')
            ->assertSee('Pemeliharaan AC ruang Garuda')
            ->assertDontSee('Reservasi rapat tim Elang');
    }

    public function test_date_range_filter(): void
    {
        $this->log(['description' => 'Peristiwa Lama', 'created_at' => Carbon::parse('2026-01-01 10:00:00')]);
        $this->log(['description' => 'Peristiwa Baru', 'created_at' => Carbon::parse('2026-05-20 10:00:00')]);
        $this->actingAs($this->userWithRole('super_admin'));

        Livewire::test(ActivityLogViewer::class)
            ->set('dateFrom', '2026-05-01')
            ->assertSee('Peristiwa Baru')
            ->assertDontSee('Peristiwa Lama');
    }

    public function test_clear_filters_resets_state(): void
    {
        $this->log(['module' => 'rooms', 'description' => 'Log ruang']);
        $this->actingAs($this->userWithRole('super_admin'));

        Livewire::test(ActivityLogViewer::class)
            ->set('moduleFilter', 'rooms')
            ->set('search', 'xyz')
            ->call('clearFilters')
            ->assertSet('moduleFilter', '')
            ->assertSet('search', '');
    }
}
