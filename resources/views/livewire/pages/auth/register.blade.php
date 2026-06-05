<?php

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    /**
     * Handle an incoming registration request.
     */
    public function register(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ]);

        $validated['password'] = Hash::make($validated['password']);

        event(new Registered($user = User::create($validated)));

        Auth::login($user);

        $this->redirect(route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div>
    <h2 class="font-display font-bold text-slate-900" style="font-size: 24px; letter-spacing: -0.01em;">
        {{ __('Daftar Akun') }}
    </h2>
    <p class="mt-1.5 text-slate-500" style="font-size: 13.5px;">
        {{ __('Buat akun baru untuk mengakses sistem.') }}
    </p>

    <form wire:submit="register" class="mt-7 space-y-5">
        <!-- Name -->
        <x-bpjs.field :label="__('Nama')" for="name" req :error="$errors->first('name')">
            <input wire:model="name" id="name" name="name" type="text" required autofocus
                   autocomplete="name" placeholder="Nama lengkap"
                   class="input @error('name') input--err @enderror">
        </x-bpjs.field>

        <!-- Email Address -->
        <x-bpjs.field :label="__('Email')" for="email" req :error="$errors->first('email')">
            <input wire:model="email" id="email" name="email" type="email" required
                   autocomplete="username" placeholder="nama@bpjs-kesehatan.go.id"
                   class="input @error('email') input--err @enderror">
        </x-bpjs.field>

        <!-- Password -->
        <x-bpjs.field :label="__('Kata Sandi')" for="password" req :error="$errors->first('password')">
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
            {{ __('Daftar') }}
        </x-bpjs.button>

        <p class="text-center text-slate-600" style="font-size: 13px;">
            {{ __('Sudah punya akun?') }}
            <a class="text-bpjs-blue-600 hover:text-bpjs-blue-700 font-medium"
               href="{{ route('login') }}" wire:navigate>
                {{ __('Masuk') }}
            </a>
        </p>
    </form>
</div>
