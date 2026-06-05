<?php

use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component
{
    public string $password = '';

    /**
     * Delete the currently authenticated user.
     */
    public function deleteUser(Logout $logout): void
    {
        $this->validate([
            'password' => ['required', 'string', 'current_password'],
        ]);

        tap(Auth::user(), $logout(...))->delete();

        $this->redirect('/', navigate: true);
    }
}; ?>

<x-bpjs.card rise>
    <header class="mb-5">
        <h2 class="h-display" style="font-size: 16px; font-weight: 700; color: var(--red-700);">
            {{ __('Hapus Akun') }}
        </h2>

        <p class="mt-1" style="font-size: 13px; color: var(--slate-500);">
            {{ __('Setelah akun Anda dihapus, seluruh sumber daya dan datanya akan dihapus secara permanen. Sebelum menghapus akun, harap unduh data atau informasi yang ingin Anda simpan.') }}
        </p>
    </header>

    <x-bpjs.button
        variant="solid-danger"
        icon="x"
        x-data=""
        x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')"
    >{{ __('Hapus Akun') }}</x-bpjs.button>

    <x-modal name="confirm-user-deletion" :show="$errors->isNotEmpty()" focusable>
        <form wire:submit="deleteUser" class="p-6">

            <h2 class="h-display" style="font-size: 17px; font-weight: 700; color: var(--slate-900);">
                {{ __('Apakah Anda yakin ingin menghapus akun Anda?') }}
            </h2>

            <p class="mt-1" style="font-size: 13px; color: var(--slate-500);">
                {{ __('Setelah akun Anda dihapus, seluruh sumber daya dan datanya akan dihapus secara permanen. Masukkan kata sandi Anda untuk mengonfirmasi bahwa Anda ingin menghapus akun secara permanen.') }}
            </p>

            <div class="mt-6">
                <x-bpjs.field for="password" :error="$errors->first('password')">
                    <span class="sr-only">{{ __('Kata Sandi') }}</span>
                    <input
                        wire:model="password"
                        id="password"
                        name="password"
                        type="password"
                        class="input @error('password') input--err @enderror"
                        placeholder="{{ __('Kata Sandi') }}"
                    />
                </x-bpjs.field>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <x-bpjs.button variant="ghost" x-on:click="$dispatch('close')">
                    {{ __('Batal') }}
                </x-bpjs.button>

                <x-bpjs.button type="submit" variant="solid-danger" icon="x">
                    {{ __('Hapus Akun') }}
                </x-bpjs.button>
            </div>
        </form>
    </x-modal>
</x-bpjs.card>
