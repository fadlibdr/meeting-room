<?php

declare(strict_types=1);

namespace App\Livewire\Booking;

use App\Actions\SubmitBookingAction;
use App\Exceptions\ApprovalRoutingException;
use App\Exceptions\BookingConflictException;
use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Booking submit form — single-page (D2), all fields visible.
 *
 * M1-B status: real fields wired, server-side validate, submit calls
 * SubmitBookingAction. NO live conflict check (that's M1-C). NO visual
 * room picker (that's M1-F — replaces the <select> dropdown).
 *
 * Pre-fill via query string (?room_id=X&starts_at=Y) supported via
 * Livewire 3 #[Url] attributes — used by calendar empty-cell click in M1-D.
 *
 * @property-read \Illuminate\Support\Collection<int, \App\Models\Room> $rooms
 *
 * @see docs/m1-submit-ui-spec.md
 */
class BookingForm extends Component
{
    /** Pre-fillable from query string (?room_id=X). Empty string = unselected. */
    #[Url(as: 'room_id', except: '')]
    public string $roomId = '';

    /** Pre-fillable from query string (?starts_at=2026-05-05T10:00). Datetime-local format. */
    #[Url(as: 'starts_at', except: '')]
    public string $startsAt = '';

    public string $endsAt = '';

    public string $subject = '';

    public ?string $agenda = null;

    public int $attendeeCount = 1;

    /**
     * Form-level submit error banner.
     * Field-level errors live in $this->errors via $this->validate().
     */
    public ?string $submitError = null;

    public function mount(): void
    {
        $this->authorize('create', Booking::class);
    }

    /**
     * @return array<string, array<int, string>>
     */
    protected function rules(): array
    {
        return [
            'roomId' => ['required', 'integer', 'exists:rooms,id'],
            'subject' => ['required', 'string', 'min:3', 'max:150'],
            'agenda' => ['nullable', 'string', 'max:5000'],
            'attendeeCount' => ['required', 'integer', 'min:1'],
            'startsAt' => ['required', 'date', 'after:now'],
            'endsAt' => ['required', 'date', 'after:startsAt'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'roomId.required' => 'Silakan pilih ruangan.',
            'roomId.integer' => 'Ruangan yang dipilih tidak valid.',
            'roomId.exists' => 'Ruangan yang dipilih tidak ditemukan.',
            'subject.required' => 'Judul rapat wajib diisi.',
            'subject.min' => 'Judul rapat minimal 3 karakter.',
            'subject.max' => 'Judul rapat maksimal 150 karakter.',
            'agenda.max' => 'Agenda terlalu panjang (maksimal 5000 karakter).',
            'attendeeCount.required' => 'Jumlah peserta wajib diisi.',
            'attendeeCount.integer' => 'Jumlah peserta harus berupa angka.',
            'attendeeCount.min' => 'Jumlah peserta minimal 1 orang.',
            'startsAt.required' => 'Waktu mulai wajib diisi.',
            'startsAt.date' => 'Waktu mulai tidak valid.',
            'startsAt.after' => 'Waktu mulai harus setelah waktu sekarang.',
            'endsAt.required' => 'Waktu selesai wajib diisi.',
            'endsAt.date' => 'Waktu selesai tidak valid.',
            'endsAt.after' => 'Waktu selesai harus setelah waktu mulai.',
        ];
    }

    public function submit(SubmitBookingAction $action): mixed
    {
        $this->submitError = null;

        $validated = $this->validate();

        /** @var User $user */
        $user = auth()->user();

        try {
            $booking = $action->execute($user, [
                'room_id' => (int) $validated['roomId'],
                'subject' => $validated['subject'],
                'agenda' => $validated['agenda'] ?? null,
                'attendee_count' => (int) $validated['attendeeCount'],
                'starts_at' => $this->normalizeDatetime($validated['startsAt']),
                'ends_at' => $this->normalizeDatetime($validated['endsAt']),
            ]);
        } catch (BookingConflictException $e) {
            $this->submitError = 'Slot waktu yang dipilih bentrok dengan jadwal lain. '
                .'Silakan pilih waktu lain.';

            return null;
        } catch (ApprovalRoutingException $e) {
            $this->submitError = $e->getMessage();

            return null;
        }

        session()->flash('success', "Booking {$booking->booking_code} berhasil dibuat.");

        return $this->redirect(route('calendar.index'), navigate: true);
    }

    /**
     * Convert datetime-local input ('2026-05-05T10:00') to a format
     * SubmitBookingAction's CarbonImmutable::parse handles unambiguously.
     *
     * NOTE: Treats input as APP_TIMEZONE-naive. Proper user-timezone
     * handling per Blueprint Dec-09 (@displayDateTime, user.timezone)
     * is M1-H polish work.
     */
    private function normalizeDatetime(string $value): string
    {
        $normalized = str_replace('T', ' ', trim($value));

        // Ensure seconds component is present (datetime-local omits it)
        if (substr_count($normalized, ':') === 1) {
            $normalized .= ':00';
        }

        return $normalized;
    }

    /**
     * Active rooms for the dropdown — replaced by RoomAvailabilityPicker in M1-F.
     *
     * @return Collection<int, Room>
     */
    public function getRoomsProperty(): Collection
    {
        return Room::query()
            ->where('is_active', true)
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'capacity', 'location', 'floor', 'approval_mode']);
    }

    public function render(): View
    {
        return view('livewire.booking.booking-form', [
            'rooms' => $this->rooms,
        ])->layout('layouts.app');
    }
}
