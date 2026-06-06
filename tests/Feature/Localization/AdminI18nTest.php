<?php

declare(strict_types=1);

namespace Tests\Feature\Localization;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\AppSettingsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminI18nTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function superAdminEn(): User
    {
        $user = User::factory()->create(['locale' => 'en']);
        $user->roles()->sync([Role::where('code', 'super_admin')->firstOrFail()->id]);

        return $user;
    }

    public function test_user_list_renders_english(): void
    {
        $this->actingAs($this->superAdminEn())
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Work Unit')
            ->assertSee('All roles')
            ->assertDontSee('Semua peran');
    }

    public function test_unit_list_renders_english(): void
    {
        $this->actingAs($this->superAdminEn())
            ->get(route('admin.units.index'))
            ->assertOk()
            ->assertSee('Parent')
            ->assertSee('Add Unit')
            ->assertDontSee('Tambah Unit');
    }

    public function test_facility_list_renders_english(): void
    {
        $this->actingAs($this->superAdminEn())
            ->get(route('admin.facilities.index'))
            ->assertOk()
            ->assertSee('All categories')
            ->assertSee('Add Facility')
            ->assertDontSee('Semua kategori');
    }

    public function test_room_block_list_renders_english(): void
    {
        $this->actingAs($this->superAdminEn())
            ->get(route('admin.room-blocks.index'))
            ->assertOk()
            ->assertSee('Block Room')
            ->assertDontSee('Tidak ada blokir ruangan.');
    }

    public function test_activity_log_renders_english(): void
    {
        $this->actingAs($this->superAdminEn())
            ->get(route('admin.logs.index'))
            ->assertOk()
            ->assertSee('All modules')
            ->assertSee('Actor')
            ->assertDontSee('Semua modul');
    }

    public function test_settings_renders_english(): void
    {
        $this->seed(AppSettingsSeeder::class);

        $this->actingAs($this->superAdminEn())
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('Database Backup')
            ->assertSee('Download Backup')
            ->assertDontSee('Cadangan Basis Data');
    }

    public function test_utilization_dashboard_renders_english(): void
    {
        $this->actingAs($this->superAdminEn())
            ->get(route('admin.reports.utilization'))
            ->assertOk()
            ->assertSee('Average utilization')
            ->assertSee('Utilization per Room')
            ->assertDontSee('Utilisasi rata-rata');
    }
}
