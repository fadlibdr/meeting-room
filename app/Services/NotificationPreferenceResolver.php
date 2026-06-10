<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\NotificationType;
use App\Models\NotificationChannelDefault;
use App\Models\NotificationPreference;
use App\Models\User;

/**
 * Resolves which channels a user should receive for a notification type,
 * combining the admin default per (type, channel) with the user's override
 * (only honoured when the default is user_overridable).
 *
 * Falls back to sensible built-in defaults when no admin row exists, so the
 * system behaves correctly before anything is configured.
 */
class NotificationPreferenceResolver
{
    /** @var array<int, string> */
    public const CHANNELS = ['database', 'mail', 'telegram'];

    /**
     * @return array<string, string>
     */
    public static function channelLabels(): array
    {
        return [
            'database' => 'Dalam Aplikasi',
            'mail' => 'Email',
            'telegram' => 'Telegram',
        ];
    }

    /**
     * The preference-enabled channels for this user + type (before infra checks).
     *
     * @return array<int, string>
     */
    public function channelsFor(User $user, NotificationType $type): array
    {
        return array_values(array_filter(
            self::CHANNELS,
            fn (string $channel): bool => $this->enabled($user, $type, $channel),
        ));
    }

    public function enabled(User $user, NotificationType $type, string $channel): bool
    {
        $default = $this->default($type, $channel);

        if ($default['user_overridable']) {
            $pref = NotificationPreference::query()
                ->where('user_id', $user->id)
                ->where('type', $type->value)
                ->where('channel', $channel)
                ->first();

            if ($pref !== null) {
                return (bool) $pref->enabled;
            }
        }

        return $default['enabled'];
    }

    /**
     * Admin default for a (type, channel) — from the DB row, else a built-in.
     *
     * @return array{enabled: bool, user_overridable: bool}
     */
    public function default(NotificationType $type, string $channel): array
    {
        $row = NotificationChannelDefault::query()
            ->where('type', $type->value)
            ->where('channel', $channel)
            ->first();

        if ($row !== null) {
            return ['enabled' => (bool) $row->enabled, 'user_overridable' => (bool) $row->user_overridable];
        }

        return match ($channel) {
            // In-app inbox is always on and not user-disableable by default.
            'database' => ['enabled' => true, 'user_overridable' => false],
            // Email follows the legacy global flag and is overridable.
            'mail' => ['enabled' => (bool) app(SettingsService::class)->get('notifications.send_email_default', false), 'user_overridable' => true],
            // Telegram is opt-in.
            'telegram' => ['enabled' => false, 'user_overridable' => true],
            default => ['enabled' => false, 'user_overridable' => true],
        };
    }
}
