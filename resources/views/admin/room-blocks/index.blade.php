<x-app-layout title="Blokir Ruangan" subtitle="Tutup ruangan untuk pemeliharaan atau acara.">
    @if(session('status'))
        <div class="card card--pad bpjs-rise mb-4 flex items-center gap-2.5"
             style="border-color: var(--bpjs-green-200); background: var(--bpjs-green-50);">
            <span style="color: var(--bpjs-green-700); display: inline-flex;"><x-icon name="checkCircle" :size="18" /></span>
            <span class="text-sm font-medium" style="color: var(--bpjs-green-800);">{{ session('status') }}</span>
        </div>
    @endif
    <livewire:admin.room-block-list />
</x-app-layout>
