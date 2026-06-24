<div class="py-2">
    <div class="mb-4 flex items-center gap-3">
        <input wire:model.live.debounce.300ms="search" type="search"
               placeholder="{{ __('Cari nama atau email…') }}" class="input max-w-xs" />
        <span class="text-xs text-slate-400">{{ __('Tidak aktif = tanpa login >:n hari', ['n' => $thresholdDays]) }}</span>
        <x-bpjs.button wire:click="export" variant="ghost" icon="download" class="ml-auto">{{ __('Ekspor CSV') }}</x-bpjs.button>
    </div>

    <div class="card bpjs-rise overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-400">
                    <th class="px-4 py-3">{{ __('Pengguna') }}</th>
                    <th class="px-4 py-3">{{ __('Peran') }}</th>
                    <th class="px-4 py-3">{{ __('Status') }}</th>
                    <th class="px-4 py-3">{{ __('2FA') }}</th>
                    <th class="px-4 py-3">{{ __('Login Terakhir') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $r)
                    <tr class="border-b border-slate-100 {{ $r['inactive'] ? 'bg-amber-50/60' : '' }}">
                        <td class="px-4 py-3">
                            <div class="font-medium text-slate-800">{{ $r['user']->name }}</div>
                            <div class="text-xs text-slate-400">{{ $r['user']->email }}</div>
                        </td>
                        <td class="px-4 py-3 text-slate-600">{{ $r['roles'] ?: '—' }}</td>
                        <td class="px-4 py-3">
                            @if($r['active'])
                                <x-bpjs.pill variant="green">{{ __('Aktif') }}</x-bpjs.pill>
                            @else
                                <x-bpjs.pill variant="slate">{{ __('Nonaktif') }}</x-bpjs.pill>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if($r['mfa'])
                                <x-bpjs.pill variant="green">{{ __('Aktif') }}</x-bpjs.pill>
                            @else
                                <x-bpjs.pill variant="slate">{{ __('Tidak') }}</x-bpjs.pill>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-slate-600">
                            @if($r['last_login_at'])
                                {{ $r['last_login_at']->format('Y-m-d H:i') }}
                                @if($r['inactive'])
                                    <span class="ml-1 text-xs font-semibold text-amber-700">{{ __('(tidak aktif)') }}</span>
                                @endif
                            @else
                                <span class="text-amber-700">{{ __('Belum pernah') }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-6 text-center text-slate-400">{{ __('Tidak ada pengguna.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
