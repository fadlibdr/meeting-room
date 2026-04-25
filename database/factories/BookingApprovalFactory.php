<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\BookingApproval;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookingApproval>
 */
class BookingApprovalFactory extends Factory
{
    protected $model = BookingApproval::class;

    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'sequence_no' => 1,
            'approver_user_id' => User::factory(),
            'status' => 'pending',
            'action_at' => null,
            'action_notes' => null,
            'acted_by_user_id' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => 'approved',
            'action_at' => now(),
            'action_notes' => 'Disetujui',
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => [
            'status' => 'rejected',
            'action_at' => now(),
            'action_notes' => 'Ditolak karena konflik jadwal',
        ]);
    }
}
