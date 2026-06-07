<?php

declare(strict_types=1);

namespace App\Livewire\Booking;

use App\Actions\CreateRecurringBookingAction;
use App\Actions\RescheduleBookingAction;
use App\Actions\SubmitBookingAction;
use App\Actions\UpdateBookingAction;
use App\DataTransferObjects\ConflictItem;
use App\Enums\RecurrenceFrequency;
use App\Enums\ResourceType;
use App\Exceptions\ApprovalRoutingException;
use App\Exceptions\BookingConflictException;
use App\Models\Booking;
use App\Models\Resource;
use App\Models\User;
use App\Services\BookingConflictService;
use App\Services\RecurrenceExpander;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Throwable;

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
 * @property-read Collection<int, \App\Models\Resource> $rooms
 *
 * @see docs/m1-submit-ui-spec.md
 */
class BookingForm extends Component
{
    /**
     * Booking being edited (M3-C). Set by mount() when the component is
     * mounted on the bookings/{booking}/edit route. Null = create mode.
     */
    public ?int $bookingId = null;

    /**
     * Form mode — 'create' | 'edit' | 'reschedule' (M3-E). In reschedule
     * mode $bookingId is the SOURCE booking; submit() cancels it and
     * creates a replacement via RescheduleBookingAction.
     */
    public string $mode = 'create';

    /** Pre-fillable from query string (?room_id=X). Empty string = unselected. */
    #[Url(as: 'room_id', except: '')]
    public string $roomId = '';

    /** Which resource type is being booked (room/equipment/vehicle/desk) — Stage 3 E2c. */
    #[Url(as: 'type', except: 'room')]
    public string $resourceType = 'room';

    /** Pre-fillable from query string (?starts_at=2026-05-05T10:00). Datetime-local format. */
    #[Url(as: 'starts_at', except: '')]
    public string $startsAt = '';

    public string $endsAt = '';

    public string $subject = '';

    public ?string $agenda = null;

    public int $attendeeCount = 1;

    // ── Recurrence (create mode only) ───────────────────────────────
    /** Toggle: when true, submit() creates a recurring series. */
    public bool $recurring = false;

    /** daily | weekly | monthly */
    public string $recurrenceFrequency = 'weekly';

    /** Every N units. */
    public int $recurrenceInterval = 1;

    /** count | until */
    public string $recurrenceEnd = 'count';

    /** Total occurrences when $recurrenceEnd === 'count'. */
    public int $recurrenceCount = 4;

    /** Inclusive end date (YYYY-MM-DD) when $recurrenceEnd === 'until'. */
    public string $recurrenceUntil = '';

    /**
     * Form-level submit error banner.
     * Field-level errors live in $this->errors via $this->validate().
     */
    public ?string $submitError = null;

    /**
     * Live conflict check status.
     *
     * One of:
     *  - 'unknown'  : no check has run yet, or required fields incomplete
     *  - 'checking' : check in flight (banner shows spinner)
     *  - 'clear'    : slot is bookable
     *  - 'conflict' : slot overlaps existing booking or block
     */
    public string $conflictStatus = 'unknown';

    /**
     * Conflict details when $conflictStatus === 'conflict'.
     * Each entry: ['type' => 'booking|block|operating_hours', 'title' => str,
     *              'starts_at' => str (Y-m-d H:i UTC), 'ends_at' => str (UTC)].
     *
     * @var array<int, array<string, mixed>>
     */
    public array $conflictDetails = [];

    public function mount(?Booking $booking = null, bool $reschedule = false): void
    {
        if ($booking !== null && $booking->exists) {
            if ($reschedule || request()->routeIs('bookings.reschedule')) {
                $this->authorize('reschedule', $booking);
                $this->mode = 'reschedule';
            } else {
                $this->authorize('update', $booking);
                $this->mode = 'edit';
            }

            $this->bookingId = $booking->id;
            $this->roomId = (string) $booking->resource_id;
            $resource = $booking->resource;
            $this->resourceType = $resource instanceof Resource ? $resource->type->value : 'room';
            $this->startsAt = $booking->starts_at->format('Y-m-d\TH:i');
            $this->endsAt = $booking->ends_at->format('Y-m-d\TH:i');
            $this->subject = $booking->subject;
            $this->agenda = $booking->agenda;
            $this->attendeeCount = $booking->attendee_count;

            return;
        }

        $this->authorize('create', Booking::class);
    }

