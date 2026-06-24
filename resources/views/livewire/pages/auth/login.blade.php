<?php

use App\Livewire\Forms\LoginForm;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public LoginForm $form;

    /**
     * Handle an incoming authentication request.
     */
    public function login(): void
    {
        $this->validate();

        $this->form->authenticate();

        Session::regenerate();

        // Second factor (CC6.1 / A.8.5): if the user has TOTP enrolled, hold the
        // session "pending" — TwoFactorGate confines them to the challenge page
        // until they verify a code.
        $user = Auth::user();
        if ($user instanceof User && $user->hasTwoFactorEnabled()) {
            Session::put('2fa.pending', true);
            $this->redirect(route('two-factor.challenge'), navigate: true);

            return;
        }

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div>
    <h2 class="font-display font-bold text-slate-900" style="font-size: 24px; letter-spacing: -0.01em;">
        {{ __('Masuk ke akun Anda') }}
    </h2>
    <p class="mt-1.5 text-slate-500" style="font-size: 13.5px;">
        {{ __('Gunakan email dinas BPJS Kesehatan Anda.') }}
    </p>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4 mt-4" :status="session('status')" />

    <form wire:submit="login" class="mt-7 space-y-5">
        <!-- Email Address -->
        <x-bpjs.field :label="__('Email')" for="email" req :error="$errors->first('form.email')">
            <input wire:model="form.email" id="email" name="email" type="email" required autofocus
                   autocomplete="username" placeholder="nama@bpjs-kesehatan.go.id"
                   class="input @error('form.email') input--err @enderror">
        </x-bpjs.field>

        <!-- Password -->
        <x-bpjs.field :label="__('Kata Sandi')" for="password" req :error="$errors->first('form.password')">
            <input wire:model="form.password" id="password" name="password" type="password" required
                   autocomplete="current-password" placeholder="••••••••"
                   class="input @error('form.password') input--err @enderror">
        </x-bpjs.field>

        <div class="flex items-center justify-between">
            <label for="remember" class="inline-flex items-center cursor-pointer">
                <input wire:model="form.remember" id="remember" type="checkbox" name="remember"
                       class="rounded border-slate-300 text-bpjs-blue-600 shadow-sm focus:ring-bpjs-blue-500">
                <span class="ms-2 text-slate-600" style="font-size: 13px;">{{ __('Ingat saya') }}</span>
            </label>

            @if (Route::has('password.request'))
                <a class="text-bpjs-blue-600 hover:text-bpjs-blue-700 font-medium" style="font-size: 13px;"
                   href="{{ route('password.request') }}" wire:navigate>
                    {{ __('Lupa sandi?') }}
                </a>
            @endif
        </div>

        <x-bpjs.button type="submit" size="lg" block>
            {{ __('Masuk') }}
        </x-bpjs.button>
    </form>

    @if(config('sso.enabled'))
        <div class="mt-6 flex items-center gap-3" aria-hidden="true">
            <span class="h-px flex-1" style="background: var(--slate-200);"></span>
            <span class="text-xs text-slate-400">{{ __('atau') }}</span>
            <span class="h-px flex-1" style="background: var(--slate-200);"></span>
        </div>
        <a href="{{ route('sso.azure.redirect') }}"
           class="mt-4 flex w-full items-center justify-center gap-2.5 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
            <svg width="18" height="18" viewBox="0 0 21 21" aria-hidden="true"><rect x="1" y="1" width="9" height="9" fill="#f25022"/><rect x="11" y="1" width="9" height="9" fill="#7fba00"/><rect x="1" y="11" width="9" height="9" fill="#00a4ef"/><rect x="11" y="11" width="9" height="9" fill="#ffb900"/></svg>
            {{ __('Masuk dengan Microsoft') }}
        </a>
    @endif
</div>
