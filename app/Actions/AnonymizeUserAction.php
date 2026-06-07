<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\ActivityLog;
use App\Models\CalendarConnection;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * UU PDP (Stage 4f) — right to erasure by anonymisation.
 *
 * Scrubs the user's identifying fields and revokes all access, while KEEPING
 * the user row so bookings/approvals/audit history stay referentially intact
 * (the person is no longer identifiable, but the operational record holds). An
 * audit entry records who performed the erasure and when.
 */
class AnonymizeUserAction
{
    public function execute(User $user, ?int $actorId = null): User
    {
        return DB::transaction(function () use ($user, $actorId): User {
            $placeholderEmail = 'anonymized-'.$user->id.'@anonymized.invalid';

            // Revoke credentials / external links before scrubbing identity.
            $user->tokens()->delete();
            CalendarConnection::query()->where('user_id', $user->id)->delete();

            $user->forceFill([
                'name' => 'Pengguna Dianonimkan',
                'email' => $placeholderEmail,
                'employee_no' => null,
                'job_title' => null,
                'password' => Hash::make(Str::random(48)), // unusable
                'calendar_feed_token' => null,
                'email_notifications' => false,
                'is_active' => false,
            ])->save();

            // Detach roles (no further authority).
            $user->roles()->detach();

            ActivityLog::create([
                'actor_user_id' => $actorId,
                'module' => 'users',
                'event' => 'anonymize',
                'subject_type' => User::class,
                'subject_id' => $user->id,
                'description' => sprintf('Data pribadi pengguna #%d dianonimkan (hak penghapusan UU PDP).', $user->id),
                'context' => ['anonymized_at' => now()->toIso8601String()],
            ]);

            return $user;
        });
    }
}
