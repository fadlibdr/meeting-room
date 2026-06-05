<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire\Admin;

use App\Livewire\Admin\UserForm;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UserFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function withRole(User $user, string $code): User
    {
        $user->roles()->sync([Role::where('code', $code)->firstOrFail()->id]);

        return $user;
    }

    private function roleId(string $code): int
    {
        return Role::where('code', $code)->firstOrFail()->id;
    }

    public function test_admin_can_assign_an_approver_to_a_user(): void
    {
        $admin = $this->withRole(User::factory()->create(), 'system_admin');
        $unit = Unit::factory()->create();
        $approver = $this->withRole(User::factory()->create(['is_active' => true]), 'unit_approver');
        $target = User::factory()->create([
            'unit_id' => $unit->id,
            'email' => 'target.assign@bpjs-kesehatan.go.id',
            'approver_user_id' => null,
        ]);

        Livewire::actingAs($admin)
            ->test(UserForm::class, ['user' => $target])
            ->set('unitId', $unit->id)
            ->set('roleIds', [$this->roleId('requester')])
            ->set('approverUserId', $approver->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame($approver->id, $target->fresh()->approver_user_id);
    }

    public function test_approver_can_be_cleared(): void
    {
        $admin = $this->withRole(User::factory()->create(), 'system_admin');
        $unit = Unit::factory()->create();
        $approver = $this->withRole(User::factory()->create(['is_active' => true]), 'unit_approver');
        $target = User::factory()->create([
            'unit_id' => $unit->id,
            'email' => 'target.clear@bpjs-kesehatan.go.id',
            'approver_user_id' => $approver->id,
        ]);

        Livewire::actingAs($admin)
            ->test(UserForm::class, ['user' => $target])
            ->set('unitId', $unit->id)
            ->set('roleIds', [$this->roleId('requester')])
            ->set('approverUserId', null)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertNull($target->fresh()->approver_user_id);
    }

    public function test_user_cannot_be_their_own_approver(): void
    {
        $admin = $this->withRole(User::factory()->create(), 'system_admin');
        $unit = Unit::factory()->create();
        $target = $this->withRole(User::factory()->create(['unit_id' => $unit->id]), 'unit_approver');

        Livewire::actingAs($admin)
            ->test(UserForm::class, ['user' => $target])
            ->set('unitId', $unit->id)
            ->set('roleIds', [$this->roleId('unit_approver')])
            ->set('approverUserId', $target->id)
            ->call('save')
            ->assertHasErrors('approverUserId');
    }

    public function test_only_approval_capable_users_are_offered_as_approvers(): void
    {
        $admin = $this->withRole(User::factory()->create(), 'system_admin');
        $unit = Unit::factory()->create();
        $approver = $this->withRole(User::factory()->create(['is_active' => true]), 'unit_approver');
        $plainRequester = $this->withRole(User::factory()->create(['is_active' => true]), 'requester');
        $target = User::factory()->create(['unit_id' => $unit->id]);

        $approvers = Livewire::actingAs($admin)
            ->test(UserForm::class, ['user' => $target])
            ->viewData('approvers');

        $this->assertTrue($approvers->contains('id', $approver->id));
        $this->assertFalse($approvers->contains('id', $plainRequester->id));
    }
}
