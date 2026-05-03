<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\AppSetting;
use App\Models\Role;
use App\Models\User;
use App\Policies\AppSettingPolicy;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppSettingPolicyTest extends TestCase
{
    use RefreshDatabase;

    private AppSettingPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->policy = new AppSettingPolicy;
    }

    private function createUserWithRole(string $roleCode): User
    {
        $user = User::factory()->create();
        $role = Role::where('code', $roleCode)->firstOrFail();
        $user->roles()->sync([$role->id]);

        return $user;
    }

    public function test_super_admin_can_view_any(): void
    {
        $user = $this->createUserWithRole('super_admin');
        $this->assertTrue($this->policy->viewAny($user));
    }

    public function test_super_admin_can_view_setting(): void
    {
        $user = $this->createUserWithRole('super_admin');
        $setting = AppSetting::factory()->create(['key' => 'test_key', 'label' => 'Test']);
        $this->assertTrue($this->policy->view($user, $setting));
    }

    public function test_super_admin_can_update_editable_setting(): void
    {
        $user = $this->createUserWithRole('super_admin');
        $setting = AppSetting::factory()->create([
            'key' => 'test_editable',
            'label' => 'Test',
            'is_editable' => true,
        ]);
        $this->assertTrue($this->policy->update($user, $setting));
    }

    public function test_super_admin_cannot_update_read_only_setting(): void
    {
        $user = $this->createUserWithRole('super_admin');
        $setting = AppSetting::factory()->readOnly()->create([
            'key' => 'test_readonly',
            'label' => 'Test',
        ]);
        $this->assertFalse($this->policy->update($user, $setting));
    }

    public function test_system_admin_can_view_any(): void
    {
        $user = $this->createUserWithRole('system_admin');
        $this->assertTrue($this->policy->viewAny($user));
    }

    public function test_system_admin_can_update_editable_setting(): void
    {
        $user = $this->createUserWithRole('system_admin');
        $setting = AppSetting::factory()->create([
            'key' => 'test_editable',
            'label' => 'Test',
            'is_editable' => true,
        ]);
        $this->assertTrue($this->policy->update($user, $setting));
    }

    public function test_system_admin_cannot_update_read_only_setting(): void
    {
        $user = $this->createUserWithRole('system_admin');
        $setting = AppSetting::factory()->readOnly()->create([
            'key' => 'test_readonly',
            'label' => 'Test',
        ]);
        $this->assertFalse($this->policy->update($user, $setting));
    }

    public function test_ga_admin_cannot_view_any(): void
    {
        $user = $this->createUserWithRole('ga_admin');
        $this->assertFalse($this->policy->viewAny($user));
    }

    public function test_ga_admin_cannot_update(): void
    {
        $user = $this->createUserWithRole('ga_admin');
        $setting = AppSetting::factory()->create(['key' => 'test_key', 'label' => 'Test']);
        $this->assertFalse($this->policy->update($user, $setting));
    }

    public function test_unit_approver_cannot_view_any(): void
    {
        $user = $this->createUserWithRole('unit_approver');
        $this->assertFalse($this->policy->viewAny($user));
    }

    public function test_requester_cannot_view_any(): void
    {
        $user = $this->createUserWithRole('requester');
        $this->assertFalse($this->policy->viewAny($user));
    }

    public function test_requester_cannot_update(): void
    {
        $user = $this->createUserWithRole('requester');
        $setting = AppSetting::factory()->create(['key' => 'test_key', 'label' => 'Test']);
        $this->assertFalse($this->policy->update($user, $setting));
    }
}
