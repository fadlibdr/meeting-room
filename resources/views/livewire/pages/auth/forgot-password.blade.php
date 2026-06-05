<?php

use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $email = '';

    /**
     * Send a password reset link to the provided email address.
     */
    public function sendPasswordResetLink(): void
    {
        $this->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        // We will send the password reset link to this user. Once we have attempted
        // to send the link, we will examine the response then see the message we
        // need to show to the user. Finally, we'll send out a proper response.
        $status = Password::sendResetLink(
            $this->only('email')
        );

        if ($status != Password::RESET_LINK_SENT) {
            $this->addError('email', __($status));

            return;
        }

        $this->reset('email');

        session()->flash('status', __($status));
    }
}; ?>

<div>
    <h2 class="font-display font-bold text-slate-900" style="font-size: 24px; letter-spacing: -0.01em;">
        {{ __('Lupa Kata Sandi') }}
    </h2>
    <p class="mt-1.5 text-slate-500" style="font-size: 13.5px;">
        {{ __('Masukkan email Anda dan kami akan mengirimkan tautan untuk mengatur ulang kata sandi.') }}
    </p>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4 mt-4" :status="session('status')" />

    <form wire:submit="sendPasswordResetLink" class="mt-7 space-y-5">
        <!-- Email Address -->
        <x-bpjs.field :label="__('Email')" for="email" req :error="$errors->first('email')">
            <input wire:model="email" id="email" name="email" type="email" required autofocus
                   autocomplete="username" placeholder="nama@bpjs-kesehatan.go.id"
                   class="input @error('email') input--err @enderror">
        </x-bpjs.field>

        <x-bpjs.button type="submit" size="lg" block>
            {{ __('Kirim Tautan Reset Kata Sandi') }}
        </x-bpjs.button>
    </form>
</div>
