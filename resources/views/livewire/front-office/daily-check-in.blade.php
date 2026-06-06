<div wire:poll.60s>
    {{-- Date picker + feedback --}}
    <div class="card card--pad mb-6 bpjs-rise">
        <div class="flex flex-wrap items-end gap-4 justify-between">
            <x-bpjs.field :label="__('Tanggal')" for="date">
                <input type="date" id="date" wire:model.live="date" class="input" style="min-width: 180px;" />
            </x-bpjs.field>
            <div class="text-sm text-slate-500">
                {{ count($bookings) }} {{ __('rapat disetujui') }} ·
                {{ $bookings->whereNotNull('checked_in_at')->count() }} {{ __('sudah check-in') }}
            </div>
        </div>
    </div>

    @if($feedback)
        <div role="status" class="toast bpjs-pop" style="position: static; transform: none; margin: 0 auto 18px; max-width: max-content;">
            <span class="ck"><x-icon name="checkCircle" :size="18" /></span>
            {{ $feedback }}
        </div>
    @endif

    {{-- Day schedule --}}
    <div class="flex flex-col gap-3">
        @forelse($bookings as $booking)
            @php($checkedIn = $booking->checked_in_at !== null)
            <x-bpjs.card rise wire:key="checkin-{{ $booking->id }}">
                <div class="flex items-start justify-between gap-4 flex-wrap">
                    <div class="min-w-0 flex items-start gap-4">
                        <div class="text-center" style="min-width: 64px;">
                            <div class="font-mono font-bold text-slate-900" style="font-size: 18px;">
                                {{ $booking->starts_at->copy()->setTimezone($timezone)->format('H:i') }}
                            </div>
                            <div class="font-mono text-slate-400" style="font-size: 12px;">
                                {{ $booking->ends_at->copy()->setTimezone($timezone)->format('H:i') }}
                            </div>
                        </div>
                        <div class="min-w-0">
                            <p class="h-display font-bold text-slate-900" style="font-size: 16px;">{{ $booking->subject }}</p>
                            <div class="mt-1.5 flex flex-wrap items-center gap-x-4 gap-y-1 text-slate-500" style="font-size: 12.5px;">
                                <span class="inline-flex items-center gap-1.5">
                                    <x-icon name="building" :size="14" /> {{ $booking->room?->name ?? '—' }}
                                </span>
                                <span class="inline-flex items-center gap-1.5">
                                    <x-icon name="users" :size="14" /> {{ $booking->requester?->name ?? '—' }}
                                </span>
                                <span class="inline-flex items-center gap-1.5">
                                    <x-icon name="users" :size="14" /> {{ $booking->attendee_count }} {{ __('peserta') }}
                                </span>
                                <span class="font-mono text-slate-400">{{ $booking->booking_code }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        @if($checkedIn)
                            <span class="pill pill--green inline-flex items-center gap-1">
                                <x-icon name="checkCircle" :size="14" />
                                {{ __('Check-in') }} {{ $booking->checked_in_at->copy()->setTimezone($timezone)->format('H:i') }}
                            </span>
                            <button type="button" wire:click="undoCheckIn({{ $booking->id }})" wire:loading.attr="disabled"
                                    class="text-xs text-slate-400 hover:text-slate-700" style="cursor: pointer; background: none; border: 0;">
                                {{ __('Batalkan') }}
                            </button>
                        @else
                            <div title="{{ __('Pindai untuk check-in mandiri') }}"
                                 style="width: 84px; height: 84px; background: #fff; padding: 4px; border-radius: 8px; flex-shrink: 0;">
                                {!! app(\App\Support\BookingCheckInLink::class)->qrSvg($booking, 84) !!}
                            </div>
                            <x-bpjs.button variant="success" type="button" icon="check"
                                wire:click="checkIn({{ $booking->id }})" wire:loading.attr="disabled">
                                {{ __('Check-in') }}
                            </x-bpjs.button>
                        @endif
                    </div>
                </div>
            </x-bpjs.card>
        @empty
            <x-bpjs.card rise class="text-center" style="padding: 56px 24px;">
                <div class="mx-auto flex items-center justify-center" style="width: 64px; height: 64px; border-radius: 9999px; background: var(--slate-100); color: var(--slate-400);">
                    <x-icon name="calendar" :size="34" />
                </div>
                <p class="h-display font-bold text-slate-900 mt-4" style="font-size: 18px;">{{ __('Tidak ada jadwal hari ini.') }}</p>
                <p class="mt-1.5 text-slate-500" style="font-size: 13px;">{{ __('Tidak ada reservasi disetujui pada tanggal ini.') }}</p>
            </x-bpjs.card>
        @endforelse
    </div>
</div>
