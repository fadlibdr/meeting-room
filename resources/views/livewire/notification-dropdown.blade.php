<div x-data="{ open: false }" class="relative" @keydown.escape.window="open = false">
    <button
        @click="open = ! open"
        type="button"
        class="relative inline-flex items-center justify-center p-2 rounded-md text-gray-500 hover:text-gray-700 hover:bg-gray-100 focus:outline-none transition"
        aria-label="Notifikasi"
    >
        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
        </svg>
        @if ($this->unreadCount > 0)
            <span class="absolute -top-0.5 -end-0.5 inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1 text-xs font-semibold leading-none text-white bg-red-500 rounded-full">
                {{ $this->unreadCount > 9 ? '9+' : $this->unreadCount }}
            </span>
        @endif
    </button>

    <div
        x-show="open"
        x-cloak
        @click.outside="open = false"
        x-transition.origin.top.right
        class="absolute end-0 mt-2 w-80 max-w-[90vw] bg-white rounded-md shadow-lg ring-1 ring-black ring-opacity-5 z-50"
    >
        <div class="flex items-center justify-between px-4 py-2 border-b border-gray-100">
            <span class="text-sm font-semibold text-gray-700">Notifikasi</span>
            @if ($this->unreadCount > 0)
                <button type="button" wire:click="markAllAsRead" class="text-xs font-medium text-indigo-600 hover:text-indigo-800">
                    Tandai semua dibaca
                </button>
            @endif
        </div>

        <div class="max-h-96 overflow-y-auto divide-y divide-gray-50">
            @forelse ($this->notifications as $notification)
                <button
                    type="button"
                    wire:click="markAsRead('{{ $notification->id }}')"
                    wire:key="notif-{{ $notification->id }}"
                    class="block w-full text-start px-4 py-3 transition hover:bg-gray-50 {{ $notification->read_at ? '' : 'bg-indigo-50/60' }}"
                >
                    <div class="flex items-start gap-2">
                        @unless ($notification->read_at)
                            <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-indigo-500"></span>
                        @endunless
                        <div class="min-w-0 flex-1">
                            <p class="text-sm text-gray-800">{{ $notification->data['message'] ?? 'Notifikasi' }}</p>
                            @if (! empty($notification->data['subject']))
                                <p class="text-xs text-gray-500 truncate">{{ $notification->data['subject'] }}</p>
                            @endif
                            <p class="mt-0.5 text-xs text-gray-400">{{ $notification->created_at?->diffForHumans() }}</p>
                        </div>
                    </div>
                </button>
            @empty
                <p class="px-4 py-6 text-sm text-center text-gray-400">Tidak ada notifikasi</p>
            @endforelse
        </div>
    </div>
</div>
