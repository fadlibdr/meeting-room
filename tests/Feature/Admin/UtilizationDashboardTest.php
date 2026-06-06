<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Livewire\Admin\UtilizationDashboard;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UtilizationDashboardTest extends TestCase
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
        $user->roles()->sync([Role::where('code', $roleCode)->firstOrFail()->id]);

        return $user;
    }

    public function test_reports_viewer_can_open_utilization_dashboard(): void
    {
        $admin = $this->userWithRole('ga_admin'); // has reports.view
        $this->assertTrue($admin->hasPermission('reports.view'));

        $this->actingAs($admin)
            ->get(route('admin.reports.utilization'))
            ->assertOk()
            ->assertSee('Utilisasi');
    }

    public function test_user_without_reports_view_is_forbidden(): void
    {
        $requester = $this->userWithRole('requester');
        $this->assertFalse($requester->hasPermission('reports.view'));

        $this->actingAs($requester)
            ->get(route('admin.reports.utilization'))
            ->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.reports.utilization'))
            ->assertRedirect(route('login'));
    }

    public function test_component_renders_and_preset_updates_range(): void
    {
        $admin = $this->userWithRole('ga_admin');

        Livewire::actingAs($admin)
            ->test(UtilizationDashboard::class)
            ->assertSet('preset', 30)
            ->call('applyPreset', 7)
            ->assertSet('preset', 7)
            ->assertOk();
    }

    public function test_editing_dates_clears_preset(): void
    {
        $admin = $this->userWithRole('ga_admin');

        Livewire::actingAs($admin)
            ->test(UtilizationDashboard::class)
            ->set('from', '2026-05-01')
            ->assertSet('preset', null);
    }
}
