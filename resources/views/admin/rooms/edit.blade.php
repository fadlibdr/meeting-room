@php
    $room = \App\Models\Room::findOrFail($roomId);
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-4">
            <a href="{{ route('admin.rooms.index') }}" wire:navigate class="text-gray-500 hover:text-gray-700">← Kembali</a>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Edit Ruang') }}: {{ $room->name }}
            </h2>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <livewire:admin.room-form :room="$room" />
        </div>
    </div>
</x-app-layout>