    /**
     * Livewire 3 lifecycle hook — fires after wire:model.live updates roomId.
     */
    public function updatedRoomId(): void
    {
        $this->checkAvailability(app(BookingConflictService::class));
    }

    /**
     * Switching resource type clears the current selection (a room id is not
     * valid under another type) and resets the conflict state — Stage 3 E2c.
     */
    public function updatedResourceType(): void
    {
        $this->roomId = '';
        $this->conflictStatus = 'unknown';
        $this->conflictDetails = [];
    }

    /**
     * Livewire 3 lifecycle hook — fires after wire:model.live updates startsAt.
     */
    public function updatedStartsAt(): void
    {
        $this->checkAvailability(app(BookingConflictService::class));
    }

    /**
     * Livewire 3 lifecycle hook — fires after wire:model.live updates endsAt.
     */
    public function updatedEndsAt(): void
    {
        $this->checkAvailability(app(BookingConflictService::class));
    }

    /**
     * Run a debounced availability check on the current form state.
     *
     * Gating per D3: returns early ('unknown') if any of roomId / startsAt /
     * endsAt is missing. Treats invalid datetime input as 'unknown' rather
     * than surfacing parse errors mid-typing.
     */
    /**
     * Handle 'room-selected' event dispatched from RoomAvailabilityPicker child.
     * Updates $roomId and triggers conflict re-check (mirrors live wire:model behavior).
     *
     * Per M1-F-Dec-2=1 (standard Livewire 3 dispatch + listener pattern).
     */
    #[On('room-selected')]
    public function selectRoomFromPicker(int $roomId, BookingConflictService $service): void
    {
        $this->roomId = (string) $roomId;
        $this->checkAvailability($service);
    }

