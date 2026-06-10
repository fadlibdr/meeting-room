<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Livewire\Admin\RoleManager;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RoleManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function admin(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->sync([Role::where('code', 'super_admin')->firstOrFail()->id]);

        return $user;
    }

    public function test_page_requires_roles_view_permission(): void
    {
        $requester = User::factory()->create(['is_active' => true]);
        $requester->roles()->sync([Role::where('code', 'requester')->firstOrFail()->id]);

        $this->actingAs($requester)->get(route('admin.roles.index'))->assertForbidden();
        $this->actingAs($this->admin())->get(route('admin.roles.index'))->assertOk();
    }

    public function test_can_create_a_role_with_selected_permissions(): void
    {
        $perm = Permission::where('code', 'bookings.view')->firstOrFail();

        Livewire::actingAs($this->admin())
            ->test(RoleManager::class)
            ->call('newRole')
            ->set('name', 'Auditor')
            ->set('code', 'auditor')
            ->set('scope', 'support')
            ->call('togglePermission', $perm->id)
            ->call('save')
            ->assertHasNoErrors();

        $role = Role::where('code', 'auditor')->firstOrFail();
        $this->assertFalse($role->is_system);
        $this->assertTrue($role->permissions()->where('code', 'bookings.view')->exists());
    }

    public function test_editing_role_permissions_updates_the_matrix(): void
    {
        $role = Role::create(['code' => 'temp', 'name' => 'Temp', 'scope' => 'operational', 'is_system' => false, 'is_active' => true]);
        $a = Permission::where('code', 'bookings.view')->firstOrFail();
        $b = Permission::where('code', 'bookings.create')->firstOrFail();
        $role->permissions()->sync([$a->id]);

        Livewire::actingAs($this->admin())
            ->test(RoleManager::class)
            ->call('edit', $role->id)
            ->assertSet('permissionIds', [$a->id])
            ->call('togglePermission', $b->id)   // add
            ->call('togglePermission', $a->id)   // remove
            ->call('save')
            ->assertHasNoErrors();

        $codes = $role->fresh()->permissions->pluck('code')->all();
        $this->assertContains('bookings.create', $codes);
        $this->assertNotContains('bookings.view', $codes);
    }

    public function test_system_roles_cannot_be_deleted(): void
    {
        $systemAdmin = Role::where('code', 'system_admin')->firstOrFail();

        Livewire::actingAs($this->admin())
            ->test(RoleManager::class)
            ->call('delete', $systemAdmin->id)
            ->assertForbidden();

        $this->assertDatabaseHas('roles', ['code' => 'system_admin']);
    }

    public function test_super_admin_cannot_be_edited(): void
    {
        $superAdmin = Role::where('code', 'super_admin')->firstOrFail();

        Livewire::actingAs($this->admin())
            ->test(RoleManager::class)
            ->call('edit', $superAdmin->id)
            ->set('name', 'Hacked')
            ->call('save')
            ->assertForbidden();
    }

    public function test_role_with_users_cannot_be_deleted(): void
    {
        $role = Role::create(['code' => 'inuse', 'name' => 'In Use', 'scope' => 'operational', 'is_system' => false, 'is_active' => true]);
        $user = User::factory()->create();
        $user->roles()->sync([$role->id]);

        Livewire::actingAs($this->admin())
            ->test(RoleManager::class)
            ->call('delete', $role->id);

        $this->assertDatabaseHas('roles', ['code' => 'inuse']);
    }

    public function test_unused_custom_role_can_be_deleted(): void
    {
        $role = Role::create(['code' => 'spare', 'name' => 'Spare', 'scope' => 'operational', 'is_system' => false, 'is_active' => true]);

        Livewire::actingAs($this->admin())
            ->test(RoleManager::class)
            ->call('delete', $role->id);

        $this->assertDatabaseMissing('roles', ['code' => 'spare']);
    }

    public function test_a_requester_cannot_drive_the_component_actions(): void
    {
        $requester = User::factory()->create(['is_active' => true]);
        $requester->roles()->sync([Role::where('code', 'requester')->firstOrFail()->id]);

        Livewire::actingAs($requester)
            ->test(RoleManager::class)
            ->call('newRole')
            ->assertForbidden();
    }
}
