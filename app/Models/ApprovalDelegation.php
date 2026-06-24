<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ApprovalDelegationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An approval delegation: while active, approvals routed to from_user are
 * re-routed to to_user (Stage 3 B).
 *
 * @property int $id
 * @property int $from_user_id
 * @property int $to_user_id
 * @property Carbon $starts_at
 * @property Carbon|null $ends_at
 * @property string|null $reason
 */
class ApprovalDelegation extends Model
{
    /** @use HasFactory<ApprovalDelegationFactory> */
    use HasFactory;

    protected $fillable = ['from_user_id', 'to_user_id', 'starts_at', 'ends_at', 'reason'];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function toUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    /**
     * Delegations active at the given moment (default: now).
     *
     * @param  Builder<ApprovalDelegation>  $query
     * @return Builder<ApprovalDelegation>
     */
    public function scopeActiveAt(Builder $query, ?Carbon $at = null): Builder
    {
        $at ??= Carbon::now();

        return $query->where('starts_at', '<=', $at)
            ->where(function (Builder $q) use ($at): void {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', $at);
            });
    }
}
