<x-app-layout title="Tambah Pengguna" subtitle="Buat akun pengguna baru.">
    <div class="mb-4">
        <a href="{{ route('admin.users.index') }}" wire:navigate
           class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-500 hover:text-slate-800">
            <x-icon name="chevronLeft" :size="16" /> Kembali ke daftar
        </a>
    </div>
    <div style="max-width: 760px;">
        <livewire:admin.user-form />
    </div>
</x-app-layout>
