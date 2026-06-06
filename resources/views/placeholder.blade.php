<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Coming Soon
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <h3 class="text-lg font-semibold mb-2">{{ __('Fitur Belum Tersedia') }}</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        {{ __('Halaman ini akan tersedia di sprint berikutnya. Saat ini, hanya autentikasi dan manajemen pengguna yang aktif.') }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
