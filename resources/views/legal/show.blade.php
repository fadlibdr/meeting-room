<x-layouts.public :pageTitle="$title">
    @unless($reviewed)
        <div class="mb-8 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="status">
            <strong class="font-semibold">{{ __('Draf — menunggu peninjauan hukum.') }}</strong>
            {{ __('Dokumen ini adalah kerangka acuan yang disusun untuk kesesuaian dengan UU PDP (UU 27/2022) dan GDPR. Ini BUKAN nasihat hukum dan belum mengikat. Teks final akan disusun dan disetujui oleh penasihat hukum.') }}
        </div>
    @endunless

    <article class="legal-prose">
        {!! $html !!}
    </article>
</x-layouts.public>
