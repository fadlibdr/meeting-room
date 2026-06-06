<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#005490">
    <title>{{ __('Check-in') }} · BPJS Kesehatan</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: linear-gradient(150deg, #00538f 0%, #00416d 60%, #002e4a 100%); color: #fff; padding: 24px;
        }
        .box { max-width: 400px; width: 100%; text-align: center; }
        .logo { height: 28px; margin-bottom: 28px; }
        .icon { width: 72px; height: 72px; border-radius: 9999px; margin: 0 auto 20px; display: flex; align-items: center; justify-content: center; }
        .ok { background: rgba(0,177,64,.18); color: #74e39b; }
        .warn { background: rgba(245,158,11,.18); color: #fcd34d; }
        .err { background: rgba(239,68,68,.18); color: #fca5a5; }
        h1 { font-size: 22px; margin: 0 0 8px; font-weight: 800; }
        p { font-size: 14px; line-height: 1.5; color: rgba(255,255,255,.8); margin: 0 0 6px; }
        .card {
            margin-top: 22px; background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.14);
            border-radius: 14px; padding: 16px 18px; text-align: left;
        }
        .row { display: flex; justify-content: space-between; gap: 12px; font-size: 13px; padding: 4px 0; }
        .row .k { color: rgba(255,255,255,.55); }
        .row .v { font-weight: 600; }
        .mono { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }
    </style>
</head>
@php
    $map = [
        'success'    => ['cls' => 'ok',   'svg' => 'check',  'title' => __('Check-in Berhasil')],
        'already'    => ['cls' => 'ok',   'svg' => 'check',  'title' => __('Sudah Check-in')],
        'too_early'  => ['cls' => 'warn', 'svg' => 'clock',  'title' => __('Belum Waktunya Check-in')],
        'too_late'   => ['cls' => 'warn', 'svg' => 'clock',  'title' => __('Waktu Check-in Berakhir')],
        'ineligible' => ['cls' => 'err',  'svg' => 'x',      'title' => __('Tidak Dapat Check-in')],
    ];
    $m = $map[$status] ?? $map['ineligible'];
    $messages = [
        'success'    => __('Kehadiran Anda telah dicatat. Selamat rapat!'),
        'already'    => __('Reservasi ini sudah tercatat check-in sebelumnya.'),
        'too_early'  => __('Check-in baru dapat dilakukan menjelang waktu mulai rapat.'),
        'too_late'   => __('Waktu rapat telah berakhir, check-in tidak lagi tersedia.'),
        'ineligible' => __('Reservasi ini tidak dapat di-check-in (mungkin sudah dibatalkan atau dilepas).'),
    ];
    $tz = (string) config('app.display_timezone', 'Asia/Jakarta');
@endphp
<body>
    <div class="box">
        <img class="logo" src="/images/bpjs/bpjs-kesehatan-logo-white.png" alt="BPJS Kesehatan">
        <div class="icon {{ $m['cls'] }}">
            @if($m['svg'] === 'check')
                <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
            @elseif($m['svg'] === 'clock')
                <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15 14"/></svg>
            @else
                <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            @endif
        </div>
        <h1>{{ $m['title'] }}</h1>
        <p>{{ $messages[$status] ?? $messages['ineligible'] }}</p>

        <div class="card">
            <div class="row"><span class="k">{{ __('Kode Reservasi') }}</span><span class="v mono">{{ $booking->booking_code }}</span></div>
            <div class="row"><span class="k">{{ __('Subjek') }}</span><span class="v">{{ $booking->subject }}</span></div>
            <div class="row"><span class="k">{{ __('Ruangan') }}</span><span class="v">{{ $booking->room->name ?? '—' }}</span></div>
            <div class="row"><span class="k">{{ __('Waktu') }}</span><span class="v mono">{{ $booking->starts_at->copy()->setTimezone($tz)->format('d/m H:i') }}–{{ $booking->ends_at->copy()->setTimezone($tz)->format('H:i') }}</span></div>
        </div>
    </div>
</body>
</html>
