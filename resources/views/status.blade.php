@php
    $map = [
        'up' => ['label' => __('Semua Sistem Operasional'), 'color' => '#16a34a', 'bg' => '#f0fdf4', 'border' => '#bbf7d0'],
        'degraded' => ['label' => __('Kinerja Menurun'), 'color' => '#b45309', 'bg' => '#fffbeb', 'border' => '#fde68a'],
        'down' => ['label' => __('Gangguan Layanan'), 'color' => '#b91c1c', 'bg' => '#fef2f2', 'border' => '#fecaca'],
    ];
    $s = $map[$status] ?? $map['down'];
@endphp
<x-layouts.public :pageTitle="__('Status Sistem')">
    <div class="rounded-xl border p-6 text-center"
         style="background: {{ $s['bg'] }}; border-color: {{ $s['border'] }};">
        <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full"
             style="background: {{ $s['color'] }};">
            <span style="display:inline-block;width:14px;height:14px;border-radius:50%;background:#fff;"></span>
        </div>
        <h1 class="text-xl font-bold" style="color: {{ $s['color'] }};">{{ $s['label'] }}</h1>
        <p class="mt-2 text-sm text-slate-500">
            {{ __('Status keseluruhan Sistem Pemesanan Ruang Rapat BPJS Kesehatan.') }}
        </p>
    </div>

    <p class="mt-6 text-center text-sm text-slate-400">
        {{ __('Jika Anda mengalami masalah yang tidak tercermin di sini, silakan hubungi tim melalui formulir bantuan di dalam aplikasi.') }}
    </p>
</x-layouts.public>
