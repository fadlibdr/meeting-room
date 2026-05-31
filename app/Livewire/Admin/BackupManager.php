<?php

namespace App\Livewire\Admin;

use Livewire\Component;

class BackupManager extends Component
{
    public bool $authorized = false;

    public function mount(): void
    {
        $this->authorized = auth()->user()?->hasPermission('app-settings.update') ?? false;
    }

    public function render()
    {
        return view('livewire.admin.backup-manager');
    }
}
