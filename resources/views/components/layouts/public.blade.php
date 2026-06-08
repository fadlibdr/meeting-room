@props(['pageTitle' => ''])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ ($pageTitle ?? '') ? $pageTitle.' · ' : '' }}{{ config('app.name', 'BPJS Kesehatan') }}</title>
        <meta name="theme-color" content="#005490">
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        {{-- 4f.4 — non-essential (analytics) scripts load ONLY after opt-in. --}}
        @if(\App\Support\Consent::granted('analytics'))
            {{-- Place analytics/marketing tags here; gated on explicit consent. --}}
        @endif
    </head>
    <body class="font-sans antialiased bg-slate-50 text-slate-800">
        <header class="border-b border-slate-200 bg-white">
            <div class="mx-auto flex max-w-4xl items-center justify-between px-6 py-4">
                <a href="{{ url('/') }}" class="flex items-center gap-2">
                    <img src="{{ asset('images/bpjs/bpjs-kesehatan-logo.png') }}" alt="BPJS Kesehatan" style="height: 28px;">
                </a>
                <nav class="flex items-center gap-4 text-sm text-slate-500">
                    @auth
                        <a href="{{ route('dashboard') }}" class="hover:text-bpjs-blue-600">{{ __('Dasbor') }}</a>
                    @else
                        <a href="{{ route('login') }}" class="hover:text-bpjs-blue-600">{{ __('Masuk') }}</a>
                    @endauth
                </nav>
            </div>
        </header>

        <main class="mx-auto max-w-4xl px-6 py-10">
            {{ $slot }}
        </main>

        <footer class="border-t border-slate-200 bg-white">
            <div class="mx-auto max-w-4xl px-6 py-8 text-sm text-slate-500">
                <nav class="flex flex-wrap gap-x-5 gap-y-2">
                    <a href="{{ route('legal.show', 'terms') }}" class="hover:text-bpjs-blue-600">{{ __('Syarat & Ketentuan') }}</a>
                    <a href="{{ route('legal.show', 'privacy') }}" class="hover:text-bpjs-blue-600">{{ __('Kebijakan Privasi') }}</a>
                    <a href="{{ route('legal.show', 'dpa') }}" class="hover:text-bpjs-blue-600">{{ __('DPA') }}</a>
                    <a href="{{ route('legal.show', 'security') }}" class="hover:text-bpjs-blue-600">{{ __('Keamanan') }}</a>
                    @if(Route::has('changelog'))
                        <a href="{{ route('changelog') }}" class="hover:text-bpjs-blue-600">{{ __('Catatan Rilis') }}</a>
                    @endif
                    @if(Route::has('status'))
                        <a href="{{ route('status') }}" class="hover:text-bpjs-blue-600">{{ __('Status') }}</a>
                    @endif
                </nav>
                <p class="mt-4 text-slate-400">© {{ date('Y') }} BPJS Kesehatan · Direktorat SDM dan Umum</p>
            </div>
        </footer>

        <x-consent-banner />
    </body>
</html>
