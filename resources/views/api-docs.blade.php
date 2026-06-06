<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#005490">
    <title>{{ __('Dokumentasi API') }} · BPJS Kesehatan</title>
    <style>
        body { margin: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        .topbar {
            display: flex; align-items: center; gap: 12px; padding: 10px 18px;
            background: #00416d; color: #fff; position: sticky; top: 0; z-index: 20;
        }
        .topbar img { height: 22px; }
        .topbar a { color: #fff; text-decoration: none; font-size: 13px; opacity: .85; }
        .topbar a:hover { opacity: 1; }
        .topbar .sp { flex: 1; }
    </style>
</head>
<body>
    <div class="topbar">
        <img src="/images/bpjs/bpjs-kesehatan-logo-white.png" alt="BPJS Kesehatan">
        <strong style="font-size: 14px;">{{ __('Dokumentasi API') }}</strong>
        <span class="sp"></span>
        <a href="{{ route('dashboard') }}">&larr; {{ __('Kembali ke aplikasi') }}</a>
    </div>

    <redoc spec-url="{{ route('api-docs.spec') }}" hide-download-button></redoc>
    <script src="{{ asset('vendor/redoc/redoc.standalone.js') }}"></script>
</body>
</html>
