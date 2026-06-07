@php
    $rolePill = [
        'super_admin'   => 'red',
        'system_admin'  => 'blue',
        'ga_admin'      => 'blue',
        'unit_approver' => 'green',
        'requester'     => 'slate',
    ];
    $avaColors = ['var(--bpjs-blue-600)', 'var(--bpjs-green-600)', 'var(--slate-500)', 'var(--amber-700)'];
@endphp
<div>
    @if(session('error'))
        <div class="card card--pad bpjs-rise mb-4 flex items-center gap-2.5"
             style="border-color: var(--red-300); background: var(--red-50);">
            <span style="color: var(--red-600); display: inline-flex;"><x-icon name="alert" :size="18" /></span>
            <span class="text-sm font-medium" style="color: var(--red-800);">{{ session('error') }}</span>
        </div>
    @endif

    {{-- Filter bar --}}
    <div class="card bpjs-rise mb-4" style="padding: 16px; display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end;">
        <x-bpjs.field :label="__('Cari pengguna')" for="search">
            <input wire:model.live.debounce.300ms="search" type="text" id="search"
                   placeholder="{{ __('nama atau email') }}" class="input" style="min-width: 240px;" />
        </x-bpjs.field>

        <x-bpjs.field :label="__('Peran')" for="roleFilter">
            <select wire:model.live="roleFilter" id="roleFilter" class="select" style="min-width: 180px;">
                <option value="">{{ __('Semua peran') }}</option>
                @foreach($roles as $role)
                    <option value="{{ $role->code }}">{{ $role->name }}</option>
                @endforeach
            </select>
        </x-bpjs.field>

        <x-bpjs.field :label="__('Status')" for="statusFilter">
            <select wire:model.live="statusFilter" id="statusFilter" class="select" style="min-width: 150px;">
                <option value="all">{{ __('Semua status') }}</option>
                <option value="active">{{ __('Aktif') }}</option>
                <option value="inactive">{{ __('Nonaktif') }}</option>
            </select>
        </x-bpjs.field>

        <button wire:click="clearFilters" type="button"
                class="text-sm font-medium text-slate-500 hover:text-slate-800" style="padding-bottom: 10px;">
            {{ __('Reset filter') }}
        </button>

        <div style="margin-left: auto;">
            @hasPermission('users.create')
                <x-bpjs.button variant="primary" icon="plus" :href="route('admin.users.create')" wire:navigate>
                    {{ __('Tambah Pengguna') }}
                </x-bpjs.button>
            @endhasPermission
        </div>
    </div>

    {{-- Table --}}
    <div class="card bpjs-rise" style="overflow: hidden;">
        <table class="dtable">
            <thead>
                <tr>
                    <th>{{ __('Pengguna') }}</th>
                    <th>{{ __('Unit Kerja') }}</th>
                    <th>{{ __('Peran') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th class="text-right">{{ __('Aksi') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $i => $user)
                    @php
                        $initials = collect(explode(' ', trim($user->name)))
                            ->filter()->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('');
                    @endphp
                    <tr>
                        <td>
                            <div style="display: flex; align-items: center; gap: 11px;">
                                <span style="width: 34px; height: 34px; border-radius: 9999px; background: {{ $avaColors[$i % count($avaColors)] }}; color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 12px; font-family: var(--font-display); flex-shrink: 0;">{{ $initials ?: '—' }}</span>
                                <div style="min-width: 0;">
                                    <div class="font-semibold text-slate-900">{{ $user->name }}</div>
                                    <div class="mono" style="font-size: 11px; color: var(--slate-500);">{{ $user->email }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="text-slate-600">{{ $user->unit?->name ?? '—' }}</td>
                        <td>
                            <div class="flex flex-wrap gap-1">
                                @foreach($user->roles as $role)
                                    <x-bpjs.pill :variant="$rolePill[$role->code] ?? 'slate'">{{ $role->name }}</x-bpjs.pill>
                                @endforeach
                            </div>
                        </td>
                        <td>
                            @if($user->is_active)
                                <span style="display: inline-flex; align-items: center; gap: 6px; font-size: 12.5px; color: var(--bpjs-green-700); font-weight: 600;">
                                    <span style="width: 7px; height: 7px; border-radius: 9999px; background: var(--bpjs-green-500);"></span>{{ __('Aktif') }}
                                </span>
                            @else
                                <span style="display: inline-flex; align-items: center; gap: 6px; font-size: 12.5px; color: var(--slate-400); font-weight: 600;">
                                    <span style="width: 7px; height: 7px; border-radius: 9999px; background: var(--slate-300);"></span>{{ __('Nonaktif') }}
                                </span>
                            @endif
                        </td>
                        <td class="text-right">
                            @hasPermission('users.update')
                                <div style="display: inline-flex; gap: 6px;">
                                    <x-bpjs.button variant="ghost" :href="route('admin.users.edit', $user->id)" wire:navigate
                                                   class="!px-3 !py-1.5 !text-xs !rounded-lg">{{ __('Edit') }}</x-bpjs.button>
                                    <x-bpjs.button :variant="$user->is_active ? 'danger' : 'success'"
                                                   wire:click="toggleActive({{ $user->id }})"
                                                   wire:confirm="{{ $user->is_active ? __('Nonaktifkan :name?', ['name' => $user->name]) : __('Aktifkan :name?', ['name' => $user->name]) }}"
                                                   type="button"
                                                   class="!px-3 !py-1.5 !text-xs !rounded-lg">{{ $user->is_active ? __('Nonaktifkan') : __('Aktifkan') }}</x-bpjs.button>
                                    {{-- UU PDP — data-subject actions --}}
                                    <a href="{{ route('admin.users.data-export', $user->id) }}"
                                       class="inline-flex items-center px-3 py-1.5 text-xs font-semibold rounded-lg text-slate-600 hover:text-slate-900"
                                       title="{{ __('Unduh data pribadi (UU PDP)') }}">{{ __('Data') }}</a>
                                    <form method="POST" action="{{ route('admin.users.anonymize', $user->id) }}"
                                          onsubmit="return confirm('{{ __('Anonimkan data pribadi :name? Tindakan ini tidak dapat dibatalkan.', ['name' => $user->name]) }}')">
                                        @csrf
                                        <button type="submit"
                                                class="inline-flex items-center px-3 py-1.5 text-xs font-semibold rounded-lg text-red-700 hover:text-red-800">{{ __('Anonimkan') }}</button>
                                    </form>
                                </div>
                            @endhasPermission
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-slate-400" style="padding: 48px;">{{ __('Tidak ada pengguna yang cocok.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
        @if($users->hasPages())
            <div class="px-4 py-3 border-t border-slate-100">{{ $users->links() }}</div>
        @endif
    </div>
</div>
