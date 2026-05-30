<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Models\Room;
use App\Models\RoomOperatingHour;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Component;

class RoomOperatingHoursManager extends Component
{
    /**
     * Day labels in business-week display order (Mon-Sun). Keys are Carbon's
     * dayOfWeek values (0=Sunday .. 6=Saturday) — the SAME convention
     * BookingConflictService and BookingCalendar use to look these rows up, so
     * the keys must stay aligned with Carbon::dayOfWeek.
     *
     * @var array<int, string>
     */
    public const DAY_LABELS = [
        1 => 'Senin',
        2 => 'Selasa',
        3 => 'Rabu',
        4 => 'Kamis',
        5 => 'Jumat',
        6 => 'Sabtu',
        0 => 'Minggu',
    ];

    public Room $room;

    /** @var array<int, bool> */
    public array $isClosed = [];

    /** @var array<int, string> */
    public array $openTime = [];

    /** @var array<int, string> */
    public array $closeTime = [];

    public function mount(Room $room): void
    {
        $this->room = $room;

        $existing = RoomOperatingHour::query()
            ->where('room_id', $room->id)
            ->get()
            ->keyBy('day_of_week');

        foreach (array_keys(self::DAY_LABELS) as $day) {
            $row = $existing->get($day);

            if ($row !== null) {
                $this->isClosed[$day] = $row->is_closed;
                $this->openTime[$day] = $row->open_time !== null ? substr($row->open_time, 0, 5) : '';
                $this->closeTime[$day] = $row->close_time !== null ? substr($row->close_time, 0, 5) : '';

                continue;
            }

            // No row yet: default to the seeded convention (weekdays 08:00-17:00,
            // weekends closed). Persisted explicitly on first save.
            $isWeekend = $day === 0 || $day === 6;
            $this->isClosed[$day] = $isWeekend;
            $this->openTime[$day] = $isWeekend ? '' : '08:00';
            $this->closeTime[$day] = $isWeekend ? '' : '17:00';
        }
    }

    private function guard(): void
    {
        $authUser = auth()->user();
        if (! $authUser instanceof User || ! $authUser->hasPermission('rooms.update')) {
            abort(403);
        }
    }

    public function save(): void
    {
        $this->guard();

        $rules = [];
        foreach (array_keys(self::DAY_LABELS) as $day) {
            $rules["isClosed.$day"] = ['boolean'];
            if (! ($this->isClosed[$day] ?? false)) {
                $rules["openTime.$day"] = ['required', 'date_format:H:i'];
                $rules["closeTime.$day"] = ['required', 'date_format:H:i'];
            }
        }

        $this->validate($rules, [
            'openTime.*.required' => 'Jam buka wajib diisi untuk hari yang tidak ditutup.',
            'openTime.*.date_format' => 'Format jam buka tidak valid.',
            'closeTime.*.required' => 'Jam tutup wajib diisi untuk hari yang tidak ditutup.',
            'closeTime.*.date_format' => 'Format jam tutup tidak valid.',
        ]);

        $hasTimeError = false;
        foreach (array_keys(self::DAY_LABELS) as $day) {
            if (! ($this->isClosed[$day] ?? false) && ($this->closeTime[$day] ?? '') <= ($this->openTime[$day] ?? '')) {
                $this->addError("closeTime.$day", 'Jam tutup harus setelah jam buka.');
                $hasTimeError = true;
            }
        }

        if ($hasTimeError) {
            return;
        }

        DB::transaction(function () {
            foreach (array_keys(self::DAY_LABELS) as $day) {
                $closed = $this->isClosed[$day] ?? false;

                $this->room->operatingHours()->updateOrCreate(
                    ['day_of_week' => $day],
                    [
                        'is_closed' => $closed,
                        'open_time' => $closed ? null : ($this->openTime[$day] ?? '').':00',
                        'close_time' => $closed ? null : ($this->closeTime[$day] ?? '').':00',
                    ]
                );
            }
        });

        session()->flash('hours_status', 'Jam operasional ruang berhasil disimpan.');
    }

    public function render(): View
    {
        return view('livewire.admin.room-operating-hours-manager', [
            'dayLabels' => self::DAY_LABELS,
        ]);
    }
}
