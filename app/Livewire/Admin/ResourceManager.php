<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Enums\ResourceType;
use App\Enums\RoomApprovalMode;
use App\Enums\RoomStatus;
use App\Models\Resource;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Stage 3 E2b (UI) — manage non-room bookable resources (equipment, vehicles,
 * desks). Rooms keep their dedicated admin (facilities / operating hours /
 * blocks); this screen covers the simpler resource types that share the same
 * booking + conflict engine generalized in E2a.
 *
 * Gated by rooms.create / rooms.update (reuses the room-management permissions).
 */
class ResourceManager extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    public string $type = 'equipment';

    public string $code = '';

    public string $name = '';

    public string $location = '';

    public ?int $capacity = 1;

    public string $status = 'active';

    public string $approvalMode = 'none';

    public int $bookingBufferMinutes = 0;

    public string $description = '';

    public string $feedback = '';

    /** Resource types managed here — every type except the dedicated Room admin. */
    private function managedTypes(): array
    {
        return array_values(array_filter(
            ResourceType::values(),
            fn (string $t) => $t !== ResourceType::Room->value,
        ));
    }

    private function guard(string $permission): void
    {
        $user = auth()->user();
        if (! $user instanceof User || ! $user->hasPermission($permission)) {
            abort(403);
        }
    }

    public function newResource(): void
    {
        $this->guard('rooms.create');
        $this->reset(['editingId', 'code', 'name', 'location', 'description']);
        $this->type = 'equipment';
        $this->capacity = 1;
        $this->status = 'active';
        $this->approvalMode = 'none';
        $this->bookingBufferMinutes = 0;
        $this->resetValidation();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->guard('rooms.update');
        $resource = Resource::query()->whereIn('type', $this->managedTypes())->findOrFail($id);
        $this->editingId = $resource->id;
        $this->type = $resource->type->value;
        $this->code = $resource->code;
        $this->name = $resource->name;
        $this->location = $resource->location ?? '';
        $this->capacity = $resource->capacity;
        $this->status = $resource->status->value;
        $this->approvalMode = $resource->approval_mode->value;
        $this->bookingBufferMinutes = $resource->booking_buffer_minutes;
        $this->description = $resource->description ?? '';
        $this->resetValidation();
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->guard($this->editingId !== null ? 'rooms.update' : 'rooms.create');

        $validated = $this->validate([
            'type' => ['required', Rule::in($this->managedTypes())],
            'code' => ['required', 'string', 'max:30', Rule::unique('resources', 'code')->ignore($this->editingId)],
            'name' => ['required', 'string', 'max:150'],
            'location' => ['nullable', 'string', 'max:150'],
            'capacity' => ['required', 'integer', 'min:1', 'max:65535'],
            'status' => ['required', Rule::enum(RoomStatus::class)],
            'approvalMode' => ['required', Rule::enum(RoomApprovalMode::class)],
            'bookingBufferMinutes' => ['required', 'integer', 'min:0', 'max:240'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $payload = [
            'type' => $validated['type'],
            'code' => $validated['code'],
            'name' => $validated['name'],
            'location' => $validated['location'] ?: null,
            'capacity' => $validated['capacity'],
            'status' => $validated['status'],
            'approval_mode' => $validated['approvalMode'],
            'booking_buffer_minutes' => $validated['bookingBufferMinutes'],
            'description' => $validated['description'] ?: null,
            'is_active' => RoomStatus::from($validated['status'])->isBookable(),
        ];

        if ($this->editingId !== null) {
            Resource::query()->whereIn('type', $this->managedTypes())->findOrFail($this->editingId)->update($payload);
            $this->feedback = __('Sumber daya berhasil diperbarui.');
        } else {
            Resource::create($payload);
            $this->feedback = __('Sumber daya berhasil dibuat.');
        }

        $this->showForm = false;
    }

    public function toggle(int $id): void
    {
        $this->guard('rooms.update');
        $resource = Resource::query()->whereIn('type', $this->managedTypes())->findOrFail($id);
        $active = ! $resource->is_active;
        $resource->forceFill([
            'is_active' => $active,
            'status' => $active ? RoomStatus::Active->value : RoomStatus::Inactive->value,
        ])->save();
    }

    public function cancel(): void
    {
        $this->showForm = false;
        $this->resetValidation();
    }

    public function render(): View
    {
        return view('livewire.admin.resource-manager', [
            'resources' => Resource::query()
                ->whereIn('type', $this->managedTypes())
                ->orderBy('type')
                ->orderBy('name')
                ->get(),
            'types' => array_map(fn (string $t) => ResourceType::from($t), $this->managedTypes()),
            'statuses' => RoomStatus::cases(),
            'approvalModes' => RoomApprovalMode::cases(),
        ])->layout('layouts.app', [
            'title' => __('Sumber Daya'),
            'subtitle' => __('Kelola peralatan, kendaraan, dan meja kerja yang dapat dipesan'),
        ]);
    }
}
