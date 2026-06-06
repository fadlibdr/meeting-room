@php
    $unit = \App\Models\Unit::findOrFail($unitId);
@endphp

<x-app-layout :title="__('Ubah Unit')" :subtitle="$unit->name">
    <div class="mb-6">
        <a href="{{ route('admin.units.index') }}" wire:navigate
           class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-500 hover:text-slate-800">
            <x-icon name="chevronLeft" :size="16" /> {{ __('Kembali ke Unit') }}
        </a>
    </div>

    <livewire:admin.unit-form :unit="$unit" />
</x-app-layout>
