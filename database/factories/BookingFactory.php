<?php

namespace Database\Factories;

use App\Enums\BookingStatus;
use App\Enums\RoomApprovalMode;
use App\Models\Booking;
use App\Models\Room;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    protected $model = Booking::class;

    public function definition(): array
    {
        $start = $this->faker->dateTimeBetween('-30 days', '+30 days');
        $end = (clone $start)->modify('+'.$this->faker->numberBetween(1, 4).' hours');
        $datePart = $start->format('Ymd');

        return [
            'booking_code' => "BKG-{$datePart}-".str_pad((string) $this->faker->unique()->randomNumber(4), 4, '0', STR_PAD_LEFT),
            'room_id' => Room::factory(),
            'requester_user_id' => User::factory(),
            'requester_unit_id' => Unit::factory(),
            'created_by_user_id' => User::factory(),
            'updated_by_user_id' => null,
            'subject' => $this->faker->randomElement([
                'Rapat Koordinasi Tim',
                'Briefing Mingguan',
                'Diskusi Strategi Q4',
                'Town Hall Meeting',
                'Presentasi Vendor',
                'Workshop Internal',
                'Rapat Anggaran',
                'Sosialisasi Kebijakan',
            ]),
            'agenda' => $this->faker->paragraph(),
            'attendee_count' => $this->faker->numberBetween(2, 20),
            'starts_at' => $start,
            'ends_at' => $end,
            'status' => BookingStatus::Draft,
            'source' => 'user',
            'approval_mode_snapshot' => RoomApprovalMode::UnitApprover,
            'current_approval_step' => null,
            'current_approver_user_id' => null,
            'submitted_at' => null,
            'approved_at' => null,
            'rejected_at' => null,
            'cancelled_at' => null,
            'completed_at' => null,
            'rejection_reason' => null,
            'cancellation_reason' => null,
            'rescheduled_from_booking_id' => null,
            'recurrence_group_id' => null,
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn () => [
            'status' => BookingStatus::Submitted,
            'submitted_at' => now(),
            'current_approval_step' => 1,
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => BookingStatus::Approved,
            'submitted_at' => now()->subDays(2),
            'approved_at' => now()->subDays(1),
            'current_approval_step' => null,
            'current_approver_user_id' => null,
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => [
            'status' => BookingStatus::Rejected,
            'submitted_at' => now()->subDays(2),
            'rejected_at' => now()->subDay(),
            'rejection_reason' => 'Konflik dengan jadwal lain',
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => BookingStatus::Cancelled,
            'cancelled_at' => now(),
            'cancellation_reason' => 'Acara ditunda',
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => BookingStatus::Completed,
            'submitted_at' => now()->subDays(5),
            'approved_at' => now()->subDays(4),
            'completed_at' => now()->subDay(),
        ]);
    }
}
