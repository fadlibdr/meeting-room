<div wire:poll.30s>

    {{-- Feedback banner --}}
    @if ($feedback)
        @if ($feedbackType === 'success')
            <div role="status" class="toast bpjs-pop" style="position: static; transform: none; margin: 0 auto 18px; max-width: max-content;">
                <span class="ck"><x-icon name="checkCircle" :size="18" /></span>
                {{ $feedback }}
            </div>
        @else
            <div role="status" class="card card--pad bpjs-pop mb-[18px]" style="border-color: var(--red-300); background: var(--red-50); color: var(--red-800); display: flex; align-items: center; gap: 10px;">
                <x-icon name="info" :size="18" />
                <span class="text-sm font-medium">{{ $feedback }}</span>
            </div>
        @endif
    @endif

    {{-- Queue --}}
    <div class="flex flex-col gap-[18px]">
        @forelse ($pendingBookings as $booking)
            <x-bpjs.card rise wire:key="booking-{{ $booking->id }}">
                <div class="flex items-start justify-between gap-4 flex-wrap">
                    <div class="min-w-0">
                        <p class="h-display font-bold text-slate-900" style="font-size: 17px;">
                            {{ $booking->subject }}
                        </p>
                        <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1.5 text-slate-500" style="font-size: 12.5px;">
                            <span class="inline-flex items-center gap-1.5">
                                <x-icon name="doc" :size="14" />
                                <span class="font-mono text-slate-600">{{ $booking->booking_code }}</span>
                            </span>
                            <span class="inline-flex items-center gap-1.5">
                                <x-icon name="building" :size="14" />
                                {{ $booking->room->name }}
                            </span>
                            <span class="inline-flex items-center gap-1.5">
                                <x-icon name="clock" :size="14" />
                                <span class="font-mono">@displayDateTime($booking->starts_at)</span>
                            </span>
                        </div>
                        <div class="mt-1.5 flex flex-wrap items-center gap-x-4 gap-y-1.5 text-slate-500" style="font-size: 12.5px;">
                            <span class="inline-flex items-center gap-1.5">
                                <x-icon name="users" :size="14" />
                                Pemesan: <span class="font-medium text-slate-700">{{ $booking->requester->name }}</span>
                            </span>
                            <span class="inline-flex items-center gap-1.5">
                                <x-icon name="users" :size="14" />
                                {{ $booking->attendee_count }} peserta
                            </span>
                        </div>
                    </div>

                    <a href="{{ route('bookings.show', $booking) }}"
                       class="inline-flex items-center gap-1.5 whitespace-nowrap font-semibold"
                       style="font-size: 13px; color: var(--bpjs-blue-600);">
                        Detail
                        <x-icon name="arrowRight" :size="15" />
                    </a>
                </div>

                @if ($rejectingId === $booking->id)
                    <div class="mt-4 pt-4" style="border-top: 1px solid var(--slate-100);">
                        <x-bpjs.field label="Alasan penolakan" req :error="$errors->first('rejectReason')">
                            <textarea wire:model="rejectReason" rows="2"
                                class="textarea @error('rejectReason') input--err @enderror"
                                placeholder="Jelaskan alasan penolakan..."></textarea>
                        </x-bpjs.field>
                        <div class="mt-3 flex gap-2">
                            <x-bpjs.button variant="solid-danger" type="button" wire:click="reject" icon="check">
                                Konfirmasi Penolakan
                            </x-bpjs.button>
                            <x-bpjs.button variant="ghost" type="button" wire:click="cancelReject">
                                Batal
                            </x-bpjs.button>
                        </div>
                    </div>
                @else
                    <div class="mt-4 pt-4 flex gap-2" style="border-top: 1px solid var(--slate-100);">
                        <x-bpjs.button variant="success" type="button" wire:click="approve({{ $booking->id }})" icon="check">
                            Setujui
                        </x-bpjs.button>
                        <x-bpjs.button variant="danger" type="button" wire:click="startReject({{ $booking->id }})" icon="x">
                            Tolak
                        </x-bpjs.button>
                    </div>
                @endif
            </x-bpjs.card>
        @empty
            <x-bpjs.card rise class="text-center" style="padding: 56px 24px;">
                <div class="mx-auto flex items-center justify-center" style="width: 64px; height: 64px; border-radius: 9999px; background: var(--bpjs-green-50); color: var(--bpjs-green-600);">
                    <x-icon name="checkCircle" :size="34" />
                </div>
                <p class="h-display font-bold text-slate-900 mt-4" style="font-size: 18px;">
                    Semua beres!
                </p>
                <p class="mt-1.5 text-slate-500" style="font-size: 13px;">
                    Tidak ada reservasi yang menunggu persetujuan Anda.
                </p>
            </x-bpjs.card>
        @endforelse
    </div>
</div>
