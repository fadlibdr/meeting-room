<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $unit_id
 * @property string|null $employee_no
 * @property string $name
 * @property string $email
 * @property string $password
 * @property string|null $job_title
 * @property int|null $approver_user_id
 * @property bool $is_active
 * @property string|null $timezone
 * @property Carbon|null $last_login_at
 * @property int $failed_login_attempts
 * @property Carbon|null $locked_until
 * @property Carbon|null $email_verified_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'unit_id',
        'employee_no',
        'name',
        'email',
        'password',
        'job_title',
        'approver_user_id',
        'is_active',
        'timezone',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'locked_until' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'failed_login_attempts' => 'integer',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(self::class, 'approver_user_id');
    }

    public function subordinates(): HasMany
    {
        return $this->hasMany(self::class, 'approver_user_id');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles')
            ->withPivot(['is_primary', 'assigned_at', 'assigned_by_user_id'])
            ->withTimestamps();
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'requester_user_id');
    }

    public function bookingApprovals(): HasMany
    {
        return $this->hasMany(BookingApproval::class, 'approver_user_id');
    }

    /**
     * Permission check stub.
     *
     * Sprint 1 wires this to PermissionCacheService per Blueprint §C.3.
     */
    public function hasPermission(string $permission): bool
    {
        // TODO Sprint 1: replace with PermissionCacheService::userHas($this, $permission)
        return false;
    }
}
