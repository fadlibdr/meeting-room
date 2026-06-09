<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Enums\RoomApprovalMode;
use App\Enums\RoomStatus;
use App\Models\ApprovalPolicy;
use App\Models\Room;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithFileUploads;

class RoomForm extends Component
{
    use WithFileUploads;

    public ?Room $room = null;

    public bool $isEditMode = false;

    public string $code = '';

    public string $name = '';

    public string $location = '';

    public string $floor = '';

    public ?int $capacity = null;

    public string $status = 'active';

    public string $approvalMode = 'unit_approver';

    public ?int $approvalPolicyId = null;

    public int $bookingBufferMinutes = 0;

    public string $description = '';

    /** Newly selected upload (temporary), if any. */
    public $photo = null;

    /** Existing stored photo path (edit mode), for preview. */
    public ?string $existingPhotoPath = null;

    /** When true, the existing photo is removed on save. */
    public bool $removePhoto = false;

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
            $this->approvalPolicyId = $room->approval_policy_id;
            $this->bookingBufferMinutes = $room->booking_buffer_minutes;
            $this->description = $room->description ?? '';
            $this->existingPhotoPath = $room->photo_path;
        }
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    protected function rules(): array
    {
        $roomId = $this->room?->id;

        return [
            'code' => ['required', 'string', 'max:30', Rule::unique('resources', 'code')->where('type', 'room')->ignore($roomId)],
            'name' => ['required', 'string', 'max:150'],
            'location' => ['nullable', 'string', 'max:150'],
            'floor' => ['nullable', 'string', 'max:30'],
            'capacity' => ['required', 'integer', 'min:1', 'max:65535'],
            'status' => ['required', Rule::enum(RoomStatus::class)],
            'approvalMode' => ['required', Rule::enum(RoomApprovalMode::class)],
            'approvalPolicyId' => ['nullable', 'integer', 'exists:approval_policies,id'],
            'bookingBufferMinutes' => ['required', 'integer', 'min:0', 'max:240'],
            'description' => ['nullable', 'string', 'max:2000'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
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
            'photo.image' => __('Berkas harus berupa gambar.'),
            'photo.mimes' => __('Format foto harus JPG, PNG, atau WEBP.'),
            'photo.max' => __('Ukuran foto maksimal 4 MB.'),
        ];
    }

    /** Discard the just-selected (not yet saved) upload. */
    public function clearPhoto(): void
    {
        $this->photo = null;
    }

    public function save(): void
    {
        $authUser = auth()->user();
        $permission = $this->isEditMode ? 'rooms.update' : 'rooms.create';

        if (! $authUser instanceof User || ! $authUser->hasPermission($permission)) {
            abort(403);
        }

        $validated = $this->validate();

        // Resolve the photo path + which old file (if any) to delete, OUTSIDE the
        // transaction (filesystem work shouldn't sit inside the DB transaction).
        $oldPath = $this->isEditMode ? $this->existingPhotoPath : null;
        $photoPath = $oldPath;
        $pathToDelete = null;

        if ($this->photo !== null) {
            $photoPath = $this->photo->store('room-photos', 'public');
            $pathToDelete = $oldPath; // replacing → delete the previous file
        } elseif ($this->removePhoto) {
            $photoPath = null;
            $pathToDelete = $oldPath;
        }

        DB::transaction(function () use ($validated, $photoPath) {
            $payload = [
                'code' => $validated['code'],
                'name' => $validated['name'],
                'location' => $validated['location'] ?: null,
                'floor' => $validated['floor'] ?: null,
                'capacity' => $validated['capacity'],
                'status' => $validated['status'],
                'approval_mode' => $validated['approvalMode'],
                'approval_policy_id' => $validated['approvalPolicyId'] ?: null,
                'booking_buffer_minutes' => $validated['bookingBufferMinutes'],
                'description' => $validated['description'] ?: null,
                'photo_path' => $photoPath,
                'is_active' => RoomStatus::from($validated['status'])->isBookable(),
            ];

            if ($this->isEditMode && $this->room instanceof Room) {
                $this->room->update($payload);
            } else {
                Room::create($payload);
            }
        });

        // Only remove the old file once the DB write has committed.
        if ($pathToDelete !== null && $pathToDelete !== $photoPath) {
            Storage::disk('public')->delete($pathToDelete);
        }

        // The temporary upload has been consumed by store(); clear it so a
        // re-render never tries to preview a moved file.
        $this->photo = null;
        $this->removePhoto = false;
        $this->existingPhotoPath = $photoPath;

        session()->flash('status', $this->isEditMode ? __('Ruang berhasil diperbarui.') : __('Ruang berhasil dibuat.'));
        $this->redirectRoute('admin.rooms.index', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.admin.room-form', [
            'statuses' => RoomStatus::cases(),
            'approvalModes' => RoomApprovalMode::cases(),
            'approvalPolicies' => ApprovalPolicy::where('is_active', true)->orderBy('name')->get(),
            'isEditMode' => $this->isEditMode,
        ]);
    }
}
