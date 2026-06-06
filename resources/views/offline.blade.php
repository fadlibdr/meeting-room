<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#005490">
    <title>Offline · BPJS Kesehatan</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: linear-gradient(150deg, #00538f 0%, #00416d 60%, #002e4a 100%); color: #fff; padding: 24px;
        }
        .box { max-width: 380px; text-align: center; }
        .logo { height: 30px; margin-bottom: 28px; }
        .icon {
            width: 64px; height: 64px; border-radius: 9999px; margin: 0 auto 20px;
            background: rgba(255,255,255,.12); display: flex; align-items: center; justify-content: center;
        }
        h1 { font-size: 22px; margin: 0 0 8px; font-weight: 800; }
        p { font-size: 14px; line-height: 1.55; color: rgba(255,255,255,.78); margin: 0 0 6px; }
        .en { color: rgba(255,255,255,.5); font-size: 13px; }
        button {
            margin-top: 24px; background: #fff; color: #00416d; border: 0; border-radius: 10px;
            padding: 11px 22px; font-size: 14px; font-weight: 700; cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="box">
        <img class="logo" src="/images/bpjs/bpjs-kesehatan-logo-white.png" alt="BPJS Kesehatan">
        <div class="icon">
            <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M1 1l22 22"/><path d="M16.72 11.06A10.94 10.94 0 0 1 19 12.55"/>
                <path d="M5 12.55a10.94 10.94 0 0 1 5.17-2.39"/><path d="M10.71 5.05A16 16 0 0 1 22.58 9"/>
                <path d="M1.42 9a15.91 15.91 0 0 1 4.7-2.88"/><path d="M8.53 16.11a6 6 0 0 1 6.95 0"/><line x1="12" y1="20" x2="12.01" y2="20"/>
            </svg>
        </div>
        <h1>Anda sedang offline</h1>
        <p>Sambungan internet terputus. Periksa jaringan Anda lalu coba lagi.</p>
        <p class="en">You are offline. Check your connection and try again.</p>
        <button onclick="location.reload()">Coba Lagi / Retry</button>
    </div>
</body>
</html>
