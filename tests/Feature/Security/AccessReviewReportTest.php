<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Livewire\Admin\AccessReviewReport;
use App\Models\Role;
use App\Models\User;
use App\Services\SettingsService;
use Database\Seeders\AppSettingsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Release E — access-review report (SOC 2 CC6.2/CC6.3 / ISO 27001 A.5.18).
 */
class AccessReviewReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(AppSettingsSeeder::class);
    }

    private function userWithRole(string $code): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->attach(Role::where('code', $code)->firstOrFail()->id, [
            'is_primary' => true, 'assigned_at' => now(),
        ]);

        return $user->fresh();
    }

    public function test_non_privileged_user_cannot_view(): void
    {
        Livewire::actingAs($this->userWithRole('requester'))
            ->test(AccessReviewReport::class)
            ->assertForbidden();
    }

    public function test_admin_sees_users_and_inactive_flag(): void
    {
        $admin = $this->userWithRole('super_admin');
        app(SettingsService::class)->set('security.inactive_account_days', '30');

        $stale = User::factory()->create(['name' => 'Stale Sam', 'is_active' => true]);
        $stale->forceFill(['last_login_at' => now()->subDays(60)])->save();

        $recent = User::factory()->create(['name' => 'Recent Rina', 'is_active' => true]);
        $recent->forceFill(['last_login_at' => now()->subDays(3)])->save();

        Livewire::actingAs($admin)
            ->test(AccessReviewReport::class)
            ->assertOk()
            ->assertSee('Stale Sam')
            ->assertSee('Recent Rina')
            ->assertSee('(tidak aktif)'); // the stale account is flagged
    }

    public function test_export_returns_a_csv_download(): void
    {
        $admin = $this->userWithRole('super_admin');

        Livewire::actingAs($admin)
            ->test(AccessReviewReport::class)
            ->call('export')
            ->assertFileDownloaded();
    }

    public function test_search_filters_by_name(): void
    {
        $admin = $this->userWithRole('super_admin');
        User::factory()->create(['name' => 'Findable Fen', 'is_active' => true]);
        User::factory()->create(['name' => 'Hidden Hugo', 'is_active' => true]);

        Livewire::actingAs($admin)
            ->test(AccessReviewReport::class)
            ->set('search', 'Findable')
            ->assertSee('Findable Fen')
            ->assertDontSee('Hidden Hugo');
    }
}
