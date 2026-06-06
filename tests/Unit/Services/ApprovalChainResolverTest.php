<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\ApprovalStepType;
use App\Exceptions\ApprovalRoutingException;
use App\Models\ApprovalDelegation;
use App\Models\ApprovalPolicy;
use App\Models\ApprovalPolicyStep;
use App\Models\Role;
use App\Models\User;
use App\Services\ApprovalChainResolver;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApprovalChainResolverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function resolver(): ApprovalChainResolver
    {
        return app(ApprovalChainResolver::class);
    }

    /**
     * @param  list<array<string, mixed>>  $steps
     */
    private function policy(array $steps): ApprovalPolicy
    {
        $policy = ApprovalPolicy::factory()->create();
        foreach ($steps as $i => $step) {
            ApprovalPolicyStep::factory()->create(array_merge([
                'approval_policy_id' => $policy->id,
                'sequence_no' => $i + 1,
            ], $step));
        }

        return $policy->fresh() ?? $policy;
    }

    public function test_expands_user_role_and_unit_approver_steps_in_order(): void
    {
        $approver = User::factory()->create(['is_active' => true]);
        $requester = User::factory()->create(['approver_user_id' => $approver->id]);
        $specific = User::factory()->create(['is_active' => true]);
        $gaAdmin = User::factory()->create(['is_active' => true]);
        $gaAdmin->roles()->attach(Role::where('code', 'ga_admin')->firstOrFail()->id, ['assigned_at' => now()]);

        $policy = $this->policy([
            ['approver_type' => ApprovalStepType::UnitApprover],
            ['approver_type' => ApprovalStepType::User, 'approver_user_id' => $specific->id],
            ['approver_type' => ApprovalStepType::Role, 'role_id' => Role::where('code', 'ga_admin')->firstOrFail()->id],
        ]);

        $chain = $this->resolver()->resolve($policy, $requester);

        $this->assertSame([$approver->id, $specific->id, $gaAdmin->id], $chain);
    }

    public function test_role_step_picks_the_lowest_id_active_holder(): void
    {
        $roleId = Role::where('code', 'ga_admin')->firstOrFail()->id;
        $first = User::factory()->create(['is_active' => true]);
        $second = User::factory()->create(['is_active' => true]);
        foreach ([$first, $second] as $u) {
            $u->roles()->attach($roleId, ['assigned_at' => now()]);
        }
        $requester = User::factory()->create();

        $chain = $this->resolver()->resolve(
            $this->policy([['approver_type' => ApprovalStepType::Role, 'role_id' => $roleId]]),
            $requester,
        );

        $this->assertSame([$first->id], $chain);
    }

    public function test_active_delegation_reroutes_a_resolved_approver(): void
    {
        $approver = User::factory()->create(['is_active' => true]);
        $delegate = User::factory()->create(['is_active' => true]);
        $requester = User::factory()->create(['approver_user_id' => $approver->id]);

        ApprovalDelegation::factory()->create([
            'from_user_id' => $approver->id,
            'to_user_id' => $delegate->id,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
        ]);

        $chain = $this->resolver()->resolve(
            $this->policy([['approver_type' => ApprovalStepType::UnitApprover]]),
            $requester,
        );

        $this->assertSame([$delegate->id], $chain);
    }

    public function test_expired_delegation_does_not_reroute(): void
    {
        $approver = User::factory()->create(['is_active' => true]);
        $delegate = User::factory()->create(['is_active' => true]);
        $requester = User::factory()->create(['approver_user_id' => $approver->id]);

        ApprovalDelegation::factory()->create([
            'from_user_id' => $approver->id,
            'to_user_id' => $delegate->id,
            'starts_at' => now()->subDays(3),
            'ends_at' => now()->subDay(), // already ended
        ]);

        $chain = $this->resolver()->resolve(
            $this->policy([['approver_type' => ApprovalStepType::UnitApprover]]),
            $requester,
        );

        $this->assertSame([$approver->id], $chain);
    }

    public function test_duplicate_and_self_steps_are_collapsed(): void
    {
        $approver = User::factory()->create(['is_active' => true]);
        $requester = User::factory()->create(['approver_user_id' => $approver->id]);

        $policy = $this->policy([
            ['approver_type' => ApprovalStepType::UnitApprover],
            ['approver_type' => ApprovalStepType::User, 'approver_user_id' => $approver->id], // duplicate
            ['approver_type' => ApprovalStepType::User, 'approver_user_id' => $requester->id], // self
        ]);

        $chain = $this->resolver()->resolve($policy, $requester);

        $this->assertSame([$approver->id], $chain);
    }

    public function test_unresolvable_step_throws(): void
    {
        $requester = User::factory()->create(['approver_user_id' => null]);

        $this->expectException(ApprovalRoutingException::class);

        $this->resolver()->resolve(
            $this->policy([['approver_type' => ApprovalStepType::UnitApprover]]),
            $requester,
        );
    }
}
