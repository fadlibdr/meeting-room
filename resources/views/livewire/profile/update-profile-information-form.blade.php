<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;

new class extends Component
{
    public string $name = '';
    public string $email = '';
    public bool $emailNotifications = true;
    public string $locale = '';

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->email = Auth::user()->email;
        $this->emailNotifications = (bool) Auth::user()->email_notifications;
        $this->locale = Auth::user()->locale ?? (string) config('app.locale', 'id');
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($user->id)],
            'locale' => ['required', 'string', Rule::in(array_keys(config('app.available_locales', [])))],
        ]);

        $user->fill($validated);
        $user->email_notifications = $this->emailNotifications;

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        // Apply the language choice to the current session immediately.
        Session::put('locale', $user->locale);

        $this->dispatch('profile-updated', name: $user->name);
    }

    /**
     * Send an email verification notification to the current user.
     */
    public function sendVerification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false));

            return;
        }

        $user->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }
}; ?>

<x-bpjs.card rise>
    <header class="mb-5">
        <h2 class="h-display" style="font-size: 16px; font-weight: 700; color: var(--slate-900);">
            {{ __('Informasi Profil') }}
        </h2>

        <p class="mt-1" style="font-size: 13px; color: var(--slate-500);">
            {{ __('Perbarui informasi profil dan alamat email akun Anda.') }}
        </p>
    </header>

    <form wire:submit="updateProfileInformation" class="space-y-5">
        <x-bpjs.field :label="__('Nama')" req for="name" :error="$errors->first('name')">
            <input wire:model="name" id="name" name="name" type="text"
                   class="input @error('name') input--err @enderror"
                   required autofocus autocomplete="name" />
        </x-bpjs.field>

        <x-bpjs.field :label="__('Email')" req for="email" :error="$errors->first('email')">
            <input wire:model="email" id="email" name="email" type="email"
                   class="input font-mono @error('email') input--err @enderror"
                   required autocomplete="username" />
        </x-bpjs.field>

        <x-bpjs.field :label="__('common.language')" for="locale" :error="$errors->first('locale')">
            <select wire:model="locale" id="locale" name="locale" class="select">
                @foreach(config('app.available_locales', []) as $code => $label)
                    <option value="{{ $code }}">{{ $label }}</option>
                @endforeach
            </select>
        </x-bpjs.field>

        <div class="pt-1">
            <label class="flex items-start gap-2.5 cursor-pointer select-none">
                <input type="checkbox" wire:model="emailNotifications" id="emailNotifications"
                       class="mt-0.5 rounded border-slate-300 text-bpjs-blue-600 shadow-sm focus:ring-bpjs-blue-500" />
                <span>
                    <span class="text-sm font-semibold text-slate-700">{{ __('Terima notifikasi email') }}</span>
                    <span class="block field__hint">{{ __('Jika dimatikan, Anda tidak akan menerima email notifikasi reservasi (notifikasi dalam aplikasi tetap aktif).') }}</span>
                </span>
            </label>
        </div>

        @if (auth()->user() instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! auth()->user()->hasVerifiedEmail())
            <div>
                <p style="font-size: 13px; color: var(--slate-700);">
                    {{ __('Alamat email Anda belum terverifikasi.') }}

                    <button wire:click.prevent="sendVerification" class="underline focus-bpjs" style="font-size: 13px; color: var(--bpjs-blue-600);">
                        {{ __('Klik di sini untuk mengirim ulang email verifikasi.') }}
                    </button>
                </p>

                @if (session('status') === 'verification-link-sent')
                    <p class="mt-2" style="font-size: 13px; font-weight: 600; color: var(--bpjs-green-700);">
                        {{ __('Tautan verifikasi baru telah dikirim ke alamat email Anda.') }}
                    </p>
                @endif
            </div>
        @endif

        <div class="flex items-center gap-4 pt-1">
            <x-bpjs.button type="submit" variant="primary" icon="check">{{ __('Simpan') }}</x-bpjs.button>

            <x-action-message class="me-3" on="profile-updated" style="font-size: 13px; font-weight: 600; color: var(--bpjs-green-700);">
                {{ __('Tersimpan.') }}
            </x-action-message>
        </div>
    </form>
</x-bpjs.card>
