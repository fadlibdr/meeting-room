<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\BookingStatus;
use App\Enums\RoomApprovalMode;
use App\Exceptions\ApprovalRoutingException;
use App\Models\Role;
use App\Models\User;
use App\Services\ApprovalRoutingService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit tests for ApprovalRoutingService (M3-C2-i).
 *
 * The routing rule was extracted from SubmitBookingAction so SubmitDraftAction
 * can reuse it. SubmitBookingActionTest still exercises the rule end to end;
 * these cover the service in isolation.
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
        return new ApprovalRoutingService;
    }

    private function attachRole(User $user, string $roleCode): void
    {
        $role = Role::where('code', $roleCode)->firstOrFail();
        $user->roles()->attach($role->id, [
            'is_primary' => true,
            'assigned_at' => now(),
        ]);
    }

    public function test_none_mode_resolves_to_approved(): void
    {
        $requester = User::factory()->create();

        $resolution = $this->service()->resolve($requester, RoomApprovalMode::None);

        $this->assertSame(BookingStatus::Approved, $resolution['status']);
        $this->assertNull($resolution['current_step']);
        $this->assertNull($resolution['approver_user_id']);
        $this->assertNotNull($resolution['approved_at']);
    }

    public function test_unit_approver_mode_resolves_to_submitted_with_the_requesters_approver(): void
    {
        $approver = User::factory()->create(['is_active' => true]);
        $requester = User::factory()->create(['approver_user_id' => $approver->id]);

        $resolution = $this->service()->resolve($requester, RoomApprovalMode::UnitApprover);

        $this->assertSame(BookingStatus::Submitted, $resolution['status']);
        $this->assertSame(1, $resolution['current_step']);
        $this->assertSame($approver->id, $resolution['approver_user_id']);
        $this->assertNull($resolution['approved_at']);
    }

    public function test_unit_approver_mode_throws_when_requester_has_no_approver(): void
    {
        $requester = User::factory()->create(['approver_user_id' => null]);

        $this->expectException(ApprovalRoutingException::class);

        $this->service()->resolve($requester, RoomApprovalMode::UnitApprover);
    }

    public function test_ga_admin_mode_resolves_to_submitted_with_an_active_ga_admin(): void
    {
        $gaAdmin = User::factory()->create(['is_active' => true]);
        $this->attachRole($gaAdmin, 'ga_admin');
        $requester = User::factory()->create();

        $resolution = $this->service()->resolve($requester, RoomApprovalMode::GaAdmin);

        $this->assertSame(BookingStatus::Submitted, $resolution['status']);
        $this->assertSame(1, $resolution['current_step']);
        $this->assertSame($gaAdmin->id, $resolution['approver_user_id']);
        $this->assertNull($resolution['approved_at']);
    }

    public function test_ga_admin_mode_throws_when_no_active_ga_admin_exists(): void
    {
        $requester = User::factory()->create();

        $this->expectException(ApprovalRoutingException::class);

        $this->service()->resolve($requester, RoomApprovalMode::GaAdmin);
    }

    public function test_ga_admin_mode_excludes_inactive_ga_admins(): void
    {
        $inactiveGaAdmin = User::factory()->create(['is_active' => false]);
        $this->attachRole($inactiveGaAdmin, 'ga_admin');
        $requester = User::factory()->create();

        $this->expectException(ApprovalRoutingException::class);

        $this->service()->resolve($requester, RoomApprovalMode::GaAdmin);
    }
}
