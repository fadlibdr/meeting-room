<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasHashid;
use App\Observers\UserObserver;
use App\Services\PermissionCacheService;
use App\Services\SettingsService;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int $id
 * @property int|null $unit_id
 * @property string|null $employee_no
 * @property string $name
 * @property string $email
 * @property string|null $pending_email
 * @property string|null $pending_email_token
 * @property string $password
 * @property bool $must_change_password
 * @property string|null $job_title
 * @property int|null $approver_user_id
 * @property bool $is_active
 * @property string|null $timezone
 * @property string|null $locale
 * @property string|null $avatar_path
 * @property string|null $telegram_chat_id
 * @property string|null $telegram_link_token
 * @property bool $email_notifications
 * @property Carbon|null $last_login_at
 * @property int $failed_login_attempts
 * @property Carbon|null $locked_until
 * @property Carbon|null $email_verified_at
 * @property string|null $calendar_feed_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[ObservedBy([UserObserver::class])]
class User extends Authenticatable
{
    use BelongsToTenant;

    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    use HasHashid;

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
        'locale',
        'avatar_path',
        'telegram_chat_id',
        'email_notifications',
        'must_change_password',
    ];

    /**
     * Telegram chat id used by the Telegram notification channel.
     */
    public function routeNotificationForTelegram(): ?string
    {
        return $this->telegram_chat_id;
    }

    /**
     * One-time deep-link token for the "Hubungkan Telegram" flow, created on
     * first use. The /start webhook exchanges it for the user's chat id.
     */
    public function ensureTelegramLinkToken(): string
    {
        if ($this->telegram_link_token === null) {
            $this->forceFill(['telegram_link_token' => Str::random(32)])->save();
        }

        return (string) $this->telegram_link_token;
    }

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * Default attribute values (mirror DB column defaults so new in-memory
     * instances behave correctly before a reload).
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'email_notifications' => true,
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
            'must_change_password' => 'boolean',
            'email_notifications' => 'boolean',
            'failed_login_attempts' => 'integer',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * Two-factor (TOTP) is fully enrolled: a secret exists and was confirmed.
     */
    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_secret !== null && $this->two_factor_confirmed_at !== null;
    }

    /**
     * Privileged = holds an administrative capability; used by the
     * mfa_enforced_for_privileged policy.
     */
    public function isPrivileged(): bool
    {
        return $this->hasPermission('app-settings.update')
            || $this->hasPermission('users.update')
            || $this->hasPermission('roles.update');
    }

    /**
     * Whether this user must enroll in 2FA before using the app, per the
     * configurable enforcement policy (security.mfa_*).
     */
    public function requiresTwoFactor(): bool
    {
        $settings = app(SettingsService::class);

        if (! (bool) $settings->get('security.mfa_enabled', true) || $this->hasTwoFactorEnabled()) {
            return false;
        }

        if ((bool) $settings->get('security.mfa_enforced', false)) {
            return true;
        }

        return (bool) $settings->get('security.mfa_enforced_for_privileged', false) && $this->isPrivileged();
    }

    /**
     * Consume a one-time recovery code; returns false if it isn't valid.
     */
    public function useRecoveryCode(string $code): bool
    {
        $codes = $this->two_factor_recovery_codes ?? [];
        $index = array_search($code, $codes, true);

        if ($index === false) {
            return false;
        }

        unset($codes[$index]);
        $this->forceFill(['two_factor_recovery_codes' => array_values($codes)])->save();

        return true;
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(self::class, 'approver_user_id');
    }

    /**
     * Public URL for the user's avatar, or null (callers fall back to initials).
     */
    public function avatarUrl(): ?string
    {
        $path = $this->avatar_path;

        return is_string($path) && $path !== ''
            ? Storage::disk('public')->url($path)
            : null;
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

    /**
     * Stage 3 F.2a — the secret token for this user's .ics subscription feed,
     * created on first access. Rotatable via {@see regenerateCalendarFeedToken()}.
     */
    public function ensureCalendarFeedToken(): string
    {
        if ($this->calendar_feed_token === null) {
            $this->forceFill(['calendar_feed_token' => Str::random(48)])->save();
        }

        return (string) $this->calendar_feed_token;
    }

    public function regenerateCalendarFeedToken(): string
    {
        $this->forceFill(['calendar_feed_token' => Str::random(48)])->save();

        return (string) $this->calendar_feed_token;
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
        return app(PermissionCacheService::class)->userHas($this, $permission);
    }

    /**
     * Stage 4 (4e) — a platform operator: super-admin of the default (platform)
     * tenant. Only they manage tenants in the provider console.
     */
    public function isPlatformAdmin(): bool
    {
        $defaultId = Tenant::query()->where('is_default', true)->value('id');

        if ($defaultId === null || (int) $this->tenant_id !== (int) $defaultId) {
            return false;
        }

        return $this->roles()->where('code', 'super_admin')->exists();
    }
}
