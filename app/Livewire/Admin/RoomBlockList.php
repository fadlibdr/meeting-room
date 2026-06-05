<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Actions\CancelRecurringRoomBlockAction;
use App\Actions\CancelRoomBlockAction;
use App\Models\RoomBlockSchedule;
use App\Models\User;
use DomainException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class RoomBlockList extends Component
{
    use WithPagination;

    #[Url(as: 'status', except: 'active')]
    public string $statusFilter = 'active';

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function cancel(int $blockId): void
    {
        $authUser = auth()->user();
        if (! $authUser instanceof User || ! $authUser->hasPermission('rooms.manage-blocks')) {
            abort(403);
        }

        $block = RoomBlockSchedule::findOrFail($blockId);

        try {
            app(CancelRoomBlockAction::class)->execute($block, $authUser);
            session()->flash('status', 'Blokir ruang berhasil dibatalkan.');
        } catch (DomainException) {
            session()->flash('status', 'Blokir ruang sudah dibatalkan sebelumnya.');
        }
    }

    public function cancelSeries(int $blockId): void
    {
        $authUser = auth()->user();
        if (! $authUser instanceof User || ! $authUser->hasPermission('rooms.manage-blocks')) {
            abort(403);
        }

        $block = RoomBlockSchedule::findOrFail($blockId);
        $count = app(CancelRecurringRoomBlockAction::class)->execute($block, $authUser);

        session()->flash('status', "Seri blokir dibatalkan: {$count} jadwal.");
    }

    public function render(): View
    {
        return view('livewire.admin.room-block-list', [
            'blocks' => $this->buildQuery(),
        ]);
    }

    /**
     * @return LengthAwarePaginator<int, RoomBlockSchedule>
     */
    private function buildQuery(): LengthAwarePaginator
    {
        $query = RoomBlockSchedule::query()->with('room')->orderByDesc('starts_at');

        if ($this->statusFilter === 'active') {
            $query->where('is_active', true)->whereNull('cancelled_at');
        } elseif ($this->statusFilter === 'cancelled') {
            $query->whereNotNull('cancelled_at');
        }

        return $query->paginate(15);
    }
}
