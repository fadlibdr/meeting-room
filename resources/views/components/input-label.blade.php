@props(['value'])

<label {{ $attributes->merge(['class' => 'block font-semibold text-[12.5px] text-slate-700']) }}>
    {{ $value ?? $slot }}
</label>
