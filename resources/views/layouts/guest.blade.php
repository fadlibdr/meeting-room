<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'BPJS Kesehatan') }}</title>

        {{-- PWA --}}
        <meta name="theme-color" content="#005490">
        <link rel="manifest" href="/manifest.webmanifest">
        <link rel="apple-touch-icon" href="/images/pwa/icon-192.png">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-title" content="Ruang Rapat">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <div class="flex min-h-screen">
            {{-- ===== Brand panel (hidden < 820px) ===== --}}
            <div class="login-brand relative flex-1 flex-col justify-between p-12 text-white overflow-hidden"
                 style="display: flex; background: linear-gradient(150deg, #00538f 0%, #00416d 60%, #002e4a 100%);">
                <div class="absolute inset-0 pointer-events-none"
                     style="background: radial-gradient(90% 50% at 90% -10%, rgba(0,177,64,.22), transparent 60%);"></div>

                <div class="relative z-10">
                    <img src="{{ asset('images/bpjs/bpjs-kesehatan-logo-white.png') }}" alt="BPJS Kesehatan" style="height: 34px;">
                </div>

                <div class="relative z-10 max-w-md">
                    <h1 class="font-display font-extrabold text-white leading-tight" style="font-size: 40px; letter-spacing: -0.02em;">
                        Reservasi ruang rapat, tanpa ribet.
                    </h1>
                    <p class="mt-5 text-white/75 leading-relaxed" style="font-size: 15px;">
                        Sistem Pemesanan Ruang Rapat BPJS Kesehatan — ajukan, setujui, dan kelola
                        penggunaan ruang rapat dalam satu tempat.
                    </p>

                    <div class="mt-10 grid grid-cols-3 gap-6 max-w-sm">
                        <div>
                            <div class="font-display font-extrabold text-white" style="font-size: 26px;">{{ \App\Models\Room::where('status', 'active')->count() }}</div>
                            <div class="text-white/60 mt-1" style="font-size: 12px;">Ruang aktif</div>
                        </div>
                        <div>
                            <div class="font-display font-extrabold text-white" style="font-size: 26px;">24/7</div>
                            <div class="text-white/60 mt-1" style="font-size: 12px;">Akses daring</div>
                        </div>
                        <div>
                            <div class="font-display font-extrabold text-white" style="font-size: 26px;">100%</div>
                            <div class="text-white/60 mt-1" style="font-size: 12px;">Terpantau</div>
                        </div>
                    </div>
                </div>

                <div class="relative z-10 text-white/45" style="font-size: 12px;">
                    © {{ date('Y') }} BPJS Kesehatan · Direktorat SDM dan Umum
                </div>
            </div>

            {{-- ===== Form column ===== --}}
            <div class="login-form flex flex-col justify-center items-center bg-white px-6 py-12"
                 style="flex: 0 0 480px; max-width: 480px;">
                <div class="w-full" style="max-width: 360px;">
                    <div class="flex justify-end gap-1.5 mb-4">
                        @foreach(config('app.available_locales', []) as $code => $label)
                            <form method="POST" action="{{ route('locale.update', $code) }}">
                                @csrf
                                <button type="submit"
                                        class="pill {{ app()->getLocale() === $code ? 'pill--blue' : '' }}"
                                        style="font-size: 11px; cursor: pointer;"
                                        title="{{ $label }}">{{ strtoupper($code) }}</button>
                            </form>
                        @endforeach
                    </div>
                    <div class="mb-8 text-center" style="display: none;" x-data x-init="if (window.innerWidth <= 820) $el.style.display = 'block'">
                        <img src="{{ asset('images/bpjs/bpjs-kesehatan-logo.png') }}" alt="BPJS Kesehatan" class="inline-block" style="height: 30px;">
                    </div>
                    {{ $slot }}
                </div>
            </div>
        </div>
    </body>
</html>
