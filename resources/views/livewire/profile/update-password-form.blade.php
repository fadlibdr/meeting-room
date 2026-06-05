<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Component;

new class extends Component
{
    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';

    /**
     * Update the password for the currently authenticated user.
     */
    public function updatePassword(): void
    {
        try {
            $validated = $this->validate([
                'current_password' => ['required', 'string', 'current_password'],
                'password' => ['required', 'string', Password::defaults(), 'confirmed'],
            ]);
        } catch (ValidationException $e) {
            $this->reset('current_password', 'password', 'password_confirmation');

            throw $e;
        }

        Auth::user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        $this->reset('current_password', 'password', 'password_confirmation');

        $this->dispatch('password-updated');
    }
}; ?>

<x-bpjs.card rise>
    <header class="mb-5">
        <h2 class="h-display" style="font-size: 16px; font-weight: 700; color: var(--slate-900);">
            {{ __('Ubah Kata Sandi') }}
        </h2>

        <p class="mt-1" style="font-size: 13px; color: var(--slate-500);">
            {{ __('Pastikan akun Anda menggunakan kata sandi yang panjang dan acak agar tetap aman.') }}
        </p>
    </header>

    <form wire:submit="updatePassword" class="space-y-5">
        <x-bpjs.field :label="__('Kata Sandi Saat Ini')" for="update_password_current_password" :error="$errors->first('current_password')">
            <input wire:model="current_password" id="update_password_current_password" name="current_password" type="password"
                   class="input @error('current_password') input--err @enderror"
                   autocomplete="current-password" />
        </x-bpjs.field>

        <x-bpjs.field :label="__('Kata Sandi Baru')" for="update_password_password" :error="$errors->first('password')">
            <input wire:model="password" id="update_password_password" name="password" type="password"
                   class="input @error('password') input--err @enderror"
                   autocomplete="new-password" />
        </x-bpjs.field>

        <x-bpjs.field :label="__('Konfirmasi Kata Sandi')" for="update_password_password_confirmation" :error="$errors->first('password_confirmation')">
            <input wire:model="password_confirmation" id="update_password_password_confirmation" name="password_confirmation" type="password"
                   class="input @error('password_confirmation') input--err @enderror"
                   autocomplete="new-password" />
        </x-bpjs.field>

        <div class="flex items-center gap-4 pt-1">
            <x-bpjs.button type="submit" variant="primary" icon="check">{{ __('Simpan') }}</x-bpjs.button>

            <x-action-message class="me-3" on="password-updated" style="font-size: 13px; font-weight: 600; color: var(--bpjs-green-700);">
                {{ __('Tersimpan.') }}
            </x-action-message>
        </div>
    </form>
</x-bpjs.card>
