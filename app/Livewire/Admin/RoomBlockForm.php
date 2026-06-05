<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Actions\BlockRoomAction;
use App\Actions\CreateRecurringRoomBlockAction;
use App\Enums\BookingStatus;
use App\Enums\RecurrenceFrequency;
use App\Enums\RoomBlockType;
use App\Exceptions\RoomBlockConflictException;
use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use App\Services\RecurrenceExpander;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;

class RoomBlockForm extends Component
{
    public ?int $roomId = null;

    public string $blockType = 'maintenance';

    public string $title = '';

    public string $reason = '';

    public string $startsAt = '';

    public string $endsAt = '';

    public bool $cancelConflicting = false;

    // ── Recurrence ──────────────────────────────────────────────────
    public bool $recurring = false;

    public string $recurrenceFrequency = 'weekly';

    public int $recurrenceInterval = 1;

    public string $recurrenceEnd = 'count';

    public int $recurrenceCount = 4;

    public string $recurrenceUntil = '';

    /**
     * @return array<string, array<int, mixed>|string>
     */
    protected function rules(): array
    {
        $rules = [
            'roomId' => ['required', 'integer', Rule::exists('rooms', 'id')],
            'blockType' => ['required', Rule::enum(RoomBlockType::class)],
            'title' => ['required', 'string', 'max:150'],
            'reason' => ['nullable', 'string', 'max:2000'],
            'startsAt' => ['required', 'date'],
            'endsAt' => ['required', 'date', 'after:startsAt'],
        ];

        if ($this->recurring) {
            $rules['recurrenceFrequency'] = ['required', 'in:daily,weekly,monthly'];
            $rules['recurrenceInterval'] = ['required', 'integer', 'min:1', 'max:12'];
            $rules['recurrenceEnd'] = ['required', 'in:count,until'];
            $rules['recurrenceCount'] = ['required_if:recurrenceEnd,count', 'integer', 'min:1', 'max:'.RecurrenceExpander::MAX_OCCURRENCES];
            $rules['recurrenceUntil'] = ['required_if:recurrenceEnd,until', 'nullable', 'date', 'after:startsAt'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'roomId.required' => 'Pilih ruang terlebih dahulu.',
            'title.required' => 'Judul blokir wajib diisi.',
            'startsAt.required' => 'Waktu mulai wajib diisi.',
            'endsAt.required' => 'Waktu selesai wajib diisi.',
            'endsAt.after' => 'Waktu selesai harus setelah waktu mulai.',
        ];
    }

    public function save(): void
    {
        $authUser = auth()->user();
        if (! $authUser instanceof User || ! $authUser->hasPermission('rooms.manage-blocks')) {
            abort(403);
        }

        $this->validate();

        $room = Room::findOrFail((int) $this->roomId);

        if ($this->recurring) {
            $until = ($this->recurrenceEnd === 'until' && $this->recurrenceUntil !== '')
                ? CarbonImmutable::parse($this->recurrenceUntil)->endOfDay()
                : null;

            $series = app(CreateRecurringRoomBlockAction::class)->execute(
                $room,
                $authUser,
                RoomBlockType::from($this->blockType),
                $this->title,
                CarbonImmutable::parse($this->startsAt),
                CarbonImmutable::parse($this->endsAt),
                RecurrenceFrequency::from($this->recurrenceFrequency),
                $this->recurrenceInterval,
                $until,
                $this->recurrenceEnd === 'count' ? $this->recurrenceCount : null,
                reason: $this->reason !== '' ? $this->reason : null,
                cancelConflictingBookings: $this->cancelConflicting,
            );

            if ($series['created']->isEmpty()) {
                $this->addError('conflict', 'Tidak ada jadwal blokir yang dapat dibuat — semua bentrok dengan reservasi. Centang opsi di bawah untuk membatalkan booking, lalu simpan kembali.');

                return;
            }

            $createdCount = $series['created']->count();
            $skippedCount = count($series['skipped']);
            $message = "Seri blokir dibuat: {$createdCount} jadwal."
                .($skippedCount > 0 ? " {$skippedCount} dilewati karena bentrok dengan reservasi." : '');
            session()->flash('status', $message);
            $this->redirectRoute('admin.room-blocks.index', navigate: true);

            return;
        }

        try {
            app(BlockRoomAction::class)->execute(
                $room,
                $authUser,
                RoomBlockType::from($this->blockType),
                $this->title,
                CarbonImmutable::parse($this->startsAt),
                CarbonImmutable::parse($this->endsAt),
                reason: $this->reason !== '' ? $this->reason : null,
                cancelConflictingBookings: $this->cancelConflicting,
            );
        } catch (RoomBlockConflictException) {
            $this->addError('conflict', 'Ada booking yang bentrok dengan jadwal blokir ini. Centang opsi di bawah untuk membatalkan booking tersebut, lalu simpan kembali.');

            return;
        }

        session()->flash('status', 'Blokir ruang berhasil dibuat.');
        $this->redirectRoute('admin.room-blocks.index', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.admin.room-block-form', [
            'rooms' => Room::query()->where('is_active', true)->orderBy('name')->get(),
            'blockTypes' => RoomBlockType::cases(),
            'conflicts' => $this->previewConflicts(),
        ]);
    }

    /**
     * Live preview of {submitted, approved} bookings the block would clash with.
     */
    private function previewConflicts(): Collection
    {
        if (! $this->roomId || $this->startsAt === '' || $this->endsAt === '') {
            return collect();
        }

        try {
            $start = CarbonImmutable::parse($this->startsAt);
            $end = CarbonImmutable::parse($this->endsAt);
        } catch (\Throwable) {
            return collect();
        }

        if ($end->lessThanOrEqualTo($start)) {
            return collect();
        }

        return Booking::query()
            ->where('room_id', $this->roomId)
            ->whereIn('status', [BookingStatus::Submitted->value, BookingStatus::Approved->value])
            ->where('starts_at', '<', $end)
            ->where('ends_at', '>', $start)
            ->orderBy('starts_at')
            ->get();
    }
}
