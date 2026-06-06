<x-app-layout title="Dashboard" subtitle="Ringkasan aktivitas pemesanan ruang rapat">
@php
    $u = auth()->user();
    $today = today();
    $canViewAll = $u->hasPermission('bookings.view-all');
    $canApprove = $u->hasPermission('bookings.approve');
    $canViewBookings = $canViewAll || $u->hasPermission('bookings.view');

    // Status → display colour bar / pill mapping
    $statusBar = [
        'approved'  => 'var(--bpjs-green-500)',
        'submitted' => 'var(--amber-300)',
        'completed' => 'var(--bpjs-blue-500)',
        'rejected'  => 'var(--red-500)',
        'draft'     => 'var(--slate-300)',
        'cancelled' => 'var(--slate-400)',
    ];

    // --- Analytics (management view) ---
    $statusDef = [
        ['key' => 'approved',  'label' => 'Disetujui'],
        ['key' => 'submitted', 'label' => 'Menunggu Approval'],
        ['key' => 'completed', 'label' => 'Selesai'],
        ['key' => 'rejected',  'label' => 'Ditolak'],
        ['key' => 'draft',     'label' => 'Draft'],
        ['key' => 'cancelled', 'label' => 'Dibatalkan'],
    ];
    $statusCounts = collect();
    $rooms = collect();
    $usageByRoom = collect();
    $maxUsage = 1;
    if ($canViewAll) {
        $statusCounts = \App\Models\Booking::query()->selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status');
        $usageByRoom = \App\Models\Booking::query()
            ->whereDate('starts_at', $today)
            ->whereIn('status', ['approved', 'submitted'])
            ->selectRaw('room_id, COUNT(*) as c')->groupBy('room_id')->pluck('c', 'room_id');
        $rooms = \App\Models\Room::query()->where('status', 'active')->orderBy('name')->get();
        $maxUsage = max(1, (int) ($usageByRoom->max() ?? 0));
    }
    $statusTotal = max(1, (int) $statusCounts->sum());

    // --- Today's schedule ---
    $todaySchedule = $canViewBookings
        ? \App\Models\Booking::with("room")
            ->whereDate('starts_at', $today)
            ->whereIn('status', ['approved', 'submitted', 'completed'])
            ->orderBy('starts_at')->take(8)->get()
        : collect();

    // --- My pending approvals ---
    $myPending = $canApprove
        ? \App\Models\Booking::with("room")
            ->where('current_approver_user_id', $u->id)
            ->where('status', 'submitted')
            ->orderBy('starts_at')->take(5)->get()
        : collect();
