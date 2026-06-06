<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Models\Unit;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class UnitList extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'status', except: 'all')]
    public string $statusFilter = 'all';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'statusFilter']);
        $this->statusFilter = 'all';
        $this->resetPage();
    }

    /** Single is_active flip, mirroring FacilityList::toggleActive. Gated by users.update. */
    public function toggleActive(int $unitId): void
    {
        $authUser = auth()->user();

        if (! $authUser instanceof User || ! $authUser->hasPermission('users.update')) {
            abort(403);
        }

        $unit = Unit::findOrFail($unitId);
        $unit->update(['is_active' => ! $unit->is_active]);

        session()->flash('status', $unit->is_active ? __('Unit diaktifkan.') : __('Unit dinonaktifkan.'));
    }

    public function render(): View
    {
        return view('livewire.admin.unit-list', [
            'units' => $this->buildQuery(),
        ]);
    }

    /**
     * @return LengthAwarePaginator<int, Unit>
     */
    private function buildQuery(): LengthAwarePaginator
    {
        $query = Unit::query()
            ->with('parent')
            ->withCount('users')
            ->orderBy('name');

        if ($this->search !== '') {
            $term = '%'.$this->search.'%';
            $query->where(function ($q) use ($term) {
                $q->where('code', 'like', $term)->orWhere('name', 'like', $term);
            });
        }

        if ($this->statusFilter === 'active') {
            $query->where('is_active', true);
        } elseif ($this->statusFilter === 'inactive') {
            $query->where('is_active', false);
        }

        return $query->paginate(15);
    }
}
