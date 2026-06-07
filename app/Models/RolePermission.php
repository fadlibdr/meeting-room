<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Services\PermissionCacheService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $role_id
 * @property int $permission_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class RolePermission extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = ['role_id', 'permission_id'];

    protected static function booted(): void
    {
        $clearCache = function (RolePermission $rolePermission) {
            app(PermissionCacheService::class)
                ->forgetByRole($rolePermission->role_id);
        };

        static::created($clearCache);
        static::updated($clearCache);
        static::deleted($clearCache);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class);
    }
}
