<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\Role;
use App\Models\Room;
use App\Models\User;
use App\Policies\RoomPolicy;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Encodes the Blueprint §G permission matrix for Rooms. A failing assertion
 * here means the seeder's role->permission mapping diverges from §G (a real
 * finding), not a policy bug. Roles tested map: super_admin/ga_admin = manage;
 * unit_approver/requester = view-only. system_admin (roomless) and the missing
 * front_office role are intentionally excluded (Dec-20).
 */
class RoomPolicyTest extends TestCase
{
    use RefreshDatabase;

    private RoomPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->policy = new RoomPolicy;
    }

    private function userWithRole(string $roleCode): User
    {
        $user = User::factory()->create();
        $role = Role::where('code', $roleCode)->firstOrFail();
        $user->roles()->sync([$role->id]);

        return $user;
    }

    private function room(): Room
    {
        return Room::factory()->create();
    }

    // ---- viewAny: every role with rooms.view ----

    public function test_view_any_allowed_for_super_admin(): void
    {
        $this->assertTrue($this->policy->viewAny($this->userWithRole('super_admin')));
    }

    public function test_view_any_allowed_for_ga_admin(): void
    {
        $this->assertTrue($this->policy->viewAny($this->userWithRole('ga_admin')));
    }

    public function test_view_any_allowed_for_unit_approver(): void
    {
        $this->assertTrue($this->policy->viewAny($this->userWithRole('unit_approver')));
    }

    public function test_view_any_allowed_for_requester(): void
    {
        $this->assertTrue($this->policy->viewAny($this->userWithRole('requester')));
    }

    // ---- view (single room) ----

    public function test_view_allowed_for_super_admin(): void
    {
        $this->assertTrue($this->policy->view($this->userWithRole('super_admin'), $this->room()));
    }

    public function test_view_allowed_for_ga_admin(): void
    {
        $this->assertTrue($this->policy->view($this->userWithRole('ga_admin'), $this->room()));
    }

    public function test_view_allowed_for_unit_approver(): void
    {
        $this->assertTrue($this->policy->view($this->userWithRole('unit_approver'), $this->room()));
    }

    public function test_view_allowed_for_requester(): void
    {
        $this->assertTrue($this->policy->view($this->userWithRole('requester'), $this->room()));
    }

    // ---- create: admins only ----

    public function test_create_allowed_for_super_admin(): void
    {
        $this->assertTrue($this->policy->create($this->userWithRole('super_admin')));
    }

    public function test_create_allowed_for_ga_admin(): void
    {
        $this->assertTrue($this->policy->create($this->userWithRole('ga_admin')));
    }

    public function test_create_denied_for_unit_approver(): void
    {
        $this->assertFalse($this->policy->create($this->userWithRole('unit_approver')));
    }

    public function test_create_denied_for_requester(): void
    {
        $this->assertFalse($this->policy->create($this->userWithRole('requester')));
    }

    // ---- update: admins only ----

    public function test_update_allowed_for_super_admin(): void
    {
        $this->assertTrue($this->policy->update($this->userWithRole('super_admin'), $this->room()));
    }

    public function test_update_allowed_for_ga_admin(): void
    {
        $this->assertTrue($this->policy->update($this->userWithRole('ga_admin'), $this->room()));
    }

    public function test_update_denied_for_unit_approver(): void
    {
        $this->assertFalse($this->policy->update($this->userWithRole('unit_approver'), $this->room()));
    }

    public function test_update_denied_for_requester(): void
    {
        $this->assertFalse($this->policy->update($this->userWithRole('requester'), $this->room()));
    }

    // ---- delete (= deactivate/archive): admins only ----

    public function test_delete_allowed_for_super_admin(): void
    {
        $this->assertTrue($this->policy->delete($this->userWithRole('super_admin'), $this->room()));
    }

    public function test_delete_allowed_for_ga_admin(): void
    {
        $this->assertTrue($this->policy->delete($this->userWithRole('ga_admin'), $this->room()));
    }

    public function test_delete_denied_for_unit_approver(): void
    {
        $this->assertFalse($this->policy->delete($this->userWithRole('unit_approver'), $this->room()));
    }

    public function test_delete_denied_for_requester(): void
    {
        $this->assertFalse($this->policy->delete($this->userWithRole('requester'), $this->room()));
    }

    // ---- manageBlocks: admins only ----

    public function test_manage_blocks_allowed_for_super_admin(): void
    {
        $this->assertTrue($this->policy->manageBlocks($this->userWithRole('super_admin')));
    }

    public function test_manage_blocks_allowed_for_ga_admin(): void
    {
        $this->assertTrue($this->policy->manageBlocks($this->userWithRole('ga_admin')));
    }

    public function test_manage_blocks_denied_for_unit_approver(): void
    {
        $this->assertFalse($this->policy->manageBlocks($this->userWithRole('unit_approver')));
    }

    public function test_manage_blocks_denied_for_requester(): void
    {
        $this->assertFalse($this->policy->manageBlocks($this->userWithRole('requester')));
    }
}
