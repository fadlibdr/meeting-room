<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ApprovalStepType;
use App\Models\ApprovalPolicy;
use App\Models\ApprovalPolicyStep;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApprovalPolicyStep>
 */
class ApprovalPolicyStepFactory extends Factory
{
    protected $model = ApprovalPolicyStep::class;

    public function definition(): array
    {
        return [
            'approval_policy_id' => ApprovalPolicy::factory(),
            'sequence_no' => 1,
            'approver_type' => ApprovalStepType::UnitApprover,
            'role_id' => null,
            'approver_user_id' => null,
        ];
    }
}
