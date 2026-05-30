<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Blokir Ruang') }}</h2>
            @hasPermission('rooms.manage-blocks')
                <a href="{{ route('admin.room-blocks.create') }}" wire:navigate class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-md">+ Blokir Baru</a>
            @endhasPermission
        </div>
    </x-slot>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if(session('status'))
                <div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-800 rounded-md text-sm">{{ session('status') }}</div>
            @endif
            <livewire:admin.room-block-list />
        </div>
    </div>
</x-app-layout>
