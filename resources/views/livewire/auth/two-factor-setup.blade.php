<?php

use App\Services\ActivityLogger;
use App\Services\SettingsService;
use App\Services\TwoFactorService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $code = '';

    public bool $confirmed = false;

    /** @var list<string> */
    public array $recoveryCodes = [];

    public function mount(): void
    {
        // Feature off, or already enrolled → nothing to do here.
        if (! (bool) app(SettingsService::class)->get('security.mfa_enabled', true)) {
            $this->redirectRoute('profile', navigate: true);

            return;
        }

        if (auth()->user()->hasTwoFactorEnabled()) {
            $this->redirectRoute('profile', navigate: true);

            return;
        }

        if (! session()->has('2fa_setup.secret')) {
            session()->put('2fa_setup.secret', app(TwoFactorService::class)->generateSecret());
        }
    }

    #[Computed]
    public function secret(): string
    {
        return (string) session('2fa_setup.secret', '');
    }

    #[Computed]
    public function qrSvg(): string
    {
        $svc = app(TwoFactorService::class);

        return $svc->qrSvg($svc->provisioningUri(auth()->user(), $this->secret));
    }

    public function confirm(): void
    {
        $secret = (string) session('2fa_setup.secret', '');

        if (! app(TwoFactorService::class)->verify($secret, $this->code)) {
            $this->addError('code', __('Kode tidak valid. Coba lagi.'));

            return;
        }

        $user = auth()->user();
        $this->recoveryCodes = app(TwoFactorService::class)->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => $this->recoveryCodes,
            'two_factor_confirmed_at' => now(),
        ])->save();

        session()->forget('2fa_setup.secret');
        app(ActivityLogger::class)->security('mfa.enabled', $user, [
            'description' => 'Autentikasi dua faktor diaktifkan.',
        ]);

        $this->confirmed = true;
    }

    public function finish(): void
    {
        $this->redirectRoute('dashboard', navigate: true);
    }
}; ?>

<div class="w-full" style="max-width: 420px;">
    @if ($confirmed)
        <h1 class="h-display mb-1 text-lg font-bold text-slate-900">{{ __('2FA Aktif') }}</h1>
        <p class="mb-4 text-sm text-slate-500">{{ __('Simpan kode pemulihan berikut di tempat aman. Setiap kode hanya dapat dipakai sekali bila Anda kehilangan akses ke aplikasi authenticator.') }}</p>
        <div class="mb-5 grid grid-cols-2 gap-2 rounded-lg bg-slate-50 p-4 font-mono text-sm">
            @foreach ($recoveryCodes as $rc)
                <span>{{ $rc }}</span>
            @endforeach
        </div>
        <x-bpjs.button wire:click="finish" size="lg" block>{{ __('Saya sudah menyimpan kode') }}</x-bpjs.button>
    @else
        <h1 class="h-display mb-1 text-lg font-bold text-slate-900">{{ __('Aktifkan Autentikasi Dua Faktor') }}</h1>
        <p class="mb-4 text-sm text-slate-500">{{ __('Pindai QR ini dengan aplikasi authenticator (Google Authenticator, Microsoft Authenticator, dll.), lalu masukkan kode 6 digit untuk mengonfirmasi.') }}</p>

        <div class="mb-3 flex justify-center rounded-lg border border-slate-200 bg-white p-4">
            {!! $this->qrSvg !!}
        </div>
        <p class="mb-4 break-all text-center text-xs text-slate-400">{{ __('Atau masukkan kunci ini secara manual:') }} <span class="font-mono text-slate-600">{{ $this->secret }}</span></p>

        <form wire:submit="confirm" class="space-y-4">
            <x-bpjs.field :label="__('Kode Verifikasi')" req for="code" :error="$errors->first('code')">
                <input wire:model="code" id="code" type="text" inputmode="numeric" autocomplete="one-time-code"
                       class="input @error('code') input--err @enderror" placeholder="123456" />
            </x-bpjs.field>
            <x-bpjs.button type="submit" size="lg" block>{{ __('Aktifkan 2FA') }}</x-bpjs.button>
        </form>
    @endif
</div>
