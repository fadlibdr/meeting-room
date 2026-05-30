<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Enums\FacilityCategory;
use App\Models\RoomFacility;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class FacilityList extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'cat', except: '')]
    public string $categoryFilter = '';

    #[Url(as: 'status', except: 'all')]
    public string $statusFilter = 'all';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedCategoryFilter(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'categoryFilter', 'statusFilter']);
        $this->statusFilter = 'all';
        $this->resetPage();
    }

    /** Single is_active flip, like UserList::toggleActive. Gated by rooms.update. */
    public function toggleActive(int $facilityId): void
    {
        $authUser = auth()->user();

        if (! $authUser instanceof User || ! $authUser->hasPermission('rooms.update')) {
            abort(403);
        }

        $facility = RoomFacility::findOrFail($facilityId);
        $facility->update(['is_active' => ! $facility->is_active]);

        session()->flash('status', $facility->is_active ? 'Fasilitas diaktifkan.' : 'Fasilitas dinonaktifkan.');
    }

    public function render(): View
    {
        return view('livewire.admin.facility-list', [
            'facilities' => $this->buildQuery(),
            'categories' => FacilityCategory::values(),
        ]);
    }

    /**
     * @return LengthAwarePaginator<int, RoomFacility>
     */
    private function buildQuery(): LengthAwarePaginator
    {
        $query = RoomFacility::query()->orderBy('name');

        if ($this->search !== '') {
            $term = '%'.$this->search.'%';
            $query->where(function ($q) use ($term) {
                $q->where('code', 'like', $term)->orWhere('name', 'like', $term);
            });
        }

        if ($this->categoryFilter !== '') {
            $query->where('category', $this->categoryFilter);
        }

        if ($this->statusFilter === 'active') {
            $query->where('is_active', true);
        } elseif ($this->statusFilter === 'inactive') {
            $query->where('is_active', false);
        }

        return $query->paginate(15);
    }
}
