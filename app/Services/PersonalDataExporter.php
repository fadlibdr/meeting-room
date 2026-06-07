<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Booking;
use App\Models\CalendarConnection;
use App\Models\Unit;
use App\Models\User;

/**
 * UU PDP (Stage 4f / cross-cutting) — assembles a user's personal data for the
 * data-subject "right to access" export. Returns a plain array (the caller
 * serializes to JSON). Secrets/credentials are deliberately excluded.
 */
class PersonalDataExporter
{
    /**
     * @return array<string, mixed>
     */
    public function export(User $user): array
    {
        $user->loadMissing('roles', 'unit');
        $unit = $user->unit;

        return [
            'generated_at' => now()->toIso8601String(),
            'profile' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'employee_no' => $user->employee_no,
                'job_title' => $user->job_title,
                'unit' => $unit instanceof Unit ? $unit->name : null,
                'roles' => $user->roles->pluck('name')->all(),
                'locale' => $user->locale,
                'timezone' => $user->timezone,
                'is_active' => $user->is_active,
                'created_at' => $user->created_at?->toIso8601String(),
            ],
            'bookings' => Booking::query()
                ->where('requester_user_id', $user->id)
                ->orderBy('starts_at')
                ->get(['id', 'booking_code', 'subject', 'status', 'resource_id', 'starts_at', 'ends_at', 'created_at'])
                ->map(fn (Booking $b): array => [
                    'booking_code' => $b->booking_code,
                    'subject' => $b->subject,
                    'status' => $b->status->value,
                    'resource_id' => $b->resource_id,
                    'starts_at' => $b->starts_at->toIso8601String(),
                    'ends_at' => $b->ends_at->toIso8601String(),
                    'created_at' => $b->created_at?->toIso8601String(),
                ])->all(),
            'calendar_connections' => CalendarConnection::query()
                ->where('user_id', $user->id)
                ->get(['provider', 'is_active', 'created_at'])
                // Tokens are intentionally NOT exported.
                ->map(fn (CalendarConnection $c): array => [
                    'provider' => $c->provider,
                    'is_active' => $c->is_active,
                    'connected_at' => $c->created_at?->toIso8601String(),
                ])->all(),
            'notifications_count' => $user->notifications()->count(),
        ];
    }

    public function filename(User $user): string
    {
        return 'data-pribadi-'.$user->id.'-'.now()->format('Ymd').'.json';
    }
}