@endphp

    {{-- Greeting + primary action --}}
    <div class="flex items-start justify-between gap-4 flex-wrap mb-6">
        <div>
            <h2 class="font-display font-bold text-slate-900" style="font-size: 22px; letter-spacing: -0.01em;">
                {{ __('Selamat datang') }}, {{ $u->name }}
            </h2>
            <p class="mt-1 text-slate-500" style="font-size: 13.5px;">
                {{ __('Sistem Pemesanan Ruang Rapat BPJS Kesehatan') }}
            </p>
        </div>

        @hasPermission('bookings.create')
            <x-bpjs.button :href="route('bookings.new')" icon="plus" wire:navigate>
                {{ __('Buat Reservasi') }}
            </x-bpjs.button>
        @endhasPermission
    </div>

    {{-- Stat tiles --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-[18px] mb-[18px]">
        @hasPermission('bookings.approve')
            <x-bpjs.stat tone="amber" icon="inbox" :eyebrow="__('Menunggu Persetujuan')"
                :value="\App\Models\Booking::where('status', 'submitted')->count()" :sub="__('Menunggu tindakan Anda')" />
        @endhasPermission
        @hasPermission('rooms.view')
            <x-bpjs.stat tone="green" icon="building" :eyebrow="__('Ruangan Tersedia')"
                :value="\App\Models\Room::where('status', 'active')->count()" :sub="__('Siap dipesan')" />
        @endhasPermission
        @hasPermission('users.view')
            <x-bpjs.stat tone="blue" icon="users" :eyebrow="__('Pengguna Aktif')"
                :value="\App\Models\User::where('is_active', true)->count()" :sub="__('Akun aktif')" />
        @endhasPermission
    </div>

    {{-- Analytics row: Utilisasi Ruangan + Distribusi Status --}}
    @if($canViewAll)
        <div class="r-split mb-[18px]" style="display: grid; grid-template-columns: 1.4fr 1fr; gap: 18px;">
            <x-bpjs.card title="Utilisasi Ruangan" rise>
                @forelse($rooms as $room)
                    @php $count = (int) ($usageByRoom[$room->id] ?? 0); $pct = round($count / $maxUsage * 100); @endphp
                    <div style="margin-bottom: 13px;">
                        <div class="flex items-center justify-between" style="font-size: 13px; margin-bottom: 5px;">
                            <span style="color: var(--slate-700); font-weight: 500;">{{ $room->name }}</span>
                            <span class="font-mono" style="color: var(--slate-500); font-size: 12px;">{{ $count }}</span>
                        </div>
                        <div style="height: 8px; border-radius: 9999px; background: var(--slate-100); overflow: hidden;">
                            <div style="height: 100%; width: {{ $pct }}%; border-radius: 9999px; background: var(--bpjs-blue-500);"></div>
                        </div>
                    </div>
                @empty
                    <p style="font-size: 13px; color: var(--slate-500);">{{ __('Belum ada ruangan aktif.') }}</p>
                @endforelse
                <p class="mt-1" style="font-size: 11.5px; color: var(--slate-400);">
                    Total reservasi aktif hari ini: <span class="font-mono">{{ (int) $usageByRoom->sum() }}</span>
                </p>
            </x-bpjs.card>

            <x-bpjs.card title="Distribusi Status" rise>
                <div style="display: flex; height: 12px; border-radius: 9999px; overflow: hidden; background: var(--slate-100);">
                    @foreach($statusDef as $s)
                        @php $c = (int) ($statusCounts[$s['key']] ?? 0); @endphp
                        @if($c > 0)
                            <div style="width: {{ $c / $statusTotal * 100 }}%; background: {{ $statusBar[$s['key']] }};" title="{{ $s['label'] }}: {{ $c }}"></div>
                        @endif
                    @endforeach
                </div>
                <div class="mt-4" style="display: flex; flex-direction: column; gap: 9px;">
                    @foreach($statusDef as $s)
                        @php $c = (int) ($statusCounts[$s['key']] ?? 0); @endphp
                        <div class="flex items-center justify-between" style="font-size: 13px;">
                            <span class="flex items-center gap-2">
                                <span style="width: 9px; height: 9px; border-radius: 9999px; background: {{ $statusBar[$s['key']] }};"></span>
                                <span style="color: var(--slate-600);">{{ $s['label'] }}</span>
                            </span>
                            <span class="font-mono" style="color: var(--slate-800); font-weight: 600;">{{ $c }}</span>
                        </div>
                    @endforeach
                </div>
            </x-bpjs.card>
        </div>
    @endif

    {{-- Schedule row: Jadwal Hari Ini + Menunggu Persetujuan Anda --}}
    @if($canViewBookings || $canApprove)
        <div class="r-split mb-[18px]" style="display: grid; grid-template-columns: 1.6fr 1fr; gap: 18px;">
            @if($canViewBookings)
                <x-bpjs.card title="Jadwal Hari Ini" rise>
                    @forelse($todaySchedule as $b)
                        <div class="flex items-center gap-3" @if(!$loop->first) style="border-top: 1px solid var(--slate-100); padding-top: 11px; margin-top: 11px;" @endif>
                            <span class="font-mono" style="font-size: 12px; color: var(--slate-500); width: 44px; flex-shrink: 0;">{{ $b->starts_at->format('H:i') }}</span>
                            <span style="width: 3px; align-self: stretch; min-height: 28px; border-radius: 3px; flex-shrink: 0; background: {{ $statusBar[$b->status->value] ?? 'var(--slate-300)' }};"></span>
                            <div class="flex-1 min-w-0">
                                <div class="truncate" style="font-size: 13.5px; color: var(--slate-900); font-weight: 500;">{{ $b->subject }}</div>
                                <div style="font-size: 11.5px; color: var(--slate-500);">{{ $b->room?->name }}</div>
                            </div>
                            <x-bpjs.status-pill :status="$b->status" />
                        </div>
                    @empty
                        <div class="text-center" style="padding: 24px 0; font-size: 13px; color: var(--slate-500);">
                            {{ __('Tidak ada jadwal hari ini.') }}
                        </div>
                    @endforelse
                </x-bpjs.card>
            @endif

            @if($canApprove)
                <x-bpjs.card title="Menunggu Persetujuan Anda" rise>
                    @forelse($myPending as $b)
                        <div class="flex items-start justify-between gap-2" @if(!$loop->first) style="border-top: 1px solid var(--slate-100); padding-top: 11px; margin-top: 11px;" @endif>
                            <div class="min-w-0">
                                <div class="truncate" style="font-size: 13px; font-weight: 600; color: var(--slate-900);">{{ $b->subject }}</div>
                                <div style="font-size: 11.5px; color: var(--slate-500);">{{ $b->room?->name }} · {{ $b->starts_at->format('d M, H:i') }}</div>
                            </div>
                            <a href="{{ route('approvals.index') }}" wire:navigate class="btn btn--ghost" style="padding: 5px 12px; font-size: 12px; flex-shrink: 0;">{{ __('Tinjau') }}</a>
                        </div>
                    @empty
                        <div class="text-center" style="padding: 24px 0;">
                            <div style="color: var(--bpjs-green-500); display: flex; justify-content: center; margin-bottom: 6px;"><x-icon name="checkCircle" :size="28" /></div>
                            <p style="font-size: 13px; color: var(--slate-500);">{{ __('Semua beres!') }}</p>
                        </div>
                    @endforelse
                </x-bpjs.card>
            @endif
        </div>
    @endif

    {{-- Recent activity (System Admin / Super Admin) --}}
    @hasPermission('activity-logs.view')
        <livewire:activity.recent-activity-feed />
    @endhasPermission

</x-app-layout>
