<x-app-layout title="Fasilitas" subtitle="Katalog fasilitas yang dapat dipasang pada ruang rapat">
    @if(session('status'))
        <div class="mb-6 flex items-center gap-2 rounded-xl border border-bpjs-green-200 bg-bpjs-green-50 px-4 py-3 text-sm text-bpjs-green-800 bpjs-rise">
            <x-icon name="checkCircle" :size="18" />
            <span>{{ session('status') }}</span>
        </div>
    @endif

    <livewire:admin.facility-list />
</x-app-layout>
