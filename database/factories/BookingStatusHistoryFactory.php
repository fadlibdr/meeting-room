<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\BookingStatusHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookingStatusHistory>
 */
class BookingStatusHistoryFactory extends Factory
{
    protected $model = BookingStatusHistory::class;

    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'from_status' => 'draft',
            'to_status' => 'submitted',
            'changed_by_user_id' => User::factory(),
            'change_reason' => null,
            'changed_at' => now(),
        ];
    }
}
