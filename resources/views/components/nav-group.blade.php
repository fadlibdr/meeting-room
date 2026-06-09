@props(['groupKey', 'label', 'active' => false])

{{--
    Collapsible sidebar nav group. Click the header to expand/collapse. State is
    persisted in localStorage (per group) so it survives wire:navigate; the group
    containing the active route always starts expanded.
--}}
<div class="nav__group"
     x-data="{ open: {{ $active ? 'true' : 'false' }} }"
     x-init="
        const s = localStorage.getItem('navgrp:{{ $groupKey }}');
        if (s !== null) open = (s === '1');
        @if($active) open = true; @endif
        $watch('open', v => localStorage.setItem('navgrp:{{ $groupKey }}', v ? '1' : '0'));
     ">
    <button type="button" class="nav__grouphead" @click="open = ! open" :aria-expanded="open ? 'true' : 'false'">
        <span>{{ $label }}</span>
        <span class="nav__chev" :class="{ 'nav__chev--open': open }"><x-icon name="chevronDown" :size="14" /></span>
    </button>
    <div class="nav__groupbody" x-show="open" x-transition.opacity.duration.150ms>
        {{ $slot }}
    </div>
</div>
