<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Enums\BookingStatus;
use App\Enums\RoomApprovalMode;
use App\Enums\RoomStatus;
use App\Models\Room;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class RoomList extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'status', except: 'all')]
    public string $statusFilter = 'all';

    #[Url(as: 'mode', except: '')]
    public string $approvalFilter = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedApprovalFilter(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'statusFilter', 'approvalFilter']);
        $this->statusFilter = 'all';
        $this->resetPage();
    }

    /** Deactivate (temporarily unbookable). Non-destructive to bookings. */
    public function deactivate(int $roomId): void
    {
        $room = $this->authorizedRoom($roomId, 'rooms.delete');
        $future = $this->futureBookingCount($room);
        $room->update(['status' => RoomStatus::Inactive, 'is_active' => false]);
        $this->flashStatusChange(__('Ruang dinonaktifkan'), $future);
    }

    /** Archive (permanent retirement — Dec-06 replacement for delete). */
    public function archive(int $roomId): void
    {
        $room = $this->authorizedRoom($roomId, 'rooms.delete');
        $future = $this->futureBookingCount($room);
        $room->update(['status' => RoomStatus::Archived, 'is_active' => false]);
        $this->flashStatusChange(__('Ruang diarsipkan'), $future);
    }

    /** Reactivate an inactive/archived room. */
    public function activate(int $roomId): void
    {
        $room = $this->authorizedRoom($roomId, 'rooms.update');
        $room->update(['status' => RoomStatus::Active, 'is_active' => true]);
        session()->flash('status', __('Ruang diaktifkan kembali.'));
    }

    /** Mirrors UserList: permission-based guard via User::hasPermission(). */
    private function authorizedRoom(int $roomId, string $permission): Room
    {
        $authUser = auth()->user();

        if (! $authUser instanceof User || ! $authUser->hasPermission($permission)) {
            abort(403);
        }

        return Room::findOrFail($roomId);
    }

    /** Future submitted/approved bookings remain valid after deactivate (§2.3). */
    private function futureBookingCount(Room $room): int
    {
        return $room->bookings()
            ->whereIn('status', [BookingStatus::Submitted, BookingStatus::Approved])
            ->where('starts_at', '>', now())
            ->count();
    }

    private function flashStatusChange(string $base, int $future): void
    {
        $msg = $future > 0
            ? __(':base. Catatan: :count booking mendatang tetap berlaku dan tidak dibatalkan.', ['base' => $base, 'count' => $future])
            : $base.'.';
        session()->flash('status', $msg);
    }

    public function render(): View
    {
        return view('livewire.admin.room-list', [
            'rooms' => $this->buildRoomsQuery(),
            'approvalModes' => RoomApprovalMode::cases(),
        ]);
    }

    /**
     * @return LengthAwarePaginator<int, Room>
     */
    private function buildRoomsQuery(): LengthAwarePaginator
    {
        $query = Room::query()->orderBy('name');

        if ($this->search !== '') {
            $term = '%'.$this->search.'%';
            $query->where(function ($q) use ($term) {
                $q->where('code', 'like', $term)
                    ->orWhere('name', 'like', $term)
                    ->orWhere('location', 'like', $term);
            });
        }

        if ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        if ($this->approvalFilter !== '') {
            $query->where('approval_mode', $this->approvalFilter);
        }

        return $query->paginate(15);
    }
}
