<div wire:poll.30s class="bg-white rounded-lg shadow-sm overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
        <h3 class="text-base font-semibold text-gray-800">
            {{ __('Recent Activity') }}
        </h3>
        <span wire:loading class="text-xs text-gray-400">
            {{ __('Refreshing...') }}
        </span>
    </div>

    @if($logs->isEmpty())
        <div class="px-6 py-8 text-center text-sm text-gray-500">
            {{ __('No activity recorded yet.') }}
        </div>
    @else
        <ul class="divide-y divide-gray-100">
            @foreach($logs as $log)
                <li class="px-6 py-3 hover:bg-gray-50">
                    <div class="flex items-start gap-3">
                        <div class="flex-shrink-0 w-8 h-8 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center text-xs font-semibold">
                            @if($log->actor)
                                {{ strtoupper(substr($log->actor->name, 0, 1)) }}
                            @else
                                ?
                            @endif
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm text-gray-900">
                                {{ $log->description ?? ($log->module . '.' . $log->event) }}
                            </p>
                            <p class="text-xs text-gray-500 mt-0.5">
                                <span class="font-medium">{{ $log->actor?->name ?? __('System') }}</span>
                                <span class="mx-1">·</span>
                                <time datetime="{{ $log->created_at?->toIso8601String() }}">
                                    {{ $log->created_at?->diffForHumans() ?? '-' }}
                                </time>
                                <span class="mx-1">·</span>
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-gray-100 text-gray-700">
                                    {{ $log->module }}.{{ $log->event }}
                                </span>
                            </p>
                        </div>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</div>
