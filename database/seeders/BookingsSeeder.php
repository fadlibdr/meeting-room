<?php

namespace Database\Seeders;

use App\Enums\BookingStatus;
use App\Models\Room;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

class BookingsSeeder extends Seeder
{
    public function run(): void
    {
        $rooms = Room::all()->keyBy('code');
        $requesters = User::whereHas('roles', fn ($q) => $q->where('code', 'requester'))
            ->where('is_active', true)
            ->get();

        $approverByUnit = [
            'BIRO-UMUM' => User::where('email', 'budi.santoso@bpjs-kesehatan.go.id')->first(),
            'BIRO-PENGEMBANGAN-IT' => User::where('email', 'siti.rahma@bpjs-kesehatan.go.id')->first(),
            'DIR-KEPESERTAAN' => User::where('email', 'ahmad.hidayat@bpjs-kesehatan.go.id')->first(),
            'DIR-IT' => User::where('email', 'siti.rahma@bpjs-kesehatan.go.id')->first(),
            'DIR-SDM-UMUM' => User::where('email', 'budi.santoso@bpjs-kesehatan.go.id')->first(),
        ];
        $gaAdmin = User::where('email', 'ga.admin@bpjs-kesehatan.go.id')->first();

        // Status distribution per Blueprint §C.4 (~50 bookings).
        // Note: PHP requires array keys to be int|string, so we use enum values here.
        $distribution = [
            BookingStatus::Draft->value => 5,
            BookingStatus::Submitted->value => 8,
            BookingStatus::Approved->value => 15,
            BookingStatus::Rejected->value => 5,
            BookingStatus::Cancelled->value => 5,
            BookingStatus::Completed->value => 12,
        ];

        $now = CarbonImmutable::now('UTC');

        foreach ($distribution as $statusValue => $count) {
            $status = BookingStatus::from($statusValue);
            for ($i = 0; $i < $count; $i++) {

                /** @var Room $room */
                $room = $rooms->random();
                /** @var User $requester */
                $requester = $requesters->random();

                // Pick approver based on requester's unit's approver chain.
                // Use the requester's approver_user_id if set, otherwise fallback by unit.
                $approver = $requester->approver
                    ?? $approverByUnit[$requester->unit?->code ?? 'BIRO-UMUM']
                    ?? User::where('email', 'budi.santoso@bpjs-kesehatan.go.id')->first();

                $startsAt = $this->pickStartTime($status, $now);

                BookingScenarioBuilder::create(
                    room: $room,
                    requester: $requester,
                    approver: $approver,
                    gaAdmin: $gaAdmin,
                    targetStatus: $status,
                    startsAt: $startsAt,
                    durationHours: fake()->numberBetween(1, 4),
                );
            }
        }
    }

    private function pickStartTime(BookingStatus $status, CarbonImmutable $now): CarbonImmutable
    {
        // Past for completed, future for draft/submitted/approved, mixed for cancelled/rejected
        return match ($status) {
            BookingStatus::Completed => $now->subDays(fake()->numberBetween(1, 30))
                ->setTime(fake()->numberBetween(9, 15), 0),
            BookingStatus::Draft, BookingStatus::Submitted, BookingStatus::Approved => $now->addDays(fake()->numberBetween(1, 30))
                ->setTime(fake()->numberBetween(9, 15), 0),
            BookingStatus::Cancelled, BookingStatus::Rejected => $now->addDays(fake()->numberBetween(-15, 15))
                ->setTime(fake()->numberBetween(9, 15), 0),
        };
    }
}
