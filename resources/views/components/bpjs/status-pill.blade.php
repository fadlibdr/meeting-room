@props(['status'])
@php
    // Accept a BookingStatus enum, a backed-enum value, or a raw string.
    $value = is_object($status) ? ($status->value ?? (string) $status) : (string) $status;
    // status -> [pill variant, Indonesian label] (mirrors BookingStatus::color/label)
    $map = [
        'draft'     => ['slate', 'Draft'],
        'submitted' => ['amber', 'Menunggu Approval'],
        'approved'  => ['green', 'Disetujui'],
        'rejected'  => ['red',   'Ditolak'],
        'cancelled' => ['slate', 'Dibatalkan'],
        'completed' => ['blue',  'Selesai'],
    ];
    [$variant, $label] = $map[$value] ?? ['slate', \Illuminate\Support\Str::headline($value)];
    // Prefer the enum's own label() if it exposes one.
    if (is_object($status) && method_exists($status, 'label')) {
        $label = $status->label();
    }
@endphp
<span {{ $attributes->merge(['class' => 'pill pill--' . $variant]) }}>{{ $label }}</span>
