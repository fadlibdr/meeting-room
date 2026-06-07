<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\CalendarConnection;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Stage 3 F.2a — shows the signed-in user their .ics subscription URL and lets
 * them rotate the token (which invalidates the old URL).
 */
class CalendarSubscription extends Component
{
    public string $feedUrl = '';

    public bool $rotated = false;

    public function mount(): void
    {
        $this->feedUrl = $this->buildUrl();
    }

    public function regenerate(): void
    {
        $this->user()->regenerateCalendarFeedToken();
        $this->feedUrl = $this->buildUrl();
        $this->rotated = true;
    }

    private function user(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    private function buildUrl(): string
    {
        return route('calendar.feed', ['token' => $this->user()->ensureCalendarFeedToken()]);
    }

    /**
     * Enabled two-way providers + whether this user has connected each.
     *
     * @return list<array{key: string, label: string, connected: bool}>
     */
    private function twoWayProviders(): array
    {
        if (! config('calendar.sync.enabled')) {
            return [];
        }

        $connected = CalendarConnection::query()
            ->where('user_id', $this->user()->id)
            ->pluck('provider')
            ->all();

        $candidates = [
            'microsoft' => __('Microsoft Outlook'),
            'google' => __('Google Calendar'),
        ];

        $providers = [];
        foreach ($candidates as $key => $label) {
            if ((bool) config("calendar.{$key}.enabled")) {
                $providers[] = ['key' => $key, 'label' => $label, 'connected' => in_array($key, $connected, true)];
            }
        }

        return $providers;
    }

    public function render(): View
    {
        return view('livewire.calendar-subscription', [
            'twoWayProviders' => $this->twoWayProviders(),
        ])->layout('layouts.app', [
            'title' => __('Langganan Kalender'),
            'subtitle' => __('Tambahkan reservasi Anda ke Outlook, Google, atau Apple Calendar'),
        ]);
    }
}
