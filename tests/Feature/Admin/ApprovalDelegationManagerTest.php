<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Livewire\Admin\ApprovalDelegationManager;
use App\Models\ApprovalDelegation;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ApprovalDelegationManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->roles()->sync([Role::where('code', 'super_admin')->firstOrFail()->id]);

        return $user;
    }

    public function test_requester_cannot_access_it(): void
    {
        $requester = User::factory()->create();
        $requester->roles()->sync([Role::where('code', 'requester')->firstOrFail()->id]);

        $this->actingAs($requester)->get(route('admin.approval-delegations.index'))->assertForbidden();
    }

    public function test_creates_a_delegation(): void
    {
        $from = User::factory()->create();
        $to = User::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(ApprovalDelegationManager::class)
            ->call('newDelegation')
            ->set('fromUserId', $from->id)
            ->set('toUserId', $to->id)
            ->set('startsAt', '2026-06-08')
            ->set('endsAt', '2026-06-12')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('approval_delegations', [
            'from_user_id' => $from->id,
            'to_user_id' => $to->id,
        ]);
    }

    public function test_from_and_to_must_differ(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(ApprovalDelegationManager::class)
            ->call('newDelegation')
            ->set('fromUserId', $user->id)
            ->set('toUserId', $user->id)
            ->set('startsAt', '2026-06-08')
            ->call('save')
            ->assertHasErrors('fromUserId');
    }

    public function test_end_now_closes_an_active_delegation(): void
    {
        $delegation = ApprovalDelegation::factory()->create([
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addWeek(),
        ]);

        Livewire::actingAs($this->admin())
            ->test(ApprovalDelegationManager::class)
            ->call('endNow', $delegation->id);

        $this->assertTrue($delegation->refresh()->ends_at->lessThanOrEqualTo(now()));
    }
}
