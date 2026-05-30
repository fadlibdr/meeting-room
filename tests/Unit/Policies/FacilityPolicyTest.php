<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\Role;
use App\Models\RoomFacility;
use App\Models\User;
use App\Policies\FacilityPolicy;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Facilities reuse rooms.* permissions (spec §2.5 / Dec-19). A failing
 * assertion indicates a seeder<->§G mismatch, not a policy bug.
 */
class FacilityPolicyTest extends TestCase
{
    use RefreshDatabase;

    private FacilityPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->policy = new FacilityPolicy;
    }

    private function userWithRole(string $roleCode): User
    {
        $user = User::factory()->create();
        $role = Role::where('code', $roleCode)->firstOrFail();
        $user->roles()->sync([$role->id]);

        return $user;
    }

    private function facility(): RoomFacility
    {
        return RoomFacility::factory()->create();
    }

    public function test_view_any_allowed_for_ga_admin(): void
    {
        $this->assertTrue($this->policy->viewAny($this->userWithRole('ga_admin')));
    }

    public function test_view_any_allowed_for_requester(): void
    {
        $this->assertTrue($this->policy->viewAny($this->userWithRole('requester')));
    }

    public function test_view_allowed_for_ga_admin(): void
    {
        $this->assertTrue($this->policy->view($this->userWithRole('ga_admin'), $this->facility()));
    }

    public function test_view_allowed_for_requester(): void
    {
        $this->assertTrue($this->policy->view($this->userWithRole('requester'), $this->facility()));
    }

    public function test_create_allowed_for_super_admin(): void
    {
        $this->assertTrue($this->policy->create($this->userWithRole('super_admin')));
    }

    public function test_create_denied_for_requester(): void
    {
        $this->assertFalse($this->policy->create($this->userWithRole('requester')));
    }

    public function test_update_allowed_for_ga_admin(): void
    {
        $this->assertTrue($this->policy->update($this->userWithRole('ga_admin'), $this->facility()));
    }

    public function test_update_denied_for_unit_approver(): void
    {
        $this->assertFalse($this->policy->update($this->userWithRole('unit_approver'), $this->facility()));
    }

    public function test_delete_allowed_for_super_admin(): void
    {
        $this->assertTrue($this->policy->delete($this->userWithRole('super_admin'), $this->facility()));
    }

    public function test_delete_denied_for_requester(): void
    {
        $this->assertFalse($this->policy->delete($this->userWithRole('requester'), $this->facility()));
    }
}
