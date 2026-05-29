<?php

use App\Livewire\Actions\Logout;
use Livewire\Volt\Component;

new class extends Component
{
    /**
     * Log the current user out of the application.
     */
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }
}; ?>

<nav x-data="{ open: false }" class="bg-white border-b border-gray-100">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}" wire:navigate>
                        <x-application-logo class="block h-9 w-auto fill-current text-gray-800" />
                    </a>
                </div>

                <!-- Navigation Links -->
                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')" wire:navigate>
                        {{ __('Dashboard') }}
                    </x-nav-link>

                    @if(auth()->user()->hasPermission('bookings.view') || auth()->user()->hasPermission('bookings.view-all'))
                        <x-nav-link :href="route('bookings.index')" :active="request()->routeIs('bookings.*') && !request()->routeIs('bookings.new')" wire:navigate>
                            {{ __('Bookings') }}
                        </x-nav-link>
                    @endif

                    @if(auth()->user()->hasPermission('bookings.view') || auth()->user()->hasPermission('bookings.view-all'))
                <x-responsive-nav-link :href="route('bookings.index')" :active="request()->routeIs('bookings.*') && !request()->routeIs('bookings.new')" wire:navigate>
                    {{ __('Bookings') }}
                </x-responsive-nav-link>
            @endif

            @hasPermission('bookings.view')
                        <x-nav-link :href="route('calendar.index')" :active="request()->routeIs('calendar.*') || request()->routeIs('bookings.new')" wire:navigate>
                            {{ __('Calendar') }}
                        </x-nav-link>
                    @endhasPermission

                    @hasPermission('bookings.approve')
                        <x-nav-link :href="route('approvals.index')" :active="request()->routeIs('approvals.*')" wire:navigate>
                            {{ __('Approvals') }}
                        </x-nav-link>
                    @endhasPermission

                    @hasPermission('rooms.view')
                        <x-nav-link :href="route('rooms.index')" :active="request()->routeIs('rooms.*') && !request()->routeIs('admin.rooms.*')" wire:navigate>
                            {{ __('Rooms') }}
                        </x-nav-link>
                    @endhasPermission

                    @hasPermission('rooms.create')
                        <x-nav-link :href="route('admin.rooms.index')" :active="request()->routeIs('admin.rooms.*')" wire:navigate>
                            {{ __('Manage Rooms') }}
                        </x-nav-link>
                    @endhasPermission

                    @hasPermission('rooms.update')
                        <x-nav-link :href="route('admin.facilities.index')" :active="request()->routeIs('admin.facilities.*')" wire:navigate>
                            {{ __('Facilities') }}
                        </x-nav-link>
                    @endhasPermission

                    @hasPermission('rooms.manage-blocks')
                        <x-nav-link :href="route('admin.room-blocks.index')" :active="request()->routeIs('admin.room-blocks.*')" wire:navigate>
                            {{ __('Room Blocks') }}
                        </x-nav-link>
                    @endhasPermission

                    @hasPermission('rooms.update')
                <x-responsive-nav-link :href="route('admin.facilities.index')" :active="request()->routeIs('admin.facilities.*')" wire:navigate>
                    {{ __('Facilities') }}
                </x-responsive-nav-link>
            @endhasPermission

            @hasPermission('rooms.manage-blocks')
                <x-responsive-nav-link :href="route('admin.room-blocks.index')" :active="request()->routeIs('admin.room-blocks.*')" wire:navigate>
                    {{ __('Room Blocks') }}
                </x-responsive-nav-link>
            @endhasPermission

            @hasPermission('users.view')
                        <x-nav-link :href="route('admin.users.index')" :active="request()->routeIs('admin.users.*')" wire:navigate>
                            {{ __('Manage Users') }}
                        </x-nav-link>
                    @endhasPermission

                    @hasPermission('activity-logs.view')
                        <x-nav-link :href="route('admin.logs.index')" :active="request()->routeIs('admin.logs.*')" wire:navigate>
                            {{ __('Activity Logs') }}
                        </x-nav-link>
                    @endhasPermission

                    @hasPermission('app-settings.view')
                        <x-nav-link :href="route('admin.settings.index')" :active="request()->routeIs('admin.settings.*')" wire:navigate>
                            {{ __('Settings') }}
                        </x-nav-link>
                    @endhasPermission
                </div>
            </div>

            <!-- Settings Dropdown -->
            <div class="hidden sm:flex sm:items-center sm:ms-6">
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 focus:outline-none transition ease-in-out duration-150">
                            <div x-data="{{ json_encode(['name' => auth()->user()->name]) }}" x-text="name" x-on:profile-updated.window="name = $event.detail.name"></div>

                            <div class="ms-1">
                                <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile')" wire:navigate>
                            {{ __('Profile') }}
                        </x-dropdown-link>

                        <!-- Authentication -->
                        <button wire:click="logout" class="w-full text-start">
                            <x-dropdown-link>
                                {{ __('Log Out') }}
                            </x-dropdown-link>
                        </button>
                    </x-slot>
                </x-dropdown>
            </div>

            <!-- Hamburger -->
            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus:text-gray-500 transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden">
        <div class="pt-2 pb-3 space-y-1">
            <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')" wire:navigate>
                {{ __('Dashboard') }}
            </x-responsive-nav-link>

            @hasPermission('bookings.view')
                <x-responsive-nav-link :href="route('calendar.index')" :active="request()->routeIs('calendar.*') || request()->routeIs('bookings.new')" wire:navigate>
                    {{ __('Calendar') }}
                </x-responsive-nav-link>
            @endhasPermission

            @hasPermission('bookings.approve')
                <x-responsive-nav-link :href="route('approvals.index')" :active="request()->routeIs('approvals.*')" wire:navigate>
                    {{ __('Approvals') }}
                </x-responsive-nav-link>
            @endhasPermission

            @hasPermission('rooms.view')
                <x-responsive-nav-link :href="route('rooms.index')" :active="request()->routeIs('rooms.*') && !request()->routeIs('admin.rooms.*')" wire:navigate>
                    {{ __('Rooms') }}
                </x-responsive-nav-link>
            @endhasPermission

            @hasPermission('rooms.create')
                <x-responsive-nav-link :href="route('admin.rooms.index')" :active="request()->routeIs('admin.rooms.*')" wire:navigate>
                    {{ __('Manage Rooms') }}
                </x-responsive-nav-link>
            @endhasPermission

            @hasPermission('users.view')
                <x-responsive-nav-link :href="route('admin.users.index')" :active="request()->routeIs('admin.users.*')" wire:navigate>
                    {{ __('Manage Users') }}
                </x-responsive-nav-link>
            @endhasPermission

            @hasPermission('activity-logs.view')
                <x-responsive-nav-link :href="route('admin.logs.index')" :active="request()->routeIs('admin.logs.*')" wire:navigate>
                    {{ __('Activity Logs') }}
                </x-responsive-nav-link>
            @endhasPermission

            @hasPermission('app-settings.view')
                <x-responsive-nav-link :href="route('admin.settings.index')" :active="request()->routeIs('admin.settings.*')" wire:navigate>
                    {{ __('Settings') }}
                </x-responsive-nav-link>
            @endhasPermission
        </div>

        <!-- Responsive Settings Options -->
        <div class="pt-4 pb-1 border-t border-gray-200">
            <div class="px-4">
                <div class="font-medium text-base text-gray-800" x-data="{{ json_encode(['name' => auth()->user()->name]) }}" x-text="name" x-on:profile-updated.window="name = $event.detail.name"></div>
                <div class="font-medium text-sm text-gray-500">{{ auth()->user()->email }}</div>
            </div>

            <div class="mt-3 space-y-1">
                <x-responsive-nav-link :href="route('profile')" wire:navigate>
                    {{ __('Profile') }}
                </x-responsive-nav-link>

                <!-- Authentication -->
                <button wire:click="logout" class="w-full text-start">
                    <x-responsive-nav-link>
                        {{ __('Log Out') }}
                    </x-responsive-nav-link>
                </button>
            </div>
        </div>
    </div>
</nav>
