<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Models\ActivityLog;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Read-only audit-log viewer (Blueprint §D.6 / Sprint 5). Filters ride the
 * activity_logs indexes (module, event, created_at, actor). Gated at the route
 * by permission:activity-logs.view (super_admin + system_admin).
 */
class ActivityLogViewer extends Component
{
    use WithPagination;

    #[Url(as: 'module', except: '')]
    public string $moduleFilter = '';

    #[Url(as: 'event', except: '')]
    public string $eventFilter = '';

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'from', except: '')]
    public string $dateFrom = '';

    #[Url(as: 'to', except: '')]
    public string $dateTo = '';

    public function updatedModuleFilter(): void
    {
        $this->resetPage();
    }

    public function updatedEventFilter(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatedDateTo(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['moduleFilter', 'eventFilter', 'search', 'dateFrom', 'dateTo']);
        $this->resetPage();
    }

    public function render(): View
    {
        return view('livewire.admin.activity-log-viewer', [
            'logs' => $this->buildQuery(),
            'modules' => $this->distinctValues('module'),
            'events' => $this->distinctValues('event'),
        ]);
    }

    /**
     * @return LengthAwarePaginator<int, ActivityLog>
     */
    private function buildQuery(): LengthAwarePaginator
    {
        $query = ActivityLog::query()->with('actor')->orderByDesc('created_at');

        if ($this->moduleFilter !== '') {
            $query->where('module', $this->moduleFilter);
        }

        if ($this->eventFilter !== '') {
            $query->where('event', $this->eventFilter);
        }

        if ($this->search !== '') {
            $query->where('description', 'like', '%'.$this->search.'%');
        }

        if ($this->dateFrom !== '') {
            $from = $this->tryParse($this->dateFrom);
            if ($from !== null) {
                $query->where('created_at', '>=', $from->startOfDay());
            }
        }

        if ($this->dateTo !== '') {
            $to = $this->tryParse($this->dateTo);
            if ($to !== null) {
                $query->where('created_at', '<=', $to->endOfDay());
            }
        }

        return $query->paginate(25);
    }

    private function distinctValues(string $column): Collection
    {
        return ActivityLog::query()->select($column)->distinct()->orderBy($column)->pluck($column);
    }

    private function tryParse(string $value): ?Carbon
    {
        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
