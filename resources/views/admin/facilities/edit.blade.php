@php
    $facility = \App\Models\RoomFacility::findOrFail($facilityId);
@endphp

<x-app-layout title="Ubah Fasilitas" :subtitle="$facility->name">
    <div class="mb-6">
        <a href="{{ route('admin.facilities.index') }}" wire:navigate
           class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-500 hover:text-slate-800">
            <x-icon name="chevronLeft" :size="16" /> Kembali ke Fasilitas
        </a>
    </div>

    <livewire:admin.facility-form :facility="$facility" />
</x-app-layout>
