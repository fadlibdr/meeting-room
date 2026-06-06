<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Livewire\Admin\ApprovalPolicyManager;
use App\Models\ApprovalPolicy;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ApprovalPolicyManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function userWithRole(string $code): User
    {
        $user = User::factory()->create();
        $user->roles()->sync([Role::where('code', $code)->firstOrFail()->id]);

        return $user;
    }

    public function test_settings_admin_can_open_it_but_requester_cannot(): void
    {
        $this->actingAs($this->userWithRole('super_admin'))
            ->get(route('admin.approval-policies.index'))->assertOk();

        $this->actingAs($this->userWithRole('requester'))
            ->get(route('admin.approval-policies.index'))->assertForbidden();
    }

    public function test_creates_a_policy_with_ordered_steps(): void
    {
        $admin = $this->userWithRole('super_admin');
        $roleId = Role::where('code', 'ga_admin')->firstOrFail()->id;

        Livewire::actingAs($admin)
            ->test(ApprovalPolicyManager::class)
            ->call('newPolicy')
            ->set('name', 'Eksekutif')
            ->set('steps', [
                ['type' => 'unit_approver', 'role_id' => null, 'approver_user_id' => null],
                ['type' => 'role', 'role_id' => $roleId, 'approver_user_id' => null],
            ])
            ->call('save')
            ->assertHasNoErrors();

        $policy = ApprovalPolicy::where('name', 'Eksekutif')->firstOrFail();
        $this->assertSame(2, $policy->steps()->count());
        $this->assertDatabaseHas('approval_policy_steps', [
            'approval_policy_id' => $policy->id, 'sequence_no' => 1, 'approver_type' => 'unit_approver',
        ]);
        $this->assertDatabaseHas('approval_policy_steps', [
            'approval_policy_id' => $policy->id, 'sequence_no' => 2, 'approver_type' => 'role', 'role_id' => $roleId,
        ]);
    }

    public function test_role_step_without_a_role_is_rejected(): void
    {
        $admin = $this->userWithRole('super_admin');

        Livewire::actingAs($admin)
            ->test(ApprovalPolicyManager::class)
            ->call('newPolicy')
            ->set('name', 'Salah')
            ->set('steps', [['type' => 'role', 'role_id' => null, 'approver_user_id' => null]])
            ->call('save')
            ->assertHasErrors('steps.0.role_id');

        $this->assertDatabaseMissing('approval_policies', ['name' => 'Salah']);
    }

    public function test_edit_replaces_the_steps(): void
    {
        $admin = $this->userWithRole('super_admin');
        $policy = ApprovalPolicy::factory()->create(['name' => 'Lama']);
        $policy->steps()->create(['sequence_no' => 1, 'approver_type' => 'unit_approver']);

        Livewire::actingAs($admin)
            ->test(ApprovalPolicyManager::class)
            ->call('editPolicy', $policy->id)
            ->set('name', 'Baru')
            ->set('steps', [
                ['type' => 'unit_approver', 'role_id' => null, 'approver_user_id' => null],
                ['type' => 'unit_approver', 'role_id' => null, 'approver_user_id' => null],
            ])
            ->call('save')
            ->assertHasNoErrors();

        $policy->refresh();
        $this->assertSame('Baru', $policy->name);
        $this->assertSame(2, $policy->steps()->count());
    }

    public function test_delete_removes_the_policy(): void
    {
        $admin = $this->userWithRole('super_admin');
        $policy = ApprovalPolicy::factory()->create();

        Livewire::actingAs($admin)
            ->test(ApprovalPolicyManager::class)
            ->call('delete', $policy->id);

        $this->assertDatabaseMissing('approval_policies', ['id' => $policy->id]);
    }
}
