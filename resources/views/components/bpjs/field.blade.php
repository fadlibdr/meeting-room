@props([
    'label' => null,
    'req' => false,
    'error' => null,
    'hint' => null,
    'for' => null,
])
<label class="field" @if($for) for="{{ $for }}" @endif>
    @if($label)
        <span class="field__lbl">{{ $label }}@if($req)<span class="req"> *</span>@endif</span>
    @endif
    {{ $slot }}
    @if($error)
        <span class="field__err">{{ $error }}</span>
    @elseif($hint)
        <span class="field__hint">{{ $hint }}</span>
    @endif
</label>
