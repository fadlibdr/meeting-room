<x-app-layout :title="__('Tambah Ruangan')" :subtitle="__('Daftarkan ruang rapat baru ke dalam sistem')">
    <div class="mb-6">
        <a href="{{ route('admin.rooms.index') }}" wire:navigate
           class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-500 hover:text-slate-800">
            <x-icon name="chevronLeft" :size="16" /> {{ __('Kembali ke Kelola Ruangan') }}
        </a>
    </div>

    <livewire:admin.room-form />
</x-app-layout>
