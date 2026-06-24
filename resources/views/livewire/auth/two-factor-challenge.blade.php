<?php

use App\Services\ActivityLogger;
use App\Services\TwoFactorService;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $code = '';

    public function mount(): void
    {
        // No pending challenge → nothing to verify.
        if (! Session::get('2fa.pending')) {
            $this->redirectRoute('dashboard', navigate: true);
        }
    }

    public function verify(): void
    {
        $user = auth()->user();
        $code = trim($this->code);

        $passed = app(TwoFactorService::class)->verify((string) $user->two_factor_secret, $code)
            || $user->useRecoveryCode($code);

        if (! $passed) {
            app(ActivityLogger::class)->security('mfa.challenge.failed', $user, [
                'description' => 'Verifikasi 2FA gagal.',
            ]);
            $this->addError('code', __('Kode tidak valid.'));

            return;
        }

        Session::forget('2fa.pending');
        Session::regenerate();

        app(ActivityLogger::class)->security('mfa.challenge.passed', $user, [
            'description' => 'Verifikasi 2FA berhasil.',
        ]);

        $this->redirectIntended(route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div class="w-full" style="max-width: 360px;">
    <h1 class="h-display mb-1 text-lg font-bold text-slate-900">{{ __('Verifikasi Dua Faktor') }}</h1>
    <p class="mb-5 text-sm text-slate-500">{{ __('Masukkan kode 6 digit dari aplikasi authenticator Anda, atau salah satu kode pemulihan.') }}</p>

    <form wire:submit="verify" class="space-y-4">
        <x-bpjs.field :label="__('Kode')" req for="code" :error="$errors->first('code')">
            <input wire:model="code" id="code" type="text" inputmode="numeric" autocomplete="one-time-code" autofocus
                   class="input @error('code') input--err @enderror" placeholder="123456" />
        </x-bpjs.field>
        <x-bpjs.button type="submit" size="lg" block>{{ __('Verifikasi') }}</x-bpjs.button>
    </form>
</div>
