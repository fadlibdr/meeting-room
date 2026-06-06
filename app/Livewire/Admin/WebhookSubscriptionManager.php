<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Enums\WebhookEvent;
use App\Models\User;
use App\Models\WebhookSubscription;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Stage 3 C2 (UI) — manage outbound webhook subscriptions.
 *
 * Gated by app-settings.update. The signing secret is generated on create and
 * shown exactly once (the receiver needs it to verify the HMAC signature).
 */
class WebhookSubscriptionManager extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $url = '';

    /** @var list<string> */
    public array $events = [];

    public bool $isActive = true;

    public ?string $plainSecret = null;

    public string $feedback = '';

    private function guard(): void
    {
        $user = auth()->user();
        if (! $user instanceof User || ! $user->hasPermission('app-settings.update')) {
            abort(403);
        }
    }

    public function newSubscription(): void
    {
        $this->guard();
        $this->reset(['editingId', 'name', 'url', 'events', 'plainSecret']);
        $this->isActive = true;
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->guard();
        $sub = WebhookSubscription::findOrFail($id);
        $this->editingId = $sub->id;
        $this->name = $sub->name;
        $this->url = $sub->url;
        $this->events = $sub->events;
        $this->isActive = $sub->is_active;
        $this->plainSecret = null;
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->guard();

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'url' => ['required', 'url', 'max:255'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => [Rule::in(WebhookEvent::values())],
            'isActive' => ['boolean'],
        ]);

        if ($this->editingId !== null) {
            WebhookSubscription::findOrFail($this->editingId)->update([
                'name' => $validated['name'],
                'url' => $validated['url'],
                'events' => $validated['events'],
                'is_active' => $validated['isActive'],
            ]);
            $this->feedback = __('Webhook diperbarui.');
        } else {
            $secret = Str::random(40);
            WebhookSubscription::create([
                'name' => $validated['name'],
                'url' => $validated['url'],
                'secret' => $secret,
                'events' => $validated['events'],
                'is_active' => $validated['isActive'],
                'created_by_user_id' => auth()->id(),
            ]);
            $this->plainSecret = $secret;
            $this->feedback = __('Webhook dibuat.');
        }

        $this->showForm = false;
    }

    public function toggle(int $id): void
    {
        $this->guard();
        $sub = WebhookSubscription::findOrFail($id);
        $sub->forceFill(['is_active' => ! $sub->is_active])->save();
    }

    public function delete(int $id): void
    {
        $this->guard();
        WebhookSubscription::findOrFail($id)->delete();
        $this->feedback = __('Webhook dihapus.');
    }

    public function cancel(): void
    {
        $this->showForm = false;
        $this->resetValidation();
    }

    public function dismissSecret(): void
    {
        $this->plainSecret = null;
    }

    public function render(): View
    {
        return view('livewire.admin.webhook-subscription-manager', [
            'subscriptions' => WebhookSubscription::withCount('deliveries')->orderBy('name')->get(),
            'webhookEvents' => WebhookEvent::cases(),
        ])->layout('layouts.app', [
            'title' => __('Webhook'),
            'subtitle' => __('Kirim notifikasi peristiwa reservasi ke sistem lain'),
        ]);
    }
}
