<?php

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    #[Locked]
    public string $token = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    /**
     * Mount the component.
     */
    public function mount(string $token): void
    {
        $this->token = $token;

        $this->email = request()->string('email');
    }

    /**
     * Reset the password for the given user.
     */
    public function resetPassword(): void
    {
        $this->validate([
            'token' => ['required'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ]);

        // Here we will attempt to reset the user's password. If it is successful we
        // will update the password on an actual user model and persist it to the
        // database. Otherwise we will parse the error and return the response.
        $status = Password::reset(
            $this->only('email', 'password', 'password_confirmation', 'token'),
            function ($user) {
                $user->forceFill([
                    'password' => Hash::make($this->password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        // If the password was successfully reset, we will redirect the user back to
        // the application's home authenticated view. If there is an error we can
        // redirect them back to where they came from with their error message.
        if ($status != Password::PASSWORD_RESET) {
            $this->addError('email', __($status));

            return;
        }

        Session::flash('status', __($status));

        $this->redirectRoute('login', navigate: true);
    }
}; ?>

<div>
    <h2 class="font-display font-bold text-slate-900" style="font-size: 24px; letter-spacing: -0.01em;">
        {{ __('Atur Ulang Kata Sandi') }}
    </h2>
    <p class="mt-1.5 text-slate-500" style="font-size: 13.5px;">
        {{ __('Buat kata sandi baru untuk akun Anda.') }}
    </p>

    <form wire:submit="resetPassword" class="mt-7 space-y-5">
        <!-- Email Address -->
        <x-bpjs.field :label="__('Email')" for="email" req :error="$errors->first('email')">
            <input wire:model="email" id="email" name="email" type="email" required autofocus
                   autocomplete="username" placeholder="nama@bpjs-kesehatan.go.id"
                   class="input @error('email') input--err @enderror">
        </x-bpjs.field>

        <!-- Password -->
        <x-bpjs.field :label="__('Kata Sandi Baru')" for="password" req :error="$errors->first('password')">
            <input wire:model="password" id="password" name="password" type="password" required
                   autocomplete="new-password" placeholder="••••••••"
                   class="input @error('password') input--err @enderror">
        </x-bpjs.field>

        <!-- Confirm Password -->
        <x-bpjs.field :label="__('Konfirmasi Kata Sandi')" for="password_confirmation" req :error="$errors->first('password_confirmation')">
            <input wire:model="password_confirmation" id="password_confirmation" name="password_confirmation" type="password" required
                   autocomplete="new-password" placeholder="••••••••"
                   class="input @error('password_confirmation') input--err @enderror">
        </x-bpjs.field>

        <x-bpjs.button type="submit" size="lg" block>
            {{ __('Simpan Kata Sandi') }}
        </x-bpjs.button>
    </form>
</div>
