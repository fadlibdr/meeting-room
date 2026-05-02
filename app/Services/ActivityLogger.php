<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class ActivityLogger
{
    /**
     * Persist an audit log entry.
     *
     * @param  string  $module  Module slug (e.g. "users", "rooms", "bookings")
     * @param  string  $event  Event slug (e.g. "created", "updated", "deactivated")
     * @param  Model|null  $subject  The subject model (optional)
     * @param  array{
     *   description?: string,
     *   old_values?: array<string, mixed>,
     *   new_values?: array<string, mixed>,
     *   context?: array<string, mixed>
     * }  $payload
     * @param  User|null  $actor  Override the actor (defaults to authenticated user)
     */
    public function log(
        string $module,
        string $event,
        ?Model $subject = null,
        array $payload = [],
        ?User $actor = null
    ): ActivityLog {
        $actor = $actor ?? $this->resolveCurrentUser();

        return ActivityLog::create([
            'actor_user_id' => $actor?->id,
            'module' => $module,
            'event' => $event,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'description' => $payload['description'] ?? null,
            'old_values' => $payload['old_values'] ?? null,
            'new_values' => $payload['new_values'] ?? null,
            'context' => $payload['context'] ?? null,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ]);
    }

    /**
     * Convenience: log a user-targeted event (e.g. "users.created" for new user X).
     *
     * @param  array{
     *   description?: string,
     *   old_values?: array<string, mixed>,
     *   new_values?: array<string, mixed>,
     *   context?: array<string, mixed>
     * }  $payload
     */
    public function logUserEvent(string $event, User $subject, array $payload = []): ActivityLog
    {
        return $this->log('users', $event, $subject, $payload);
    }

    private function resolveCurrentUser(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }
}
