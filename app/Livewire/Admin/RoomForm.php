<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Enums\RoomApprovalMode;
use App\Enums\RoomStatus;
use App\Models\Room;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;

class RoomForm extends Component
{
    public ?Room $room = null;

    public bool $isEditMode = false;

    public string $code = '';

    public string $name = '';

    public string $location = '';

    public string $floor = '';

    public ?int $capacity = null;

    public string $status = 'active';

    public string $approvalMode = 'unit_approver';

    public int $bookingBufferMinutes = 0;

    public string $description = '';

    public function mount(?Room $room = null): void
    {
        if ($room && $room->exists) {
            $this->isEditMode = true;
            $this->room = $room;
            $this->code = $room->code;
            $this->name = $room->name;
            $this->location = $room->location ?? '';
            $this->floor = $room->floor ?? '';
            $this->capacity = $room->capacity;
            $this->status = $room->status->value;
            $this->approvalMode = $room->approval_mode->value;
            $this->bookingBufferMinutes = $room->booking_buffer_minutes;
            $this->description = $room->description ?? '';
        }
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    protected function rules(): array
    {
        $roomId = $this->room?->id;

        return [
            'code' => ['required', 'string', 'max:30', 'unique:rooms,code'.($roomId ? ','.$roomId : '')],
            'name' => ['required', 'string', 'max:150'],
            'location' => ['nullable', 'string', 'max:150'],
            'floor' => ['nullable', 'string', 'max:30'],
            'capacity' => ['required', 'integer', 'min:1', 'max:65535'],
            'status' => ['required', Rule::enum(RoomStatus::class)],
            'approvalMode' => ['required', Rule::enum(RoomApprovalMode::class)],
            'bookingBufferMinutes' => ['required', 'integer', 'min:0', 'max:240'],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'code.required' => __('Kode ruang wajib diisi.'),
            'code.unique' => __('Kode ruang sudah digunakan.'),
            'name.required' => __('Nama ruang wajib diisi.'),
            'capacity.required' => __('Kapasitas wajib diisi.'),
            'capacity.min' => 'Kapasitas minimal 1.',
            'status.required' => __('Status wajib dipilih.'),
            'approvalMode.required' => __('Mode approval wajib dipilih.'),
            'bookingBufferMinutes.min' => __('Buffer tidak boleh negatif.'),
        ];
    }

    public function save(): void
    {
        $authUser = auth()->user();
        $permission = $this->isEditMode ? 'rooms.update' : 'rooms.create';

        if (! $authUser instanceof User || ! $authUser->hasPermission($permission)) {
            abort(403);
        }

        $validated = $this->validate();

        DB::transaction(function () use ($validated) {
            $payload = [
                'code' => $validated['code'],
                'name' => $validated['name'],
                'location' => $validated['location'] ?: null,
                'floor' => $validated['floor'] ?: null,
                'capacity' => $validated['capacity'],
                'status' => $validated['status'],
                'approval_mode' => $validated['approvalMode'],
                'booking_buffer_minutes' => $validated['bookingBufferMinutes'],
                'description' => $validated['description'] ?: null,
                'is_active' => RoomStatus::from($validated['status'])->isBookable(),
            ];

            if ($this->isEditMode && $this->room instanceof Room) {
                $this->room->update($payload);
            } else {
                Room::create($payload);
            }
        });

        session()->flash('status', $this->isEditMode ? __('Ruang berhasil diperbarui.') : __('Ruang berhasil dibuat.'));
        $this->redirectRoute('admin.rooms.index', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.admin.room-form', [
            'statuses' => RoomStatus::cases(),
            'approvalModes' => RoomApprovalMode::cases(),
            'isEditMode' => $this->isEditMode,
        ]);
    }
}
