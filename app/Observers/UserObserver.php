<?php

declare(strict_types=1);

namespace App\Observers;

use App\Facades\Activity;
use App\Models\User;

class UserObserver
{
    /**
     * Fields whose changes are worth auditing.
     *
     * @var array<int, string>
     */
    private const AUDITABLE_FIELDS = [
        'name',
        'email',
        'unit_id',
        'is_active',
        'locked_until',
        'email_verified_at',
    ];

    public function created(User $user): void
    {
        $newValues = $this->extractAuditableFields($user->getAttributes());

        Activity::log(
            module: 'users',
            event: 'created',
            subject: $user,
            payload: [
                'description' => "User {$user->email} provisioned",
                'new_values' => $newValues,
            ]
        );
    }

    public function updated(User $user): void
    {
        $changes = $user->getChanges();
        $original = $user->getOriginal();

        // Special case: password change is logged but the hash is never stored
        if (array_key_exists('password', $changes)) {
            Activity::log(
                module: 'users',
                event: 'password_changed',
                subject: $user,
                payload: [
                    'description' => "Password changed for {$user->email}",
                ]
            );
        }

        // General attribute changes (intersected with auditable fields)
        $auditedChanges = array_intersect_key($changes, array_flip(self::AUDITABLE_FIELDS));

        if ($auditedChanges === []) {
            return;
        }

        // Special case: is_active toggle gets a more specific event
        if (array_key_exists('is_active', $auditedChanges) && count($auditedChanges) === 1) {
            $event = $user->is_active ? 'activated' : 'deactivated';
            $description = $user->is_active
                ? "User {$user->email} reactivated"
                : "User {$user->email} deactivated";

            Activity::log(
                module: 'users',
                event: $event,
                subject: $user,
                payload: [
                    'description' => $description,
                    'old_values' => array_intersect_key($original, $auditedChanges),
                    'new_values' => $auditedChanges,
                ]
            );

            return;
        }

        // Generic update event
        Activity::log(
            module: 'users',
            event: 'updated',
            subject: $user,
            payload: [
                'description' => "User {$user->email} attributes updated",
                'old_values' => array_intersect_key($original, $auditedChanges),
                'new_values' => $auditedChanges,
            ]
        );
    }

    /**
     * Extract only auditable fields from a full attributes array.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function extractAuditableFields(array $attributes): array
    {
        return array_intersect_key($attributes, array_flip(self::AUDITABLE_FIELDS));
    }
}
