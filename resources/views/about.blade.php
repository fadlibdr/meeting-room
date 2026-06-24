<x-app-layout :title="__('Tentang')" :subtitle="__('Informasi aplikasi')">
    <div class="py-2" style="max-width: 640px;">
        <x-bpjs.card :title="config('app.name', 'SIRRA')">
            <div class="space-y-4 text-sm text-slate-600">
                <p>{{ __('Sistem reservasi ruang rapat BPJS Kesehatan — pengajuan, persetujuan, dan penjadwalan ruang rapat dalam satu tempat.') }}</p>

                <dl class="grid grid-cols-3 gap-y-2">
                    <dt class="font-medium text-slate-500">{{ __('Versi') }}</dt>
                    <dd class="col-span-2 font-mono text-slate-800">v{{ config('app.version') }}</dd>

                    <dt class="font-medium text-slate-500">{{ __('Framework') }}</dt>
                    <dd class="col-span-2 text-slate-800">Laravel {{ app()->version() }} · PHP {{ PHP_VERSION }}</dd>
                </dl>

                <div class="border-t border-slate-100 pt-3">
                    <a href="{{ route('changelog') }}" wire:navigate class="text-sm font-semibold text-bpjs-blue-600 hover:text-bpjs-blue-700">
                        {{ __('Lihat Catatan Rilis') }} →
                    </a>
                </div>
            </div>
        </x-bpjs.card>

        <p class="mt-4 text-center text-xs text-slate-400">© {{ date('Y') }} BPJS Kesehatan</p>
    </div>
</x-app-layout>
