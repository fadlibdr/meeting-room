@php
    // event -> [icon, bg var, fg var] (mirrors ACTIVITY_META action colors)
    $activityMeta = [
        'created'   => ['plus',       'var(--bpjs-blue-50)',  'var(--bpjs-blue-600)'],
        'submitted' => ['arrowRight', 'var(--amber-50)',      'var(--amber-700)'],
        'approved'  => ['check',      'var(--bpjs-green-50)', 'var(--bpjs-green-700)'],
        'rejected'  => ['x',          'var(--red-50)',        'var(--red-600)'],
        'cancelled' => ['x',          'var(--slate-100)',     'var(--slate-500)'],
    ];
@endphp
<div wire:poll.30s class="card" style="overflow: hidden;">
    <div class="flex items-center justify-between" style="padding: 18px 22px; border-bottom: 1px solid var(--slate-100);">
        <div class="card__h" style="margin: 0;">
            {{ __('Aktivitas Terbaru') }}
        </div>
        <span wire:loading class="flex items-center gap-1.5" style="font-size: 11.5px; color: var(--slate-400);">
            <x-icon name="clock" :size="13" />
            {{ __('Memuat...') }}
        </span>
    </div>

    @if($logs->isEmpty())
        <div class="text-center" style="padding: 32px 22px; font-size: 13.5px; color: var(--slate-500);">
            {{ __('Belum ada aktivitas yang tercatat.') }}
        </div>
    @else
        <ul>
            @foreach($logs as $log)
                @php [$icIcon, $icBg, $icFg] = $activityMeta[$log->event] ?? ['info', 'var(--slate-100)', 'var(--slate-500)']; @endphp
                <li class="flex items-start gap-3" style="padding: 13px 22px; border-top: 1px solid var(--slate-100);">
                    <span style="flex-shrink: 0; width: 34px; height: 34px; border-radius: 10px; display: flex; align-items: center; justify-content: center; background: {{ $icBg }}; color: {{ $icFg }};">
                        <x-icon :name="$icIcon" :size="17" />
                    </span>
                    <div class="flex-1 min-w-0">
                        <p style="font-size: 13.5px; color: var(--slate-900);">
                            {{ $log->description ?? ($log->module . '.' . $log->event) }}
                        </p>
                        <p class="flex items-center gap-2 mt-1" style="font-size: 11.5px; color: var(--slate-500);">
                            <span style="font-weight: 600; color: var(--slate-700);">{{ $log->actor?->name ?? __('Sistem') }}</span>
                            <span style="color: var(--slate-300);">·</span>
                            <time datetime="{{ $log->created_at?->toIso8601String() }}">
                                {{ $log->created_at?->diffForHumans() ?? '-' }}
                            </time>
                            <span style="color: var(--slate-300);">·</span>
                            <span class="font-mono" style="font-size: 10.5px; color: var(--slate-500);">{{ $log->module }}.{{ $log->event }}</span>
                        </p>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</div>
