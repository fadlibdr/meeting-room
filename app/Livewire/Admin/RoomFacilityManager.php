<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Models\Room;
use App\Models\RoomFacility;
use App\Models\RoomFacilityItem;
use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;

class RoomFacilityManager extends Component
{
    public Room $room;

    // Add form
    public ?int $selectedFacilityId = null;

    public ?int $quantity = 1;

    public bool $isOperational = true;

    public string $notes = '';

    // Inline edit
    public ?int $editingItemId = null;

    public ?int $editQuantity = 1;

    public bool $editIsOperational = true;

    public string $editNotes = '';

    public function mount(Room $room): void
    {
        $this->room = $room;
    }

    private function guard(): void
    {
        $authUser = auth()->user();
        if (! $authUser instanceof User || ! $authUser->hasPermission('rooms.update')) {
            abort(403);
        }
    }

    public function addFacility(): void
    {
        $this->guard();

        $validated = $this->validate([
            'selectedFacilityId' => [
                'required', 'integer',
                Rule::exists('room_facilities', 'id')->where('is_active', true),
                Rule::unique('room_facility_items', 'room_facility_id')->where('room_id', $this->room->id),
            ],
            'quantity' => ['required', 'integer', 'min:1', 'max:65535'],
            'isOperational' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'selectedFacilityId.required' => __('Pilih fasilitas terlebih dahulu.'),
            'selectedFacilityId.exists' => __('Fasilitas tidak ditemukan atau nonaktif.'),
            'selectedFacilityId.unique' => __('Fasilitas ini sudah ditambahkan ke ruang.'),
            'quantity.min' => 'Jumlah minimal 1.',
        ]);

        RoomFacilityItem::create([
            'room_id' => $this->room->id,
            'room_facility_id' => $validated['selectedFacilityId'],
            'quantity' => $validated['quantity'],
            'is_operational' => $validated['isOperational'] ?? true,
            'notes' => $validated['notes'] ?: null,
        ]);

        $this->reset(['selectedFacilityId', 'quantity', 'isOperational', 'notes']);
        session()->flash('facility_status', __('Fasilitas ditambahkan ke ruang.'));
    }

    public function startEdit(int $itemId): void
    {
        $item = RoomFacilityItem::query()->where('room_id', $this->room->id)->findOrFail($itemId);
        $this->editingItemId = $item->id;
        $this->editQuantity = $item->quantity;
        $this->editIsOperational = $item->is_operational;
        $this->editNotes = $item->notes ?? '';
    }

    public function cancelEdit(): void
    {
        $this->reset(['editingItemId', 'editQuantity', 'editIsOperational', 'editNotes']);
    }

    public function saveEdit(): void
    {
        $this->guard();

        if ($this->editingItemId === null) {
            return;
        }

        $validated = $this->validate([
            'editQuantity' => ['required', 'integer', 'min:1', 'max:65535'],
            'editIsOperational' => ['boolean'],
            'editNotes' => ['nullable', 'string', 'max:1000'],
        ], [
            'editQuantity.min' => 'Jumlah minimal 1.',
        ]);

        $item = RoomFacilityItem::query()->where('room_id', $this->room->id)->findOrFail($this->editingItemId);
        $item->update([
            'quantity' => $validated['editQuantity'],
            'is_operational' => $validated['editIsOperational'] ?? true,
            'notes' => $validated['editNotes'] ?: null,
        ]);

        $this->cancelEdit();
        session()->flash('facility_status', __('Fasilitas diperbarui.'));
    }

    public function remove(int $itemId): void
    {
        $this->guard();
        RoomFacilityItem::query()->where('room_id', $this->room->id)->findOrFail($itemId)->delete();
        session()->flash('facility_status', __('Fasilitas dihapus dari ruang.'));
    }

    public function render(): View
    {
        $assignedIds = $this->room->facilityItems()->pluck('room_facility_id');

        return view('livewire.admin.room-facility-manager', [
            'items' => $this->room->facilityItems()->with('facility')->get(),
            'availableFacilities' => RoomFacility::query()
                ->where('is_active', true)
                ->whereNotIn('id', $assignedIds)
                ->orderBy('name')
                ->get(),
        ]);
    }
}
