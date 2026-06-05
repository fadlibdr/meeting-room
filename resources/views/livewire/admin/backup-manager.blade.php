@if ($authorized)
    <x-bpjs.card>
        <div class="flex items-start justify-between gap-4 flex-wrap">
            <div class="min-w-0">
                <h3 class="font-display" style="font-size: 15px; font-weight: 700; color: var(--slate-900);">
                    Cadangan Basis Data
                </h3>
                <p class="mt-1 max-w-prose" style="font-size: 13px; color: var(--slate-500); line-height: 1.55;">
                    Unduh berkas cadangan lengkap basis data dalam format <span class="font-mono">.sql.gz</span>.
                    Berkas ini berisi <strong style="color: var(--slate-700);">seluruh data, termasuk data pribadi</strong> —
                    simpan di tempat yang aman dan hapus bila sudah tidak diperlukan.
                </p>
            </div>

            <form method="POST" action="{{ route('admin.backup.download') }}" class="shrink-0">
                @csrf
                <x-bpjs.button type="submit" variant="success" icon="download">
                    Unduh Cadangan
                </x-bpjs.button>
            </form>
        </div>
    </x-bpjs.card>
@endif
