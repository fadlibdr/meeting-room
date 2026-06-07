<?php

declare(strict_types=1);

namespace App\Http\Requests\Booking;

use App\Models\Booking;
use App\Models\Resource;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation for booking creation/draft submission.
 *
 * Scope (Phase 2 Piece 2):
 * - Input shape: types, presence, format
 * - Cross-field consistency: ends_at > starts_at, duration <= max
 * - Capacity check: attendee_count <= room.capacity
 * - Active room check: room.is_active=true
 * - Authorization at request level: user can create
 *
 * Out of scope (handled by Action layer):
 * - Slot conflict detection (BookingConflictService)
 * - Status transitions (draft vs submitted determined by source)
 * - Approver assignment
 * - Race-safe room locking
 *
 * Admin overrides (override_capacity, retrospective) are deferred to Phase 3
 * per Phase 2 architectural decision. All rules are strict here.
 */
class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Booking::class) ?? false;
    }

    /**
     * Accept the deprecated `room_id` field as an alias for `resource_id` (E3).
     */
    protected function prepareForValidation(): void
    {
        if (! $this->has('resource_id') && $this->has('room_id')) {
            $this->merge(['resource_id' => $this->input('room_id')]);
        }
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'resource_id' => ['required', 'integer', Rule::exists('resources', 'id')],
            'subject' => ['required', 'string', 'max:150'],
            'agenda' => ['nullable', 'string', 'max:5000'],
            'attendee_count' => ['required', 'integer', 'min:1'],
            'starts_at' => ['required', 'date', 'after:now'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'resource_id.required' => 'Ruangan wajib dipilih.',
            'resource_id.integer' => 'ID ruangan tidak valid.',
            'resource_id.exists' => 'Ruangan yang dipilih tidak ditemukan.',
            'subject.required' => 'Judul rapat wajib diisi.',
            'subject.string' => 'Judul rapat harus berupa teks.',
            'subject.max' => 'Judul rapat maksimal 150 karakter.',
            'agenda.string' => 'Agenda harus berupa teks.',
            'agenda.max' => 'Agenda maksimal 5000 karakter.',
            'attendee_count.required' => 'Jumlah peserta wajib diisi.',
            'attendee_count.integer' => 'Jumlah peserta harus berupa angka.',
            'attendee_count.min' => 'Jumlah peserta minimal 1 orang.',
            'starts_at.required' => 'Waktu mulai wajib diisi.',
            'starts_at.date' => 'Format waktu mulai tidak valid.',
            'starts_at.after' => 'Waktu mulai harus di masa depan.',
            'ends_at.required' => 'Waktu selesai wajib diisi.',
            'ends_at.date' => 'Format waktu selesai tidak valid.',
            'ends_at.after' => 'Waktu selesai harus setelah waktu mulai.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $this->validateMaxDuration($validator);
            $this->validateRoomActiveAndCapacity($validator);
        });
    }

    private function validateMaxDuration(Validator $validator): void
    {
        $startsAt = $this->date('starts_at');
        $endsAt = $this->date('ends_at');

        if ($startsAt === null || $endsAt === null) {
            return;
        }

        $maxHours = (int) config('meeting_room.max_booking_duration_hours', 8);
        $durationHours = $startsAt->diffInMinutes($endsAt) / 60;

        if ($durationHours > $maxHours) {
            $validator->errors()->add(
                'ends_at',
                "Durasi rapat melebihi batas {$maxHours} jam. Untuk acara lebih panjang, hubungi GA Admin."
            );
        }
    }

    private function validateRoomActiveAndCapacity(Validator $validator): void
    {
        $roomId = $this->input('resource_id');
        $attendeeCount = (int) $this->input('attendee_count', 0);

        if (! is_numeric($roomId)) {
            return;
        }

        $room = Resource::find($roomId);

        if ($room === null) {
            return;
        }

        if (! $room->is_active) {
            $validator->errors()->add(
                'resource_id',
                'Ruangan yang dipilih tidak aktif.'
            );

            return;
        }

        if ($attendeeCount > $room->capacity) {
            $validator->errors()->add(
                'attendee_count',
                "Jumlah peserta ({$attendeeCount}) melebihi kapasitas ruangan ({$room->capacity})."
            );
        }
    }
}
