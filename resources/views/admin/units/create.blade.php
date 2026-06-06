<x-app-layout :title="__('Tambah Unit')" :subtitle="__('Buat unit kerja baru')">
    <div class="mb-6">
        <a href="{{ route('admin.units.index') }}" wire:navigate
           class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-500 hover:text-slate-800">
            <x-icon name="chevronLeft" :size="16" /> {{ __('Kembali ke Unit') }}
        </a>
    </div>

    <livewire:admin.unit-form />
</x-app-layout>
