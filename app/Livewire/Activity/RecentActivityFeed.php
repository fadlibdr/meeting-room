<?php

declare(strict_types=1);

namespace App\Livewire\Activity;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use Livewire\Component;

class RecentActivityFeed extends Component
{
    public int $perPage = 10;

    public function render(): View
    {
        return view('livewire.activity.recent-activity-feed', [
            'logs' => $this->fetchRecentLogs(),
        ]);
    }

    /**
     * @return Collection<int, ActivityLog>
     */
    private function fetchRecentLogs(): Collection
    {
        return ActivityLog::query()
            ->with('actor')
            ->orderByDesc('created_at')
            ->limit($this->perPage)
            ->get();
    }
}
