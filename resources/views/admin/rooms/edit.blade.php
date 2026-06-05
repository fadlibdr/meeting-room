@php
    $room = \App\Models\Room::findOrFail($roomId);
@endphp

<x-app-layout title="Ubah Ruangan" :subtitle="$room->name">
    <div class="mb-6">
        <a href="{{ route('admin.rooms.index') }}" wire:navigate
           class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-500 hover:text-slate-800">
            <x-icon name="chevronLeft" :size="16" /> Kembali ke Kelola Ruangan
        </a>
    </div>

    <div class="space-y-6">
        <livewire:admin.room-form :room="$room" />

        <div>
            <h3 class="font-display font-bold text-slate-900 mb-3" style="font-size: 16px; letter-spacing: -0.01em;">
                Fasilitas Ruang
            </h3>
            <livewire:admin.room-facility-manager :room="$room" />
        </div>

        <div>
            <h3 class="font-display font-bold text-slate-900 mb-3" style="font-size: 16px; letter-spacing: -0.01em;">
                Jam Operasional
            </h3>
            <livewire:admin.room-operating-hours-manager :room="$room" />
        </div>
    </div>
</x-app-layout>
