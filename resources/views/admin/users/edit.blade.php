@php
    $user = \App\Models\User::with('roles')->findOrFail($userId);
@endphp

<x-app-layout :title="__('Ubah Pengguna')" :subtitle="$user->name">
    <div class="mb-4">
        <a href="{{ route('admin.users.index') }}" wire:navigate
           class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-500 hover:text-slate-800">
            <x-icon name="chevronLeft" :size="16" /> {{ __('Kembali ke daftar') }}
        </a>
    </div>

    @if(session('status'))
        <div class="card card--pad bpjs-rise mb-4 flex items-center gap-2.5"
             style="max-width: 760px; border-color: var(--bpjs-green-200); background: var(--bpjs-green-50);">
            <span style="color: var(--bpjs-green-700); display: inline-flex;"><x-icon name="checkCircle" :size="18" /></span>
            <span class="text-sm font-medium" style="color: var(--bpjs-green-800);">{{ session('status') }}</span>
        </div>
    @endif

    <div style="max-width: 760px;">
        <livewire:admin.user-form :user="$user" />
    </div>
</x-app-layout>
