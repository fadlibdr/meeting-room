<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire\Admin;

use App\Livewire\Admin\SettingsManager;
use App\Models\AppSetting;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AppSettingsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SettingsManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(AppSettingsSeeder::class);
    }

    private function userWithRole(string $roleCode): User
    {
        $user = User::factory()->create();
        $role = Role::where('code', $roleCode)->firstOrFail();
        $user->roles()->sync([$role->id]);

        return $user;
    }

    // ─── AUTHORIZATION ──────────────────────────────────────────────

    public function test_super_admin_can_render_settings_page(): void
    {
        $user = $this->userWithRole('super_admin');

        Livewire::actingAs($user)
            ->test(SettingsManager::class)
            ->assertOk()
            ->assertSee('Pengaturan Sistem');
    }

    public function test_system_admin_can_render_settings_page(): void
    {
        $user = $this->userWithRole('system_admin');

        Livewire::actingAs($user)
            ->test(SettingsManager::class)
            ->assertOk();
    }

    public function test_requester_cannot_render_settings_page(): void
    {
        $user = $this->userWithRole('requester');

        Livewire::actingAs($user)
            ->test(SettingsManager::class)
            ->assertForbidden();
    }

    public function test_ga_admin_cannot_render_settings_page(): void
    {
        $user = $this->userWithRole('ga_admin');

        Livewire::actingAs($user)
            ->test(SettingsManager::class)
            ->assertForbidden();
    }

    public function test_unauthenticated_route_redirects_to_login(): void
    {
        $this->get('/admin/settings')->assertRedirect('/login');
    }

    // ─── EDIT FLOW ──────────────────────────────────────────────────

    public function test_super_admin_can_start_editing_setting(): void
    {
        $user = $this->userWithRole('super_admin');
        $setting = AppSetting::where('key', 'booking.default_buffer_minutes')->firstOrFail();

        Livewire::actingAs($user)
            ->test(SettingsManager::class)
            ->call('startEdit', $setting->id)
            ->assertSet('editingId', $setting->id)
            ->assertSet('editValue', 15);
    }

    public function test_super_admin_can_cancel_edit(): void
    {
        $user = $this->userWithRole('super_admin');
        $setting = AppSetting::where('key', 'booking.default_buffer_minutes')->firstOrFail();

        Livewire::actingAs($user)
            ->test(SettingsManager::class)
            ->call('startEdit', $setting->id)
            ->call('cancelEdit')
            ->assertSet('editingId', null)
            ->assertSet('editValue', null);
    }

    public function test_super_admin_can_save_integer_setting(): void
    {
        $user = $this->userWithRole('super_admin');
        $setting = AppSetting::where('key', 'booking.default_buffer_minutes')->firstOrFail();

        Livewire::actingAs($user)
            ->test(SettingsManager::class)
            ->call('startEdit', $setting->id)
            ->set('editValue', 30)
            ->call('save')
            ->assertSet('editingId', null)
            ->assertSet('errorMessage', null);

        $this->assertDatabaseHas('app_settings', [
            'key' => 'booking.default_buffer_minutes',
            'value' => '30',
            'updated_by_user_id' => $user->id,
        ]);
    }

    public function test_super_admin_can_save_boolean_setting(): void
    {
        $user = $this->userWithRole('super_admin');
        $setting = AppSetting::where('key', 'system.maintenance_mode')->firstOrFail();

        Livewire::actingAs($user)
            ->test(SettingsManager::class)
            ->call('startEdit', $setting->id)
            ->set('editValue', true)
            ->call('save')
            ->assertSet('editingId', null);

        $this->assertDatabaseHas('app_settings', [
            'key' => 'system.maintenance_mode',
            'value' => '1',
        ]);
    }

    public function test_save_shows_success_message(): void
    {
        $user = $this->userWithRole('super_admin');
        $setting = AppSetting::where('key', 'booking.default_buffer_minutes')->firstOrFail();

        $component = Livewire::actingAs($user)
            ->test(SettingsManager::class)
            ->call('startEdit', $setting->id)
            ->set('editValue', 20)
            ->call('save');

        $this->assertStringContainsString('berhasil diperbarui', $component->get('successMessage'));
    }

    // ─── VALIDATION ─────────────────────────────────────────────────

    public function test_cannot_save_negative_integer(): void
    {
        $user = $this->userWithRole('super_admin');
        $setting = AppSetting::where('key', 'booking.default_buffer_minutes')->firstOrFail();

        Livewire::actingAs($user)
            ->test(SettingsManager::class)
            ->call('startEdit', $setting->id)
            ->set('editValue', -5)
            ->call('save')
            ->assertSet('editingId', $setting->id)  // Still editing
            ->assertSet('errorMessage', 'Nilai harus berupa angka non-negatif.');

        // DB unchanged
        $this->assertDatabaseHas('app_settings', [
            'key' => 'booking.default_buffer_minutes',
            'value' => '15',  // original
        ]);
    }

    public function test_cannot_save_non_numeric_for_integer_setting(): void
    {
        $user = $this->userWithRole('super_admin');
        $setting = AppSetting::where('key', 'booking.default_buffer_minutes')->firstOrFail();

        Livewire::actingAs($user)
            ->test(SettingsManager::class)
            ->call('startEdit', $setting->id)
            ->set('editValue', 'abc')
            ->call('save')
            ->assertSet('editingId', $setting->id)
            ->assertSet('errorMessage', 'Nilai harus berupa angka non-negatif.');
    }

    // ─── EDGE CASES ─────────────────────────────────────────────────

    public function test_cannot_start_edit_on_read_only_setting(): void
    {
        $user = $this->userWithRole('super_admin');
        $readOnly = AppSetting::factory()->readOnly()->create([
            'key' => 'system.locked_value',
            'label' => 'Locked',
            'data_type' => 'string',
            'value' => 'frozen',
        ]);

        Livewire::actingAs($user)
            ->test(SettingsManager::class)
            ->call('startEdit', $readOnly->id)
            ->assertForbidden();
    }

    public function test_save_does_nothing_when_nothing_being_edited(): void
    {
        $user = $this->userWithRole('super_admin');

        Livewire::actingAs($user)
            ->test(SettingsManager::class)
            ->call('save')  // No startEdit first
            ->assertSet('editingId', null)
            ->assertSet('errorMessage', null);
    }

    public function test_grouped_settings_displayed_on_render(): void
    {
        $user = $this->userWithRole('super_admin');

        Livewire::actingAs($user)
            ->test(SettingsManager::class)
            ->assertSee('Waktu Jeda antar Booking (menit)')
            ->assertSee('Mode Pemeliharaan');
    }
}
