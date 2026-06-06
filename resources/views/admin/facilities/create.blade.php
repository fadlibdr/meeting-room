<x-app-layout :title="__('Tambah Fasilitas')" :subtitle="__('Daftarkan fasilitas baru ke dalam katalog')">
    <div class="mb-6">
        <a href="{{ route('admin.facilities.index') }}" wire:navigate
           class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-500 hover:text-slate-800">
            <x-icon name="chevronLeft" :size="16" /> {{ __('Kembali ke Fasilitas') }}
        </a>
    </div>

    <livewire:admin.facility-form />
</x-app-layout>
