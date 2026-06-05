@props([
    'pad' => true,
    'title' => null,
    'rise' => false,
])
<div {{ $attributes->merge(['class' => 'card' . ($pad ? ' card--pad' : '') . ($rise ? ' bpjs-rise' : '')]) }}>
    @if($title)<div class="card__h">{{ $title }}</div>@endif
    {{ $slot }}
</div>
