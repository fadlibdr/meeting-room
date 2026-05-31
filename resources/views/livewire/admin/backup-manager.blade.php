{{-- Neutral Tailwind — restyle to your design system. Button uses BPJS green. --}}
@if ($authorized)
    <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">
                    Cadangan Basis Data
                </h3>
                <p class="mt-1 max-w-prose text-sm text-gray-600 dark:text-gray-400">
                    Unduh berkas cadangan lengkap basis data dalam format <code>.sql.gz</code>.
                    Berkas ini berisi <strong>seluruh data, termasuk data pribadi</strong> —
                    simpan di tempat yang aman dan hapus bila sudah tidak diperlukan.
                </p>
            </div>

            <form method="POST" action="{{ route('admin.backup.download') }}" class="shrink-0">
                @csrf
                <button type="submit"
                    class="inline-flex items-center gap-2 rounded-md bg-emerald-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none"
                         viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                    </svg>
                    Unduh Cadangan
                </button>
            </form>
        </div>
    </div>
@endif
