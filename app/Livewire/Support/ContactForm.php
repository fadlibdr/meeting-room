<?php

declare(strict_types=1);

namespace App\Livewire\Support;

use App\Enums\SupportCategory;
use App\Models\SupportRequest;
use App\Models\User;
use App\Notifications\SupportRequestNotification;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Notification;
use Livewire\Component;

/**
 * Stage 4g.1 — authenticated "Bantuan / Hubungi" form. Persists the request
 * (tenant-scoped, stamped by BelongsToTenant) AND emails the support inbox.
 */
class ContactForm extends Component
{
    public string $category = SupportCategory::Bug->value;

    public string $subject = '';

    public string $message = '';

    public bool $sent = false;

    public ?int $ticketId = null;

    public function submit(): void
    {
        $validated = $this->validate([
            'category' => ['required', 'string', 'in:'.implode(',', SupportCategory::values())],
            'subject' => ['nullable', 'string', 'max:150'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
        ]);

        /** @var User $user */
        $user = auth()->user();

        $request = SupportRequest::create([
            'user_id' => $user->id,
            'category' => $validated['category'],
            'subject' => $validated['subject'] ?: null,
            'message' => $validated['message'],
            'status' => 'open',
        ]);

        $to = (string) config('support.to');
        if ($to !== '') {
            Notification::route('mail', $to)
                ->notify(new SupportRequestNotification($request, $user->name, $user->email));
        }

        $this->sent = true;
        $this->ticketId = $request->id;
        $this->reset(['subject', 'message']);
    }

    public function render(): View
    {
        return view('livewire.support.contact-form', [
            'categories' => SupportCategory::cases(),
            'helpCenterUrl' => config('support.help_center_url'),
        ])->layout('layouts.app', [
            'title' => __('Bantuan & Dukungan'),
            'subtitle' => __('Kirim pertanyaan atau laporan kepada tim kami'),
        ]);
    }
}
