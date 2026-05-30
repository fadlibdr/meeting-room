<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

class NotificationDropdown extends Component
{
    #[Computed]
    public function unreadCount(): int
    {
        return $this->user()->unreadNotifications()->count();
    }

    /**
     * @return Collection<int, DatabaseNotification>
     */
    #[Computed]
    public function notifications(): Collection
    {
        return $this->user()->notifications()->latest()->limit(10)->get();
    }

    public function markAsRead(string $id)
    {
        $notification = $this->user()->notifications()->whereKey($id)->first();

        if ($notification === null) {
            return null;
        }

        $notification->markAsRead();
        unset($this->unreadCount, $this->notifications);

        $url = $notification->data['url'] ?? null;

        return is_string($url) ? $this->redirect($url, navigate: true) : null;
    }

    public function markAllAsRead(): void
    {
        $this->user()->unreadNotifications()->update(['read_at' => now()]);
        unset($this->unreadCount, $this->notifications);
    }

    public function render()
    {
        return view('livewire.notification-dropdown');
    }

    private function user(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
