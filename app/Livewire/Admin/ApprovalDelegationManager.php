<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Models\ApprovalDelegation;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Stage 3 B (UI) — manage approval delegations.
 *
 * Gated by app-settings.update. Dates are entered per-day in the display
 * timezone and stored in UTC (start-of-day / end-of-day). "End" closes an
 * active delegation by stamping ends_at = now.
 */
class ApprovalDelegationManager extends Component
{
    public const DISPLAY_TIMEZONE_FALLBACK = 'Asia/Jakarta';

    public bool $showForm = false;

    public ?int $fromUserId = null;

    public ?int $toUserId = null;

    public string $startsAt = '';

    public string $endsAt = '';

    public string $reason = '';

    public string $feedback = '';

    private function guard(): void
    {
        $user = auth()->user();
        if (! $user instanceof User || ! $user->hasPermission('app-settings.update')) {
            abort(403);
        }
    }

    public function newDelegation(): void
    {
        $this->guard();
        $this->reset(['fromUserId', 'toUserId', 'startsAt', 'endsAt', 'reason']);
        $this->startsAt = CarbonImmutable::now($this->tz())->format('Y-m-d');
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->guard();

        $validated = $this->validate([
            'fromUserId' => ['required', 'integer', 'exists:users,id', 'different:toUserId'],
            'toUserId' => ['required', 'integer', 'exists:users,id'],
            'startsAt' => ['required', 'date'],
            'endsAt' => ['nullable', 'date', 'after_or_equal:startsAt'],
            'reason' => ['nullable', 'string', 'max:255'],
        ], [
            'fromUserId.different' => __('Pemberi dan penerima delegasi harus berbeda.'),
        ]);

        $tz = $this->tz();
        ApprovalDelegation::create([
            'from_user_id' => $validated['fromUserId'],
            'to_user_id' => $validated['toUserId'],
            'starts_at' => CarbonImmutable::parse($validated['startsAt'], $tz)->startOfDay()->utc(),
            'ends_at' => $validated['endsAt'] !== null && $validated['endsAt'] !== ''
                ? CarbonImmutable::parse($validated['endsAt'], $tz)->endOfDay()->utc()
                : null,
            'reason' => $validated['reason'] ?: null,
        ]);

        $this->feedback = __('Delegasi dibuat.');
        $this->showForm = false;
    }

    public function endNow(int $id): void
    {
        $this->guard();
        $delegation = ApprovalDelegation::findOrFail($id);
        $delegation->forceFill(['ends_at' => now()])->save();
        $this->feedback = __('Delegasi diakhiri.');
    }

    public function delete(int $id): void
    {
        $this->guard();
        ApprovalDelegation::findOrFail($id)->delete();
        $this->feedback = __('Delegasi dihapus.');
    }

    public function cancel(): void
    {
        $this->showForm = false;
        $this->resetValidation();
    }

    private function tz(): string
    {
        $userTimezone = auth()->check() ? auth()->user()->timezone : null;

        return $userTimezone ?? config('app.display_timezone', self::DISPLAY_TIMEZONE_FALLBACK);
    }

    public function render(): View
    {
        return view('livewire.admin.approval-delegation-manager', [
            'delegations' => ApprovalDelegation::with(['fromUser:id,name', 'toUser:id,name'])
                ->orderByDesc('starts_at')->get(),
            'users' => User::where('is_active', true)->orderBy('name')->get(['id', 'name', 'email']),
            'timezone' => $this->tz(),
        ])->layout('layouts.app', [
            'title' => __('Delegasi Persetujuan'),
            'subtitle' => __('Alihkan antrean persetujuan saat approver berhalangan'),
        ]);
    }
}
