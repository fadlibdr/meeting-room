<x-app-layout :title="__('Kelola Ruangan')" :subtitle="__('Daftar ruang rapat, status, dan mode persetujuan')">
    @if(session('status'))
        <div class="mb-6 flex items-center gap-2 rounded-xl border border-bpjs-green-200 bg-bpjs-green-50 px-4 py-3 text-sm text-bpjs-green-800 bpjs-rise">
            <x-icon name="checkCircle" :size="18" />
            <span>{{ session('status') }}</span>
        </div>
    @endif

    <div class="mb-4 flex justify-end">
        <a href="{{ route('admin.export', 'rooms') }}" class="btn btn--ghost">{{ __('Ekspor CSV') }}</a>
    </div>
    <livewire:admin.room-list />
</x-app-layout>
