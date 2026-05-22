<div wire:poll.30s>
    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Page header --}}
            <div>
                <h1 class="font-display text-3xl font-semibold text-slate-900 tracking-tight">
                    Kotak Persetujuan
                </h1>
                <p class="mt-2 text-sm text-slate-500">
                    Reservasi yang menunggu keputusan Anda.
                </p>
            </div>

            {{-- Feedback banner --}}
            @if ($feedback)
                <div role="status"
                    class="rounded-lg border p-4 text-sm {{ $feedbackType === 'success'
                        ? 'border-green-200 bg-green-50 text-green-800'
                        : 'border-red-200 bg-red-50 text-red-700' }}">
                    {{ $feedback }}
                </div>
            @endif

            {{-- Queue --}}
            @forelse ($pendingBookings as $booking)
                <div class="bg-white rounded-lg shadow-sm p-6" wire:key="booking-{{ $booking->id }}">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="font-display text-lg font-semibold text-slate-900">
                                {{ $booking->subject }}
                            </p>
                            <p class="mt-1 text-sm text-slate-500">
                                {{ $booking->booking_code }} · {{ $booking->room->name }} ·
                                @displayDateTime($booking->starts_at)
                            </p>
                            <p class="mt-1 text-sm text-slate-500">
                                Pemesan: {{ $booking->requester->name }} ·
                                {{ $booking->attendee_count }} peserta
                            </p>
                        </div>
                        <a href="{{ route('bookings.show', $booking) }}"
                           class="text-sm text-blue-600 hover:text-blue-800 whitespace-nowrap">
                            Lihat detail
                        </a>
                    </div>

                    @if ($rejectingId === $booking->id)
                        <div class="mt-4 pt-4 border-t border-slate-100">
                            <label class="block text-sm font-medium text-slate-700 mb-1">
                                Alasan penolakan
                            </label>
                            <textarea wire:model="rejectReason" rows="2"
                                class="w-full rounded-lg border-slate-300 text-sm focus:border-red-400 focus:ring-red-400"
                                placeholder="Jelaskan alasan penolakan..."></textarea>
                            @error('rejectReason')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                            <div class="mt-3 flex gap-2">
                                <button type="button" wire:click="reject"
                                    class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">
                                    Konfirmasi Penolakan
                                </button>
                                <button type="button" wire:click="cancelReject"
                                    class="rounded-lg bg-slate-100 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-200">
                                    Batal
                                </button>
                            </div>
                        </div>
                    @else
                        <div class="mt-4 flex gap-2">
                            <button type="button" wire:click="approve({{ $booking->id }})"
                                class="rounded-lg bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-700">
                                Setujui
                            </button>
                            <button type="button" wire:click="startReject({{ $booking->id }})"
                                class="rounded-lg bg-white border border-red-300 px-4 py-2 text-sm font-semibold text-red-600 hover:bg-red-50">
                                Tolak
                            </button>
                        </div>
                    @endif
                </div>
            @empty
                <div class="bg-white rounded-lg shadow-sm p-10 text-center">
                    <p class="text-sm text-slate-400">
                        Tidak ada reservasi yang menunggu persetujuan Anda.
                    </p>
                </div>
            @endforelse

        </div>
    </div>
</div>
