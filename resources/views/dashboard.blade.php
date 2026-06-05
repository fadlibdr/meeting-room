<x-app-layout title="Dashboard" subtitle="Ringkasan aktivitas pemesanan ruang rapat">

    {{-- Greeting + primary action --}}
    <div class="flex items-start justify-between gap-4 flex-wrap mb-6">
        <div>
            <h2 class="font-display font-bold text-slate-900" style="font-size: 22px; letter-spacing: -0.01em;">
                {{ __('Selamat datang') }}, {{ auth()->user()->name }}
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
    <div class="grid grid-cols-1 md:grid-cols-3 gap-[18px] mb-6">
        @hasPermission('bookings.approve')
            <x-bpjs.stat
                tone="amber"
                icon="inbox"
                :eyebrow="__('Menunggu Persetujuan')"
                :value="\App\Models\Booking::where('status', 'submitted')->count()"
                :sub="__('Menunggu tindakan Anda')" />
        @endhasPermission

        @hasPermission('rooms.view')
            <x-bpjs.stat
                tone="green"
                icon="building"
                :eyebrow="__('Ruangan Tersedia')"
                :value="\App\Models\Room::where('status', 'active')->count()"
                :sub="__('Siap dipesan')" />
        @endhasPermission

        @hasPermission('users.view')
            <x-bpjs.stat
                tone="blue"
                icon="users"
                :eyebrow="__('Pengguna Aktif')"
                :value="\App\Models\User::where('is_active', true)->count()"
                :sub="__('Akun aktif')" />
        @endhasPermission
    </div>

    {{-- Recent activity (System Admin / Super Admin) --}}
    @hasPermission('activity-logs.view')
        <livewire:activity.recent-activity-feed />
    @endhasPermission

</x-app-layout>
