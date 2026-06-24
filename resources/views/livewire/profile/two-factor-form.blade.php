<?php

use App\Services\ActivityLogger;
use App\Services\SettingsService;
use App\Services\TwoFactorService;
use Illuminate\Support\Facades\Hash;
use Livewire\Volt\Component;

new class extends Component
{
    public string $password = '';

    /** @var list<string>|null */
    public ?array $newRecoveryCodes = null;

    public function with(): array
    {
        return [
            'mfaEnabled' => (bool) app(SettingsService::class)->get('security.mfa_enabled', true),
            'hasTwoFactor' => auth()->user()->hasTwoFactorEnabled(),
            'enforced' => auth()->user()->requiresTwoFactor() || auth()->user()->hasTwoFactorEnabled(),
        ];
    }

    private function passwordConfirmed(): bool
    {
        if (! Hash::check($this->password, auth()->user()->password)) {
            $this->addError('password', __('Kata sandi salah.'));

            return false;
        }

        return true;
    }

    public function disable(): void
    {
        if (! $this->passwordConfirmed()) {
            return;
        }

        $user = auth()->user();
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        app(ActivityLogger::class)->security('mfa.disabled', $user, [
            'description' => 'Autentikasi dua faktor dinonaktifkan.',
        ]);

        $this->reset('password', 'newRecoveryCodes');
        session()->flash('status', __('2FA dinonaktifkan.'));
    }

    public function regenerateRecoveryCodes(): void
    {
        if (! $this->passwordConfirmed()) {
            return;
        }

        $user = auth()->user();
        $this->newRecoveryCodes = app(TwoFactorService::class)->generateRecoveryCodes();
        $user->forceFill(['two_factor_recovery_codes' => $this->newRecoveryCodes])->save();

        app(ActivityLogger::class)->security('mfa.recovery_regenerated', $user, [
            'description' => 'Kode pemulihan 2FA dibuat ulang.',
        ]);

        $this->reset('password');
    }
}; ?>

<x-bpjs.card :title="__('Autentikasi Dua Faktor (2FA)')" class="mt-[18px]">
    @if (! $mfaEnabled)
        <p class="text-sm text-slate-500">{{ __('Fitur 2FA dinonaktifkan oleh administrator.') }}</p>
    @elseif (! $hasTwoFactor)
        <p class="mb-4 text-sm text-slate-500">{{ __('Tambahkan lapisan keamanan kedua menggunakan aplikasi authenticator.') }}</p>
        <x-bpjs.button :href="route('two-factor.setup')" wire:navigate icon="shield">{{ __('Aktifkan 2FA') }}</x-bpjs.button>
    @else
        <p class="mb-4 text-sm text-emerald-600">{{ __('2FA aktif untuk akun Anda.') }}</p>

        @if ($newRecoveryCodes)
            <div class="mb-4 grid grid-cols-2 gap-2 rounded-lg bg-slate-50 p-4 font-mono text-sm">
                @foreach ($newRecoveryCodes as $rc)
                    <span>{{ $rc }}</span>
                @endforeach
            </div>
        @endif

        <x-bpjs.field :label="__('Konfirmasi Kata Sandi')" for="tf_password" :error="$errors->first('password')">
            <input wire:model="password" id="tf_password" type="password" autocomplete="current-password"
                   class="input @error('password') input--err @enderror" />
        </x-bpjs.field>

        <div class="mt-3 flex gap-2">
            <x-bpjs.button wire:click="regenerateRecoveryCodes" variant="ghost">{{ __('Buat Ulang Kode Pemulihan') }}</x-bpjs.button>
            <x-bpjs.button wire:click="disable" variant="danger">{{ __('Nonaktifkan 2FA') }}</x-bpjs.button>
        </div>
    @endif
</x-bpjs.card>
