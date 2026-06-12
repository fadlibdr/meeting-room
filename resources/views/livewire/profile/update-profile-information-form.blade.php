<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public string $name = '';
    public string $email = '';
    public bool $emailNotifications = true;
    public string $locale = '';
    public string $telegramChatId = '';

    /** A staged email change awaiting confirmation, if any. */
    public ?string $pendingEmail = null;

    /** Newly selected avatar upload (temporary), if any. */
    public $avatar = null;

    /** Existing stored avatar path, for preview. */
    public ?string $existingAvatarPath = null;

    /** When true, the existing avatar is removed on save. */
    public bool $removeAvatar = false;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->email = Auth::user()->email;
        $this->emailNotifications = (bool) Auth::user()->email_notifications;
        $this->telegramChatId = (string) (Auth::user()->telegram_chat_id ?? '');
        $this->locale = Auth::user()->locale ?? (string) config('app.locale', 'id');
        $this->existingAvatarPath = Auth::user()->avatar_path;
        $this->pendingEmail = Auth::user()->pending_email;
    }

    /** Discard the just-selected (not yet saved) upload. */
    public function clearAvatar(): void
    {
        $this->avatar = null;
    }

    /** Telegram deep-link (t.me/<bot>?start=<token>) generated on demand. */
    public ?string $telegramDeepLink = null;

    /**
     * Generate a one-time link and the t.me deep link so the user can connect
     * their Telegram automatically (the /start webhook captures their chat id).
     */
    public function connectTelegram(): void
    {
        $botUsername = (string) config('services.telegram.bot_username', '');
        if ($botUsername === '') {
            return;
        }

        $token = Auth::user()->ensureTelegramLinkToken();
        $this->telegramDeepLink = 'https://t.me/'.ltrim($botUsername, '@').'?start='.$token;
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
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'telegramChatId' => ['nullable', 'string', 'max:64', 'regex:/^-?\d+$/'],
        ]);

        // Resolve avatar path + which old file (if any) to delete.
        $oldPath = $this->existingAvatarPath;
        $avatarPath = $oldPath;
        $pathToDelete = null;

        if ($this->avatar !== null) {
            $avatarPath = $this->avatar->store('avatars', 'public');
            $pathToDelete = $oldPath; // replacing → drop the previous file
        } elseif ($this->removeAvatar) {
            $avatarPath = null;
            $pathToDelete = $oldPath;
        }

        // Email changes are staged (pending_email) and only applied once the
        // user confirms via the link sent to the NEW address. Name/locale/etc.
        // apply immediately; the current email keeps working until confirmed.
        $emailChanged = strtolower(trim($validated['email'])) !== strtolower((string) $user->email);

        $user->fill(['name' => $validated['name'], 'locale' => $validated['locale']]);
        $user->email_notifications = $this->emailNotifications;
        $user->telegram_chat_id = ($validated['telegramChatId'] ?? '') !== '' ? $validated['telegramChatId'] : null;
        $user->avatar_path = $avatarPath;

        if ($emailChanged) {
            // Store only the SHA-256 of the confirmation token; the plaintext
            // lives solely in the emailed link, so a DB/backup leak can't yield a
            // usable token.
            $token = \Illuminate\Support\Str::random(40);
            $user->pending_email = strtolower(trim($validated['email']));
            $user->pending_email_token = hash('sha256', $token);
        }

        $user->save();

        if ($emailChanged) {
            \Illuminate\Support\Facades\Notification::route('mail', $user->pending_email)
                ->notify(new \App\Notifications\EmailChangeVerificationNotification($token, $user->name));
            $this->pendingEmail = $user->pending_email;
        }

        if ($pathToDelete !== null && $pathToDelete !== $avatarPath) {
            Storage::disk('public')->delete($pathToDelete);
        }

        // Reset the consumed temp upload so a re-render never previews a moved file.
        $this->avatar = null;
        $this->removeAvatar = false;
        $this->existingAvatarPath = $avatarPath;

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
        <x-bpjs.field :label="__('Foto Profil')" for="avatar"
                      :hint="__('Opsional. JPG, PNG, atau WEBP, maksimal 4 MB.')"
                      :error="$errors->first('avatar')">
            <div class="flex items-center gap-4">
                @if($avatar && $avatar->isPreviewable())
                    <img src="{{ $avatar->temporaryUrl() }}" alt="{{ __('Pratinjau') }}"
                         class="h-16 w-16 rounded-full border border-slate-200 object-cover" />
                @elseif($existingAvatarPath && ! $removeAvatar)
                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($existingAvatarPath) }}"
                         alt="{{ $name }}" class="h-16 w-16 rounded-full border border-slate-200 object-cover" />
                @else
                    <div class="flex h-16 w-16 items-center justify-center rounded-full bg-bpjs-blue-600 text-lg font-bold text-white">
                        {{ \Illuminate\Support\Str::of($name)->explode(' ')->filter()->take(2)->map(fn ($p) => \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($p, 0, 1)))->implode('') }}
                    </div>
                @endif
                <div class="space-y-2">
                    <input type="file" id="avatar" wire:model="avatar"
                           accept="image/jpeg,image/png,image/webp"
                           class="input @error('avatar') input--err @enderror" />
                    <div class="flex items-center gap-3">
                        <div wire:loading wire:target="avatar" class="text-sm text-slate-500">{{ __('Mengunggah…') }}</div>
                        @if($avatar)
                            <button type="button" wire:click="clearAvatar" class="text-sm text-slate-500 underline">{{ __('Batalkan pilihan') }}</button>
                        @elseif($existingAvatarPath)
                            <label class="flex items-center gap-2 text-sm text-slate-600">
                                <input type="checkbox" wire:model.live="removeAvatar" class="rounded border-slate-300" />
                                {{ __('Hapus foto') }}
                            </label>
                        @endif
                    </div>
                </div>
            </div>
        </x-bpjs.field>

        <x-bpjs.field :label="__('Nama')" req for="name" :error="$errors->first('name')">
            <input wire:model="name" id="name" name="name" type="text"
                   class="input @error('name') input--err @enderror"
                   required autofocus autocomplete="name" />
        </x-bpjs.field>

        <x-bpjs.field :label="__('Email')" req for="email"
                      :hint="__('Mengubah email memerlukan konfirmasi melalui tautan yang dikirim ke alamat baru. Email lama tetap berlaku sampai dikonfirmasi.')"
                      :error="$errors->first('email')">
            <input wire:model="email" id="email" name="email" type="email"
                   class="input font-mono @error('email') input--err @enderror"
                   required autocomplete="username" />
            @if($pendingEmail)
                <p class="mt-1.5 rounded-md bg-amber-50 px-2.5 py-1.5 text-xs text-amber-800">
                    {{ __('Menunggu konfirmasi untuk:') }} <span class="font-mono font-semibold">{{ $pendingEmail }}</span>
                </p>
            @endif
        </x-bpjs.field>

        <x-bpjs.field :label="__('common.language')" for="locale" :error="$errors->first('locale')">
            <select wire:model="locale" id="locale" name="locale" class="select">
                @foreach(config('app.available_locales', []) as $code => $label)
                    <option value="{{ $code }}">{{ $label }}</option>
                @endforeach
            </select>
        </x-bpjs.field>

        <x-bpjs.field :label="__('Telegram Chat ID')" for="telegramChatId"
                      :hint="__('Opsional. Tempel Chat ID numerik Anda untuk menerima notifikasi via Telegram, atau gunakan tombol Hubungkan di bawah. Kosongkan untuk menonaktifkan.')"
                      :error="$errors->first('telegramChatId')">
            <input wire:model="telegramChatId" id="telegramChatId" type="text" inputmode="numeric"
                   placeholder="123456789"
                   class="input font-mono @error('telegramChatId') input--err @enderror" />

            @if(config('services.telegram.bot_username'))
                <div class="mt-2">
                    @if($telegramDeepLink)
                        <a href="{{ $telegramDeepLink }}" target="_blank" rel="noopener" class="btn btn--primary">
                            {{ __('Buka Telegram & tekan Start') }}
                        </a>
                        <p class="mt-1.5 field__hint">{{ __('Setelah menekan Start di bot, Chat ID Anda tertaut otomatis. Muat ulang halaman ini untuk melihat hasilnya.') }}</p>
                    @else
                        <button type="button" wire:click="connectTelegram" class="btn btn--ghost">
                            {{ __('Hubungkan Telegram otomatis') }}
                        </button>
                    @endif
                </div>
            @endif
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
