@props([
    'eyebrow' => null,
    'value' => null,
    'sub' => null,
    'icon' => null,
    'tone' => 'blue',   // blue | green | amber | red | slate
])
@php
    $tones = [
        'blue'  => ['var(--bpjs-blue-50)',  'var(--bpjs-blue-600)'],
        'green' => ['var(--bpjs-green-50)', 'var(--bpjs-green-600)'],
        'amber' => ['var(--amber-50)',      'var(--amber-700)'],
        'red'   => ['var(--red-50)',        'var(--red-600)'],
        'slate' => ['var(--slate-100)',     'var(--slate-500)'],
    ];
    [$bg, $fg] = $tones[$tone] ?? $tones['blue'];
@endphp
<div {{ $attributes->merge(['class' => 'card card--pad stat bpjs-rise']) }} style="position: relative;">
    @if($icon)
        <span class="ic" style="background: {{ $bg }}; color: {{ $fg }};"><x-icon :name="$icon" :size="20" /></span>
    @endif
    @if($eyebrow)<span class="eye">{{ $eyebrow }}</span>@endif
    @if($value !== null)<span class="n">{{ $value }}</span>@endif
    @if($sub)<span class="d">{{ $sub }}</span>@endif
    {{ $slot }}
</div>
