@php
    $user = \App\Models\User::with('roles')->findOrFail($userId);
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-4">
            <a href="{{ route('admin.users.index') }}" wire:navigate
               class="text-gray-500 hover:text-gray-700">
                ← Kembali
            </a>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Edit Pengguna') }}: {{ $user->name }}
            </h2>
        </div>
    </x-slot>

    @if(session('status'))
        <div class="mb-4 max-w-3xl mx-auto px-4 py-3 bg-green-50 border border-green-200 text-green-800 rounded-md">
            {{ session('status') }}
        </div>
    @endif

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <livewire:admin.user-form :user="$user" />
        </div>
    </div>
</x-app-layout>
