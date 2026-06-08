<x-layouts.public :pageTitle="$title">
    <div class="mb-6 rounded-lg border border-slate-300 bg-slate-100 px-4 py-2 text-xs text-slate-500">
        {{ __('Pratinjau internal — materi pemasaran masih draf dan belum diluncurkan.') }}
    </div>
    <article class="legal-prose">
        {!! $html !!}
    </article>
</x-layouts.public>
