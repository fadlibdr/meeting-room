<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Observers\UserRoleObserver;
use App\Services\PermissionCacheService;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $role_id
 * @property bool $is_primary
 * @property Carbon $assigned_at
 * @property int|null $assigned_by_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[ObservedBy([UserRoleObserver::class])]
class UserRole extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'user_id', 'role_id', 'is_primary', 'assigned_at', 'assigned_by_user_id',
    ];

    protected static function booted(): void
    {
        $clearCache = function (UserRole $userRole): void {
            $user = $userRole->user;
            if ($user instanceof User) {
                app(PermissionCacheService::class)->forget($user);
            }
        };

        static::created($clearCache);
        static::updated($clearCache);
        static::deleted($clearCache);
    }

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'assigned_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_user_id');
    }
}
