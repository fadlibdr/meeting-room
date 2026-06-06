<div x-data="{ open: false }" class="relative" @keydown.escape.window="open = false">
    <button
        @click="open = ! open"
        type="button"
        class="iconbtn"
        aria-label="{{ __('Notifikasi') }}"
    >
        <x-icon name="bell" :size="20" />
        @if ($this->unreadCount > 0)
            <span class="dot"></span>
        @endif
    </button>

    <div
        x-show="open"
        x-cloak
        @click.outside="open = false"
        x-transition.origin.top.right
        class="bpjs-pop"
        style="position: absolute; inset-inline-end: 0; top: calc(100% + 8px); width: 340px; max-width: 90vw; background: #fff; border: 1px solid var(--slate-200); border-radius: 14px; box-shadow: 0 18px 40px rgba(16,24,40,.16); z-index: 50; overflow: hidden;"
    >
        <div class="flex items-center justify-between" style="padding: 12px 16px; border-bottom: 1px solid var(--slate-100);">
            <span class="h-display" style="font-weight: 700; font-size: 13.5px; color: var(--slate-800);">{{ __('Notifikasi') }}</span>
            @if ($this->unreadCount > 0)
                <button type="button" wire:click="markAllAsRead" style="font-size: 11.5px; font-weight: 600; color: var(--bpjs-blue-600);" class="hover:underline">
                    {{ __('Tandai semua dibaca') }}
                </button>
            @endif
        </div>

        <div style="max-height: 340px; overflow-y: auto;">
            @forelse ($this->notifications as $notification)
                <button
                    type="button"
                    wire:click="markAsRead('{{ $notification->id }}')"
                    wire:key="notif-{{ $notification->id }}"
                    class="block w-full text-start transition"
                    style="padding: 12px 16px; border-bottom: 1px solid var(--slate-50); {{ $notification->read_at ? 'background: #fff;' : 'background: rgba(0,102,179,.05);' }}"
                    onmouseover="this.style.background='var(--slate-50)'"
                    onmouseout="this.style.background='{{ $notification->read_at ? '#fff' : 'rgba(0,102,179,.05)' }}'"
                >
                    <div class="flex items-start gap-2.5">
                        <span style="margin-top: 6px; width: 7px; height: 7px; border-radius: 9999px; flex-shrink: 0; background: {{ $notification->read_at ? 'transparent' : 'var(--bpjs-blue-500)' }};"></span>
                        <div class="min-w-0 flex-1">
                            <p style="font-size: 13px; color: var(--slate-800); line-height: 1.35;">{{ $notification->data['message'] ?? __('Notifikasi') }}</p>
                            @if (! empty($notification->data['subject']))
                                <p class="truncate" style="font-size: 11.5px; color: var(--slate-500); margin-top: 2px;">{{ $notification->data['subject'] }}</p>
                            @endif
                            <p style="font-size: 11px; color: var(--slate-400); margin-top: 3px;">{{ $notification->created_at?->diffForHumans() }}</p>
                        </div>
                    </div>
                </button>
            @empty
                <p class="text-center" style="padding: 24px 16px; font-size: 13px; color: var(--slate-400);">{{ __('Tidak ada notifikasi') }}</p>
            @endforelse
        </div>
    </div>
</div>
