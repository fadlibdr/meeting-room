<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\BookingStatus;
use App\Enums\RoomApprovalMode;
use App\Exceptions\ApprovalRoutingException;
use App\Models\ApprovalDelegation;
use App\Models\Role;
use App\Models\Room;
use App\Models\User;
use App\Services\ApprovalRoutingService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit tests for ApprovalRoutingService legacy (mode-driven) routing, now
 * returning a CHAIN (Stage 3 B). Policy/chain/delegation behaviour lives in
 * ApprovalChainResolverTest and the multi-step approval feature test.
 *
 * @see ApprovalRoutingService
 */
class ApprovalRoutingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function service(): ApprovalRoutingService
    {
        return app(ApprovalRoutingService::class);
    }

    private function room(RoomApprovalMode $mode): Room
    {
        return Room::factory()->create(['approval_mode' => $mode->value, 'approval_policy_id' => null]);
    }

    private function attachRole(User $user, string $roleCode): void
    {
        $role = Role::where('code', $roleCode)->firstOrFail();
        $user->roles()->attach($role->id, ['is_primary' => true, 'assigned_at' => now()]);
    }

    public function test_none_mode_resolves_to_approved(): void
    {
        $requester = User::factory()->create();

        $resolution = $this->service()->resolve($requester, $this->room(RoomApprovalMode::None));

        $this->assertSame(BookingStatus::Approved, $resolution['status']);
        $this->assertSame([], $resolution['chain']);
        $this->assertNotNull($resolution['approved_at']);
    }

    public function test_unit_approver_mode_resolves_to_submitted_with_the_requesters_approver(): void
    {
        $approver = User::factory()->create(['is_active' => true]);
        $requester = User::factory()->create(['approver_user_id' => $approver->id]);

        $resolution = $this->service()->resolve($requester, $this->room(RoomApprovalMode::UnitApprover));

        $this->assertSame(BookingStatus::Submitted, $resolution['status']);
        $this->assertSame([$approver->id], $resolution['chain']);
        $this->assertNull($resolution['approved_at']);
    }

    public function test_unit_approver_mode_throws_when_requester_has_no_approver(): void
    {
        $requester = User::factory()->create(['approver_user_id' => null]);

        $this->expectException(ApprovalRoutingException::class);

        $this->service()->resolve($requester, $this->room(RoomApprovalMode::UnitApprover));
    }

    public function test_ga_admin_mode_resolves_to_submitted_with_an_active_ga_admin(): void
    {
        $gaAdmin = User::factory()->create(['is_active' => true]);
        $this->attachRole($gaAdmin, 'ga_admin');
        $requester = User::factory()->create();

        $resolution = $this->service()->resolve($requester, $this->room(RoomApprovalMode::GaAdmin));

        $this->assertSame(BookingStatus::Submitted, $resolution['status']);
        $this->assertSame([$gaAdmin->id], $resolution['chain']);
        $this->assertNull($resolution['approved_at']);
    }

    public function test_ga_admin_mode_throws_when_no_active_ga_admin_exists(): void
    {
        $requester = User::factory()->create();

        $this->expectException(ApprovalRoutingException::class);

        $this->service()->resolve($requester, $this->room(RoomApprovalMode::GaAdmin));
    }

    public function test_ga_admin_mode_excludes_inactive_ga_admins(): void
    {
        $inactiveGaAdmin = User::factory()->create(['is_active' => false]);
        $this->attachRole($inactiveGaAdmin, 'ga_admin');
        $requester = User::factory()->create();

        $this->expectException(ApprovalRoutingException::class);

        $this->service()->resolve($requester, $this->room(RoomApprovalMode::GaAdmin));
    }

    public function test_legacy_unit_approver_is_re_routed_by_an_active_delegation(): void
    {
        $approver = User::factory()->create(['is_active' => true]);
        $delegate = User::factory()->create(['is_active' => true]);
        $requester = User::factory()->create(['approver_user_id' => $approver->id]);

        ApprovalDelegation::factory()->create([
            'from_user_id' => $approver->id,
            'to_user_id' => $delegate->id,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);

        $resolution = $this->service()->resolve($requester, $this->room(RoomApprovalMode::UnitApprover));

        $this->assertSame([$delegate->id], $resolution['chain']);
    }
}