    public function checkAvailability(BookingConflictService $service): void
    {
        $this->conflictDetails = [];

        // D3 gating: all three trigger fields must be set
        if ($this->roomId === '' || $this->startsAt === '' || $this->endsAt === '') {
            $this->conflictStatus = 'unknown';

            return;
        }

        $this->conflictStatus = 'checking';

        try {
            $room = Resource::query()->find((int) $this->roomId);
            if ($room === null || ! $room->is_active) {
                $this->conflictStatus = 'unknown';

                return;
            }

            $startsAt = CarbonImmutable::parse($this->normalizeDatetime($this->startsAt))->utc();
            $endsAt = CarbonImmutable::parse($this->normalizeDatetime($this->endsAt))->utc();

            // Defensive: if user typed reversed times, don't run service
            if ($endsAt->lessThanOrEqualTo($startsAt)) {
                $this->conflictStatus = 'unknown';

                return;
            }

            $conflicts = $service->findConflicts($room, $startsAt, $endsAt, $this->bookingId);

            if ($conflicts->isEmpty()) {
                $this->conflictStatus = 'clear';

                return;
            }

            $this->conflictStatus = 'conflict';
            /** @var array<int, array<string, mixed>> $details */
            $details = $conflicts->map(fn (ConflictItem $item): array => [
                'type' => $item->type,
                'title' => $item->title,
                'starts_at' => $item->startsAt->format('Y-m-d H:i'),
                'ends_at' => $item->endsAt->format('Y-m-d H:i'),
            ])->values()->all();
            $this->conflictDetails = $details;
        } catch (Throwable $e) {
            // Invalid datetime mid-typing, etc. — silent fallback
            $this->conflictStatus = 'unknown';
            $this->conflictDetails = [];
        }
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    protected function rules(): array
    {
        $rules = [
            'resourceType' => ['required', Rule::in(ResourceType::values())],
            'roomId' => ['required', 'integer', Rule::exists('resources', 'id')->where('type', $this->resourceType)],
            'subject' => ['required', 'string', 'min:3', 'max:150'],
            'agenda' => ['nullable', 'string', 'max:5000'],
            'attendeeCount' => ['required', 'integer', 'min:1'],
            'startsAt' => ['required', 'date', 'after:now'],
            'endsAt' => ['required', 'date', 'after:startsAt'],
        ];

        if ($this->mode === 'create' && $this->recurring) {
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

    public function submit(SubmitBookingAction $submitAction, UpdateBookingAction $updateAction, RescheduleBookingAction $rescheduleAction): mixed
    {
        $this->submitError = null;

        $this->validate();

        // Re-run the live check synchronously before delegating. Both actions
        // lockForUpdate + re-check anyway (defense in depth); this catches the
        // common case before transaction overhead.
        $this->checkAvailability(app(BookingConflictService::class));
        // For a recurring series the action skips conflicting occurrences, so a
        // first-occurrence clash should NOT block the whole submit.
        if (! $this->recurring && $this->conflictStatus === 'conflict') {
            $this->submitError = 'Slot waktu yang dipilih bentrok dengan jadwal lain. '
                .'Silakan pilih waktu lain.';

            return null;
        }

        /** @var User $user */
        $user = auth()->user();

        $payload = [
            'resource_id' => (int) $this->roomId,
            'subject' => $this->subject,
            'agenda' => $this->agenda,
            'attendee_count' => $this->attendeeCount,
            'starts_at' => $this->normalizeDatetime($this->startsAt),
            'ends_at' => $this->normalizeDatetime($this->endsAt),
        ];

        try {
            if ($this->mode === 'reschedule') {
                $booking = $rescheduleAction->execute(
                    Booking::findOrFail($this->bookingId),
                    $user,
                    $payload,
                );
            } elseif ($this->mode === 'edit') {
                $booking = $updateAction->execute(
                    Booking::findOrFail($this->bookingId),
                    $user,
                    $payload,
                );
            } elseif ($this->recurring) {
                $until = ($this->recurrenceEnd === 'until' && $this->recurrenceUntil !== '')
                    ? CarbonImmutable::parse($this->recurrenceUntil)->endOfDay()
                    : null;
                $series = app(CreateRecurringBookingAction::class)->execute(
                    $user,
                    $payload,
                    RecurrenceFrequency::from($this->recurrenceFrequency),
                    $this->recurrenceInterval,
                    $until,
                    $this->recurrenceEnd === 'count' ? $this->recurrenceCount : null,
                );

                if ($series['created']->isEmpty()) {
                    $this->submitError = 'Tidak ada jadwal yang dapat dibuat — semua tanggal bentrok dengan jadwal lain.';

                    return null;
                }

                $createdCount = $series['created']->count();
                $skippedCount = count($series['skipped']);
                $message = "Seri reservasi dibuat: {$createdCount} jadwal berhasil.";
                if ($skippedCount > 0) {
                    $dates = collect($series['skipped'])->pluck('starts_at')->implode(', ');
                    $message .= " {$skippedCount} dilewati karena bentrok ({$dates}).";
                }
                session()->flash('success', $message);

                return $this->redirect(route('bookings.index'), navigate: true);
            } else {
                $booking = $submitAction->execute($user, $payload);
            }
        } catch (BookingConflictException $e) {
            $this->submitError = 'Slot waktu yang dipilih bentrok dengan jadwal lain. '
                .'Silakan pilih waktu lain.';

            return null;
        } catch (ApprovalRoutingException $e) {
            $this->submitError = $e->getMessage();

            return null;
        } catch (DomainException $e) {
            $this->submitError = $e->getMessage();

            return null;
        }

        if ($this->mode === 'reschedule') {
            session()->flash('success', "Reservasi dijadwalkan ulang. Reservasi baru {$booking->booking_code} telah dibuat.");

            return $this->redirect(route('bookings.show', $booking->id), navigate: true);
        }

        if ($this->mode === 'edit') {
            session()->flash('success', "Reservasi {$booking->booking_code} berhasil diperbarui.");

            return $this->redirect(route('bookings.show', $booking->id), navigate: true);
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
     * is tracked as future work outside M1.
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

    public function render(): View
    {
        return view('livewire.booking.booking-form')
            ->layout('layouts.app', ['title' => __('Buat Reservasi'), 'subtitle' => __('Ajukan pemesanan ruang rapat')]);
    }
}
