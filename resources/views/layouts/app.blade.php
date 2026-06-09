@props(['title' => null, 'subtitle' => null])
@php
    $u = auth()->user();
    $initials = collect(explode(' ', trim($u?->name ?? '')))
        ->filter()->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('');
    $primaryRole = $u?->roles->firstWhere('pivot.is_primary', true)?->name
        ?? $u?->roles->first()?->name
        ?? $u?->job_title;
    $pendingCount = ($u && $u->hasPermission('bookings.approve'))
        ? \App\Models\Booking::where('status', 'submitted')->count()
        : 0;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'BPJS Kesehatan') }}</title>

        {{-- PWA --}}
        <meta name="theme-color" content="{{ $branding['color'] ?? '#005490' }}">
        <style>:root{ --brand-color: {{ $branding['color'] ?? '#005490' }}; }</style>
        <link rel="manifest" href="/manifest.webmanifest">
        <link rel="apple-touch-icon" href="/images/pwa/icon-192.png">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="apple-mobile-web-app-title" content="Ruang Rapat">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <div class="app" x-data="{ nav: window.innerWidth > 1024 }" :class="{ 'nav-collapsed': !nav }">

            {{-- Off-canvas scrim (mobile) --}}
            <div class="nav-scrim" @click="nav = false"></div>

            {{-- ============ SIDEBAR ============ --}}
            <aside class="sidebar">
                <div class="sidebar__brand">
                    <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center">
                        @if(!empty($branding['logo_url']))
                            <img src="{{ $branding['logo_url'] }}" alt="{{ $branding['name'] ?? 'Logo' }}" style="max-height: 28px;">
                        @else
                            <img src="{{ asset('images/bpjs/bpjs-kesehatan-logo-white.png') }}" alt="{{ $branding['name'] ?? 'BPJS Kesehatan' }}">
                        @endif
                    </a>
                </div>

                <nav class="nav">
                    {{-- Standalone top item --}}
                    <a href="{{ route('dashboard') }}" wire:navigate
                       class="nav__item @if(request()->routeIs('dashboard')) active @endif">
                        <x-icon name="dashboard" :size="19" /> {{ __('nav.dashboard') }}
                    </a>

                    {{-- Group: Reservasi --}}
                    @if($u->hasPermission('bookings.view') || $u->hasPermission('bookings.view-all') || $u->hasPermission('bookings.approve') || $u->hasPermission('bookings.check-in') || $u->hasPermission('rooms.view'))
                        <x-nav-group group-key="bookings" :label="__('nav.bookings_group')"
                                     :active="request()->routeIs('calendar.*') || request()->routeIs('bookings.*') || request()->routeIs('approvals.*') || request()->routeIs('front-office.*') || (request()->routeIs('rooms.*') && !request()->routeIs('admin.*'))">
                            @hasPermission('bookings.view')
                                <a href="{{ route('calendar.index') }}" wire:navigate
                                   class="nav__item @if(request()->routeIs('calendar.*') || request()->routeIs('bookings.new')) active @endif">
                                    <x-icon name="calendar" :size="19" /> {{ __('nav.calendar') }}
                                </a>
                            @endhasPermission

                            @if($u->hasPermission('bookings.view') || $u->hasPermission('bookings.view-all'))
                                <a href="{{ route('bookings.index') }}" wire:navigate
                                   class="nav__item @if(request()->routeIs('bookings.*') && !request()->routeIs('bookings.new')) active @endif">
                                    <x-icon name="doc" :size="19" /> {{ __('nav.reservations') }}
                                </a>
                            @endif

                            @hasPermission('bookings.approve')
                                <a href="{{ route('approvals.index') }}" wire:navigate
                                   class="nav__item @if(request()->routeIs('approvals.*')) active @endif">
                                    <x-icon name="inbox" :size="19" /> {{ __('nav.approvals') }}
                                    @if($pendingCount > 0)<span class="count">{{ $pendingCount }}</span>@endif
                                </a>
                            @endhasPermission

                            @hasPermission('bookings.check-in')
                                <a href="{{ route('front-office.index') }}" wire:navigate
                                   class="nav__item @if(request()->routeIs('front-office.*')) active @endif">
                                    <x-icon name="checkCircle" :size="19" /> {{ __('nav.front_desk') }}
                                </a>
                            @endhasPermission

                            @hasPermission('rooms.view')
                                <a href="{{ route('rooms.index') }}" wire:navigate
                                   class="nav__item @if(request()->routeIs('rooms.*') && !request()->routeIs('admin.*')) active @endif">
                                    <x-icon name="building" :size="19" /> {{ __('nav.rooms') }}
                                </a>
                            @endhasPermission
                        </x-nav-group>
                    @endif

                    {{-- Group: Laporan --}}
                    @hasPermission('reports.view')
                        <x-nav-group group-key="reports" :label="__('nav.reports')"
                                     :active="request()->routeIs('admin.reports.*')">
                            <a href="{{ route('admin.reports.utilization') }}" wire:navigate
                               class="nav__item @if(request()->routeIs('admin.reports.*')) active @endif">
                                <x-icon name="dashboard" :size="19" /> {{ __('nav.utilization_report') }}
                            </a>
                        </x-nav-group>
                    @endhasPermission

                    {{-- Group: Manajemen Ruang --}}
                    @if($u->hasPermission('rooms.create') || $u->hasPermission('rooms.update') || $u->hasPermission('rooms.manage-blocks'))
                        <x-nav-group group-key="rooms" :label="__('nav.room_management')"
                                     :active="request()->routeIs('admin.rooms.*') || request()->routeIs('admin.resources.*') || request()->routeIs('admin.facilities.*') || request()->routeIs('admin.room-blocks.*')">
                            @hasPermission('rooms.create')
                                <a href="{{ route('admin.rooms.index') }}" wire:navigate
                                   class="nav__item @if(request()->routeIs('admin.rooms.*')) active @endif">
                                    <x-icon name="building" :size="19" /> {{ __('nav.manage_rooms') }}
                                </a>
                            @endhasPermission

                            @hasPermission('rooms.update')
                                <a href="{{ route('admin.resources.index') }}" wire:navigate
                                   class="nav__item @if(request()->routeIs('admin.resources.*')) active @endif">
                                    <x-icon name="panelLeft" :size="19" /> {{ __('nav.resources') }}
                                </a>
                                <a href="{{ route('admin.facilities.index') }}" wire:navigate
                                   class="nav__item @if(request()->routeIs('admin.facilities.*')) active @endif">
                                    <x-icon name="panelLeft" :size="19" /> {{ __('nav.facilities') }}
                                </a>
                            @endhasPermission

                            @hasPermission('rooms.manage-blocks')
                                <a href="{{ route('admin.room-blocks.index') }}" wire:navigate
                                   class="nav__item @if(request()->routeIs('admin.room-blocks.*')) active @endif">
                                    <x-icon name="clock" :size="19" /> {{ __('nav.block_rooms') }}
                                </a>
                            @endhasPermission
                        </x-nav-group>
                    @endif

                    {{-- Group: Pengguna & Organisasi --}}
                    @hasPermission('users.view')
                        <x-nav-group group-key="users" :label="__('nav.users_org')"
                                     :active="request()->routeIs('admin.users.*') || request()->routeIs('admin.units.*')">
                            <a href="{{ route('admin.users.index') }}" wire:navigate
                               class="nav__item @if(request()->routeIs('admin.users.*')) active @endif">
                                <x-icon name="users" :size="19" /> {{ __('nav.users') }}
                            </a>
                            <a href="{{ route('admin.units.index') }}" wire:navigate
                               class="nav__item @if(request()->routeIs('admin.units.*')) active @endif">
                                <x-icon name="building" :size="19" /> {{ __('nav.units') }}
                            </a>
                        </x-nav-group>
                    @endhasPermission

                    {{-- Group: Persetujuan --}}
                    @hasPermission('app-settings.update')
                        <x-nav-group group-key="approval" :label="__('nav.approval_admin')"
                                     :active="request()->routeIs('admin.approval-policies.*') || request()->routeIs('admin.approval-delegations.*')">
                            <a href="{{ route('admin.approval-policies.index') }}" wire:navigate
                               class="nav__item @if(request()->routeIs('admin.approval-policies.*')) active @endif">
                                <x-icon name="checkCircle" :size="19" /> {{ __('nav.approval_policies') }}
                            </a>
                            <a href="{{ route('admin.approval-delegations.index') }}" wire:navigate
                               class="nav__item @if(request()->routeIs('admin.approval-delegations.*')) active @endif">
                                <x-icon name="users" :size="19" /> {{ __('nav.approval_delegations') }}
                            </a>
                        </x-nav-group>
                    @endhasPermission

                    {{-- Group: Sistem --}}
                    @if($u->hasPermission('activity-logs.view') || $u->hasPermission('app-settings.update') || $u->hasPermission('app-settings.view') || $u->isPlatformAdmin())
                        <x-nav-group group-key="system" :label="__('nav.system')"
                                     :active="request()->routeIs('admin.logs.*') || request()->routeIs('admin.webhooks.*') || request()->routeIs('admin.settings.*') || request()->routeIs('admin.tenants.*')">
                            @hasPermission('activity-logs.view')
                                <a href="{{ route('admin.logs.index') }}" wire:navigate
                                   class="nav__item @if(request()->routeIs('admin.logs.*')) active @endif">
                                    <x-icon name="doc" :size="19" /> {{ __('nav.activity_log') }}
                                </a>
                            @endhasPermission

                            @hasPermission('app-settings.update')
                                <a href="{{ route('admin.webhooks.index') }}" wire:navigate
                                   class="nav__item @if(request()->routeIs('admin.webhooks.*')) active @endif">
                                    <x-icon name="arrowRight" :size="19" /> {{ __('nav.webhooks') }}
                                </a>
                            @endhasPermission

                            @hasPermission('app-settings.view')
                                <a href="{{ route('admin.settings.index') }}" wire:navigate
                                   class="nav__item @if(request()->routeIs('admin.settings.*')) active @endif">
                                    <x-icon name="settings" :size="19" /> {{ __('nav.settings') }}
                                </a>
                            @endhasPermission

                            @if($u->isPlatformAdmin())
                                <a href="{{ route('admin.tenants.index') }}" wire:navigate
                                   class="nav__item @if(request()->routeIs('admin.tenants.*')) active @endif">
                                    <x-icon name="building" :size="19" /> {{ __('nav.tenants') }}
                                </a>
                            @endif
                        </x-nav-group>
                    @endif
                </nav>

                <div class="sidebar__foot">
                    <div class="userchip">
                        @if($u?->avatarUrl())
                            <img class="ava" src="{{ $u->avatarUrl() }}" alt="{{ $u->name }}" style="object-fit: cover;" />
                        @else
                            <div class="ava">{{ $initials ?: '—' }}</div>
                        @endif
                        <div style="min-width: 0;">
                            <div class="nm" x-data="{{ json_encode(['name' => $u->name]) }}" x-text="name"
                                 x-on:profile-updated.window="name = $event.detail.name"></div>
                            @if($primaryRole)<div class="rl">{{ $primaryRole }}</div>@endif
                        </div>
                        <form method="POST" action="{{ route('logout') }}" class="ml-auto flex">
                            @csrf
                            <button type="submit" class="lo" title="{{ __('common.logout') }}"><x-icon name="logout" :size="17" /></button>
                        </form>
                    </div>
                </div>
            </aside>

            {{-- ============ MAIN COLUMN ============ --}}
            <div class="main">
                <header class="topbar">
                    <button class="iconbtn" @click="nav = !nav" aria-label="{{ __('common.toggle_menu') }}" style="margin-right: 4px;">
                        <x-icon name="menu" :size="20" />
                    </button>

                    <div style="min-width: 0;">
                        @if($title)
                            <h1>{{ $title }}</h1>
                            @if($subtitle)<div class="sub">{{ $subtitle }}</div>@endif
                        @elseif(isset($header))
                            {{ $header }}
                        @endif
                    </div>

                    <div class="spacer"></div>

                    <livewire:notification-dropdown />

                    <x-dropdown align="right" width="56">
                        <x-slot name="trigger">
                            <button class="profilebtn" aria-label="{{ __('common.user_profile') }}">
                                @if($u?->avatarUrl())
                                    <img class="ava" src="{{ $u->avatarUrl() }}" alt="{{ $u->name }}" style="object-fit: cover;" />
                                @else
                                    <span class="ava">{{ $initials ?: '—' }}</span>
                                @endif
                                <x-icon name="chevronDown" :size="15" />
                            </button>
                        </x-slot>
                        <x-slot name="content">
                            <div class="px-4 py-3 border-b border-slate-100">
                                <div class="text-sm font-bold text-slate-900 font-display">{{ $u->name }}</div>
                                @if($primaryRole)<div class="text-xs text-slate-500 truncate">{{ $primaryRole }}</div>@endif
                            </div>
                            <x-dropdown-link :href="route('profile')" wire:navigate>
                                {{ __('common.my_profile') }}
                            </x-dropdown-link>
                            <x-dropdown-link :href="route('calendar-subscription.index')" wire:navigate>
                                {{ __('Langganan Kalender') }}
                            </x-dropdown-link>
                            <x-dropdown-link :href="route('data.export.mine')">
                                {{ __('Unduh Data Pribadi') }}
                            </x-dropdown-link>
                            <x-dropdown-link :href="route('api-tokens.index')" wire:navigate>
                                {{ __('Token API') }}
                            </x-dropdown-link>
                            <x-dropdown-link :href="route('api-docs.page')">
                                {{ __('Dokumentasi API') }}
                            </x-dropdown-link>
                            <x-dropdown-link :href="route('support')" wire:navigate>
                                {{ __('Bantuan & Dukungan') }}
                            </x-dropdown-link>
                            @hasPermission('app-settings.view')
                                <x-dropdown-link :href="route('admin.settings.index')" wire:navigate>
                                    {{ __('common.settings') }}
                                </x-dropdown-link>
                            @endhasPermission
                            <div class="px-4 py-2 border-t border-slate-100">
                                <div class="text-xs text-slate-400 mb-1.5">{{ __('common.language') }}</div>
                                <div class="flex gap-1.5">
                                    @foreach(config('app.available_locales', []) as $code => $label)
                                        <form method="POST" action="{{ route('locale.update', $code) }}">
                                            @csrf
                                            <button type="submit"
                                                    class="pill {{ app()->getLocale() === $code ? 'pill--blue' : '' }}"
                                                    style="font-size: 11px; cursor: pointer;"
                                                    @if(app()->getLocale() === $code) aria-current="true" @endif
                                                    title="{{ $label }}">{{ strtoupper($code) }}</button>
                                        </form>
                                    @endforeach
                                </div>
                            </div>
                            <form method="POST" action="{{ route('logout') }}" class="border-t border-slate-100">
                                @csrf
                                <button type="submit" class="w-full text-start">
                                    <x-dropdown-link class="text-red-600">{{ __('common.logout') }}</x-dropdown-link>
                                </button>
                            </form>
                        </x-slot>
                    </x-dropdown>
                </header>

                <main class="scroll">
                    <div class="page">
                        {{ $slot }}
                    </div>
                </main>
            </div>
        </div>

        <x-consent-banner />
    </body>
</html>
