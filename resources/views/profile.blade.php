<x-app-layout title="Profil Saya" subtitle="Kelola informasi akun Anda">
    @php
        $u = auth()->user();
        $initials = collect(explode(' ', trim($u?->name ?? '')))
            ->filter()->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('');
        $primaryRole = $u?->roles->firstWhere('pivot.is_primary', true)?->name
            ?? $u?->roles->first()?->name
            ?? $u?->job_title
            ?? __('Pengguna');

        $myBookings = \App\Models\Booking::where('requester_user_id', $u->id)->get();
        $totalBookings = $myBookings->count();
        $approvedBookings = $myBookings->where('status', 'approved')->count();
        $pendingBookings = $myBookings->where('status', 'submitted')->count();
    @endphp

    {{-- ============ BANNER CARD ============ --}}
    <div class="card bpjs-rise" style="overflow: hidden; margin-bottom: 18px;">
        <div style="height: 96px; background: linear-gradient(120deg,#00538f,#00416d 60%,#003459); position: relative;">
            <div style="position: absolute; width: 240px; height: 240px; border-radius: 50%; background: radial-gradient(circle,rgba(0,177,64,.3),transparent 65%); top: -120px; right: 40px;"></div>
        </div>
        <div class="profile-banner__row" style="padding: 0 26px 22px; display: flex; align-items: flex-end; gap: 18px; margin-top: -42px; position: relative; z-index: 1; flex-wrap: wrap;">
            @if($u?->avatarUrl())
                <img src="{{ $u->avatarUrl() }}" alt="{{ $u->name }}"
                     style="width: 92px; height: 92px; border-radius: 22px; object-fit: cover; border: 4px solid #fff; box-shadow: 0 4px 12px rgba(16,24,40,.12); flex-shrink: 0;" />
            @else
                <div style="width: 92px; height: 92px; border-radius: 22px; background: var(--bpjs-blue-600); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 34px; font-family: var(--font-display); border: 4px solid #fff; box-shadow: 0 4px 12px rgba(16,24,40,.12); flex-shrink: 0;">{{ $initials }}</div>
            @endif
            <div style="flex: 1; min-width: 200px; padding-bottom: 4px;">
                <div class="h-display" style="font-size: 22px; font-weight: 800; color: var(--slate-900);">{{ $u->name }}</div>
                <div style="font-size: 13.5px; color: var(--slate-500); margin-top: 2px;">{{ $primaryRole }} &middot; BPJS Kesehatan</div>
            </div>
        </div>
    </div>

    {{-- ============ STAT TILES ============ --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-[18px] mb-[18px]">
        <x-bpjs.stat
            tone="blue"
            icon="doc"
            :eyebrow="__('Total Reservasi')"
            :value="$totalBookings"
            :sub="__('Reservasi yang Anda buat')" />

        <x-bpjs.stat
            tone="green"
            icon="checkCircle"
            :eyebrow="__('Disetujui')"
            :value="$approvedBookings"
            :sub="__('Reservasi disetujui')" />

        <x-bpjs.stat
            tone="amber"
            icon="clock"
            :eyebrow="__('Menunggu')"
            :value="$pendingBookings"
            :sub="__('Menunggu persetujuan')" />
    </div>

    {{-- ============ INFORMASI AKUN ============ --}}
    <x-bpjs.card title="Informasi Akun" rise class="mb-[18px]">
        <dl class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-5 gap-y-4" style="margin: 0;">
            <div>
                <dt style="font-size: 11.5px; color: var(--slate-400);">{{ __('Nama Lengkap') }}</dt>
                <dd style="margin: 3px 0 0; font-size: 13.5px; color: var(--slate-900); font-weight: 600;">{{ $u->name }}</dd>
            </div>
            <div>
                <dt style="font-size: 11.5px; color: var(--slate-400);">{{ __('NIP') }}</dt>
                <dd class="font-mono" style="margin: 3px 0 0; font-size: 13.5px; color: var(--slate-900); font-weight: 600;">{{ $u->employee_no ?? '—' }}</dd>
            </div>
            <div>
                <dt style="font-size: 11.5px; color: var(--slate-400);">{{ __('Email') }}</dt>
                <dd class="font-mono" style="margin: 3px 0 0; font-size: 13.5px; color: var(--slate-900); font-weight: 600;">{{ $u->email }}</dd>
            </div>
            <div>
                <dt style="font-size: 11.5px; color: var(--slate-400);">{{ __('Unit Kerja') }}</dt>
                <dd style="margin: 3px 0 0; font-size: 13.5px; color: var(--slate-900); font-weight: 600;">{{ $u->unit?->name ?? '—' }}</dd>
            </div>
            <div>
                <dt style="font-size: 11.5px; color: var(--slate-400);">{{ __('Jabatan') }}</dt>
                <dd style="margin: 3px 0 0; font-size: 13.5px; color: var(--slate-900); font-weight: 600;">{{ $u->job_title ?? '—' }}</dd>
            </div>
            <div>
                <dt style="font-size: 11.5px; color: var(--slate-400);">{{ __('Peran') }}</dt>
                <dd style="margin: 3px 0 0; font-size: 13.5px; color: var(--slate-900); font-weight: 600;">{{ $primaryRole }}</dd>
            </div>
            <div>
                <dt style="font-size: 11.5px; color: var(--slate-400);">{{ __('Zona Waktu') }}</dt>
                <dd style="margin: 3px 0 0; font-size: 13.5px; color: var(--slate-900); font-weight: 600;">{{ $u->timezone ?? '—' }}</dd>
            </div>
            <div>
                <dt style="font-size: 11.5px; color: var(--slate-400);">{{ __('Bergabung') }}</dt>
                <dd style="margin: 3px 0 0; font-size: 13.5px; color: var(--slate-900); font-weight: 600;">{{ $u->created_at?->translatedFormat('F Y') ?? '—' }}</dd>
            </div>
        </dl>
    </x-bpjs.card>

    {{-- ============ FORM SECTIONS ============ --}}
    <div class="grid grid-cols-1 gap-[18px]">
        <livewire:profile.update-profile-information-form />
        <livewire:profile.update-password-form />

        {{-- 4f.2 — surface data-subject rights (export) + privacy policy --}}
        <x-bpjs.card :title="__('Privasi & Data Saya')">
            <p style="font-size: 13px; color: var(--slate-500); margin: 0 0 14px;">
                {{ __('Anda berhak mengunduh salinan data pribadi Anda. Pelajari bagaimana data Anda diproses dalam Kebijakan Privasi kami.') }}
            </p>
            <div class="flex flex-wrap gap-3">
                <a href="{{ route('data.export.mine') }}" class="btn btn--primary">
                    {{ __('Unduh Data Saya') }}
                </a>
                <a href="{{ route('legal.show', 'privacy') }}" class="btn btn--ghost">
                    {{ __('Kebijakan Privasi') }}
                </a>
            </div>
        </x-bpjs.card>

        {{-- Self-service account deletion is disabled by policy (component kept for reversibility). --}}
        {{-- <livewire:profile.delete-user-form /> --}}
    </div>
</x-app-layout>
