@props([
    'variant' => 'primary',   // primary | success | ghost | danger | solid-danger
    'size' => null,           // null | lg
    'block' => false,
    'icon' => null,
    'href' => null,
    'type' => 'button',
])
@php
    $classes = 'btn btn--' . $variant
        . ($size === 'lg' ? ' btn--lg' : '')
        . ($block ? ' btn--block' : '');
    $iconSize = $size === 'lg' ? 19 : 17;
@endphp
@if($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if($icon)<x-icon :name="$icon" :size="$iconSize" />@endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if($icon)<x-icon :name="$icon" :size="$iconSize" />@endif
        {{ $slot }}
    </button>
@endif
