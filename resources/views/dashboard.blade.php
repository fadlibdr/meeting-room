<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Welcome --}}
            <div class="bg-white rounded-lg shadow-sm p-6">
                <h3 class="text-lg font-semibold text-gray-800">
                    {{ __('Selamat datang') }}, {{ auth()->user()->name }}
                </h3>
                <p class="mt-1 text-sm text-gray-600">
                    {{ __('Sistem Pemesanan Ruang Rapat BPJS Kesehatan') }}
                </p>
            </div>

            {{-- Stats grid for users with admin/approval permissions --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                @hasPermission('users.view')
                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">
                            {{ __('Total Users') }}
                        </p>
                        <p class="mt-2 text-3xl font-semibold text-gray-900">
                            {{ \App\Models\User::where('is_active', true)->count() }}
                        </p>
                        <p class="mt-1 text-xs text-gray-500">
                            {{ __('Active accounts') }}
                        </p>
                    </div>
                @endhasPermission

                @hasPermission('rooms.view')
                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">
                            {{ __('Available Rooms') }}
                        </p>
                        <p class="mt-2 text-3xl font-semibold text-gray-900">
                            {{ \App\Models\Room::where('status', 'active')->count() }}
                        </p>
                        <p class="mt-1 text-xs text-gray-500">
                            {{ __('Currently bookable') }}
                        </p>
                    </div>
                @endhasPermission

                @hasPermission('bookings.approve')
                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">
                            {{ __('Pending Approvals') }}
                        </p>
                        <p class="mt-2 text-3xl font-semibold text-gray-900">
                            {{ \App\Models\Booking::where('status', 'submitted')->count() }}
                        </p>
                        <p class="mt-1 text-xs text-gray-500">
                            {{ __('Awaiting your action') }}
                        </p>
                    </div>
                @endhasPermission
            </div>

            {{-- Recent Activity (System Admin / Super Admin only) --}}
            @hasPermission('activity-logs.view')
                <livewire:activity.recent-activity-feed />
            @endhasPermission

        </div>
    </div>
</x-app-layout>
