<?php

namespace App\Livewire\Admin;

use App\Models\Role;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class UserList extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'role', except: '')]
    public string $roleFilter = '';

    #[Url(as: 'status', except: 'all')]
    public string $statusFilter = 'all';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedRoleFilter(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'roleFilter', 'statusFilter']);
        $this->statusFilter = 'all';
        $this->resetPage();
    }

    public function toggleActive(int $userId): void
    {
        $authUser = auth()->user();
        if (! $authUser instanceof User || ! $authUser->hasPermission('users.update')) {
            abort(403);
        }

        $target = User::findOrFail($userId);

        // Prevent self-deactivation as a safety guard
        if ($target->id === $authUser->id) {
            session()->flash('error', __('Anda tidak dapat menonaktifkan akun sendiri.'));

            return;
        }

        $target->update([
            'is_active' => ! $target->is_active,
        ]);
    }

    public function render(): View
    {
        return view('livewire.admin.user-list', [
            'users' => $this->buildUsersQuery(),
            'roles' => Role::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    /**
     * @return LengthAwarePaginator<int, User>
     */
    private function buildUsersQuery(): LengthAwarePaginator
    {
        $query = User::query()
            ->with(['roles', 'unit'])
            ->orderBy('name');

        if ($this->search !== '') {
            $term = '%'.$this->search.'%';
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term);
            });
        }

        if ($this->roleFilter !== '') {
            $query->whereHas('roles', fn ($q) => $q->where('roles.code', $this->roleFilter));
        }

        if ($this->statusFilter === 'active') {
            $query->where('is_active', true);
        } elseif ($this->statusFilter === 'inactive') {
            $query->where('is_active', false);
        }

        return $query->paginate(15);
    }
}
