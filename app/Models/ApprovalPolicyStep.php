<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ApprovalStepType;
use Database\Factories\ApprovalPolicyStepFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One ordered step of an approval policy (Stage 3 B).
 *
 * @property int $id
 * @property int $approval_policy_id
 * @property int $sequence_no
 * @property ApprovalStepType $approver_type
 * @property int|null $role_id
 * @property int|null $approver_user_id
 */
class ApprovalPolicyStep extends Model
{
    /** @use HasFactory<ApprovalPolicyStepFactory> */
    use HasFactory;

    protected $fillable = [
        'approval_policy_id', 'sequence_no', 'approver_type', 'role_id', 'approver_user_id',
    ];

    protected function casts(): array
    {
        return [
            'approver_type' => ApprovalStepType::class,
            'sequence_no' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<ApprovalPolicy, $this>
     */
    public function policy(): BelongsTo
    {
        return $this->belongsTo(ApprovalPolicy::class, 'approval_policy_id');
    }

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approverUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_user_id');
    }
}
