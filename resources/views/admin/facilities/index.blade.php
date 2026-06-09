<x-app-layout :title="__('Fasilitas')" :subtitle="__('Kelengkapan yang melekat pada sumber daya — bukan item yang dipesan terpisah')">
    @if(session('status'))
        <div class="mb-6 flex items-center gap-2 rounded-xl border border-bpjs-green-200 bg-bpjs-green-50 px-4 py-3 text-sm text-bpjs-green-800 bpjs-rise">
            <x-icon name="checkCircle" :size="18" />
            <span>{{ session('status') }}</span>
        </div>
    @endif

    <div class="mb-6 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
        {{ __('Fasilitas adalah atribut sebuah ruangan (mis. proyektor, papan tulis) dan tidak memiliki jadwal sendiri. Jika sebuah benda perlu dipesan terpisah dengan jadwalnya sendiri, daftarkan sebagai') }}
        <a href="{{ route('admin.resources.index') }}" class="font-semibold text-bpjs-blue-600 hover:text-bpjs-blue-700">{{ __('Sumber Daya') }}</a>
        {{ __('(jenis Peralatan), bukan di sini.') }}
    </div>

    <livewire:admin.facility-list />
</x-app-layout>
