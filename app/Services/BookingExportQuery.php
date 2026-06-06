<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Builds the bookings query for an export, applying the same scope + filters as
 * the bookings list. Shared by the synchronous (Livewire) and queued (Job) paths
 * so a queued export reproduces exactly what the user saw on screen.
 */
final class BookingExportQuery
{
    /**
     * @param  array<string, mixed>  $filters  status | search | date_from | date_to
     * @return Builder<Booking>
     */
    public function build(User $user, bool $canViewAll, array $filters): Builder
    {
        $query = Booking::query()
            ->with(['room', 'requester', 'requesterUnit'])
            ->orderByDesc('starts_at');

        if (! $canViewAll) {
            $query->where('requester_user_id', $user->id);
        }

        $status = $this->str($filters, 'status');
        if ($status !== '') {
            $query->where('status', $status);
        }

        $search = $this->str($filters, 'search');
        if ($search !== '') {
            $term = '%'.$search.'%';
            $query->where(function (Builder $q) use ($term): void {
                $q->where('subject', 'like', $term)
                    ->orWhere('booking_code', 'like', $term);
            });
        }

        $from = $this->date($this->str($filters, 'date_from'));
        if ($from !== null) {
            $query->where('starts_at', '>=', $from->startOfDay());
        }

        $to = $this->date($this->str($filters, 'date_to'));
        if ($to !== null) {
            $query->where('starts_at', '<=', $to->endOfDay());
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function str(array $filters, string $key): string
    {
        $value = $filters[$key] ?? '';

        return is_string($value) ? $value : '';
    }

    private function date(string $value): ?Carbon
    {
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
