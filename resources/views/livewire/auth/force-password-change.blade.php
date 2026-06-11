<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $password = '';

    public string $password_confirmation = '';

    public function update(): void
    {
        $validated = $this->validate([
            'password' => ['required', 'string', Password::defaults(), 'confirmed'],
        ]);

        $user = Auth::user();
        $user->forceFill([
            'password' => Hash::make($validated['password']),
            'must_change_password' => false,
        ])->save();

        $this->redirectIntended(route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div class="w-full" style="max-width: 360px;">
    <h1 class="h-display mb-1 text-lg font-bold text-slate-900">{{ __('Ganti Kata Sandi') }}</h1>
    <p class="mb-5 text-sm text-slate-500">{{ __('Demi keamanan, tetapkan kata sandi baru untuk melanjutkan.') }}</p>

    <form wire:submit="update" class="space-y-4">
        <x-bpjs.field :label="__('Kata Sandi Baru')" req for="password" :error="$errors->first('password')">
            <input wire:model="password" id="password" type="password" autocomplete="new-password"
                   class="input @error('password') input--err @enderror" />
        </x-bpjs.field>
        <x-bpjs.field :label="__('Konfirmasi Kata Sandi')" req for="password_confirmation">
            <input wire:model="password_confirmation" id="password_confirmation" type="password" autocomplete="new-password"
                   class="input" />
        </x-bpjs.field>
        <x-bpjs.button type="submit" size="lg" block>{{ __('Simpan & Lanjutkan') }}</x-bpjs.button>
    </form>
</div>
