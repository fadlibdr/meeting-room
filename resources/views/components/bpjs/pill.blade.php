@props([
    'variant' => 'slate',   // green | amber | red | blue | slate
])
<span {{ $attributes->merge(['class' => 'pill pill--' . $variant]) }}>{{ $slot }}</span>
