<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Detail Reservasi
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Header: kode + status badge --}}
            @php
                $badgeClass = match ($booking->status->color()) {
                    'green' => 'bg-green-100 text-green-800',
                    'amber' => 'bg-amber-100 text-amber-800',
                    'red' => 'bg-red-100 text-red-800',
                    'blue' => 'bg-blue-100 text-blue-800',
                    default => 'bg-slate-100 text-slate-700',
                };
            @endphp
            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-400">
                            Kode Reservasi
                        </p>
                        <h1 class="font-display text-2xl font-semibold text-slate-900 tracking-tight">
                            {{ $booking->booking_code }}
                        </h1>
                        <p class="mt-1 text-sm text-slate-500">{{ $booking->subject }}</p>
                    </div>
                    <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold {{ $badgeClass }}">
                        {{ $booking->status->label() }}
                    </span>
                </div>
            </div>

            {{-- Detail rapat --}}
            <div class="bg-white rounded-lg shadow-sm p-6">
                <h2 class="font-display text-sm font-semibold text-slate-700 mb-4">Informasi Rapat</h2>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4 text-sm">
                    <div>
                        <dt class="text-slate-400">Ruangan</dt>
                        <dd class="text-slate-900 font-medium">
                            {{ $booking->room->name }}
                            @if ($booking->room->floor)
                                <span class="text-slate-500">· Lantai {{ $booking->room->floor }}</span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-slate-400">Peserta</dt>
                        <dd class="text-slate-900 font-medium">
                            {{ $booking->attendee_count }} dari {{ $booking->room->capacity }} kursi
                        </dd>
                    </div>
                    <div>
                        <dt class="text-slate-400">Waktu Mulai</dt>
                        <dd class="text-slate-900 font-medium">@displayDateTime($booking->starts_at)</dd>
                    </div>
                    <div>
                        <dt class="text-slate-400">Waktu Selesai</dt>
                        <dd class="text-slate-900 font-medium">@displayDateTime($booking->ends_at)</dd>
                    </div>
                    <div>
                        <dt class="text-slate-400">Pemesan</dt>
                        <dd class="text-slate-900 font-medium">
                            {{ $booking->requester->name }}
                            @if ($booking->requesterUnit)
                                <span class="text-slate-500">· {{ $booking->requesterUnit->name }}</span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-slate-400">Lokasi</dt>
                        <dd class="text-slate-900 font-medium">{{ $booking->room->location ?? '—' }}</dd>
                    </div>
                </dl>
                @if ($booking->agenda)
                    <div class="mt-4 pt-4 border-t border-slate-100">
                        <dt class="text-slate-400 text-sm">Agenda</dt>
                        <dd class="text-slate-700 text-sm mt-1 whitespace-pre-line">{{ $booking->agenda }}</dd>
                    </div>
                @endif
            </div>

            {{-- Status persetujuan --}}
            <div class="bg-white rounded-lg shadow-sm p-6">
                <h2 class="font-display text-sm font-semibold text-slate-700 mb-3">Status Persetujuan</h2>
                @switch($booking->status)
                    @case(\App\Enums\BookingStatus::Submitted)
                        <p class="text-sm text-slate-600">
                            Menunggu persetujuan dari
                            <span class="font-medium text-slate-900">{{ $booking->currentApprover?->name ?? 'approver yang ditunjuk' }}</span>.
                        </p>
                        @break
                    @case(\App\Enums\BookingStatus::Approved)
                        <p class="text-sm text-slate-600">
                            Disetujui pada <span class="font-medium text-slate-900">@displayDateTime($booking->approved_at)</span>.
                        </p>
                        @break
                    @case(\App\Enums\BookingStatus::Rejected)
                        <p class="text-sm text-slate-600">Reservasi ditolak.</p>
                        @if ($booking->rejection_reason)
                            <p class="mt-2 rounded-lg bg-red-50 border border-red-100 p-3 text-sm text-red-700">
                                {{ $booking->rejection_reason }}
                            </p>
                        @endif
                        @break
                    @case(\App\Enums\BookingStatus::Cancelled)
                        <p class="text-sm text-slate-600">Reservasi dibatalkan.</p>
                        @if ($booking->cancellation_reason)
                            <p class="mt-2 rounded-lg bg-slate-50 border border-slate-100 p-3 text-sm text-slate-600">
                                {{ $booking->cancellation_reason }}
                            </p>
                        @endif
                        @break
                    @default
                        <p class="text-sm text-slate-500">{{ $booking->status->label() }}.</p>
                @endswitch
            </div>

            {{-- Riwayat / timeline --}}
            <div class="bg-white rounded-lg shadow-sm p-6">
                <h2 class="font-display text-sm font-semibold text-slate-700 mb-4">Riwayat</h2>
                @if ($timeline->isEmpty())
                    <p class="text-sm text-slate-400">Belum ada riwayat.</p>
                @else
                    <ol class="space-y-4">
                        @foreach ($timeline as $entry)
                            <li class="flex gap-3">
                                <div class="mt-1 h-2 w-2 flex-shrink-0 rounded-full {{ $entry['type'] === 'approval' ? 'bg-blue-400' : 'bg-slate-300' }}"></div>
                                <div class="flex-1">
                                    <p class="text-sm font-medium text-slate-800">{{ $entry['title'] }}</p>
                                    <p class="text-xs text-slate-400">
                                        @displayDateTime($entry['at'])
                                        @if ($entry['actor'])
                                            · {{ $entry['actor'] }}
                                        @endif
                                    </p>
                                    @if ($entry['detail'])
                                        <p class="mt-1 text-sm text-slate-600">{{ $entry['detail'] }}</p>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </div>

            {{-- Tindakan --}}
            @canany(['update', 'submit', 'cancel', 'reschedule'], $booking)
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <h2 class="font-display text-sm font-semibold text-slate-700 mb-3">Tindakan</h2>

                    @can('update', $booking)
                        <a href="{{ route('bookings.edit', $booking->id) }}"
                           class="mb-3 inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-bpjs-blue-500">
                            Ubah Reservasi
                        </a>
                    @endcan

                    @can('submit', $booking)
                        @error('submit')
                            <p class="mb-3 rounded-lg border border-red-100 bg-red-50 p-3 text-sm text-red-700">
                                {{ $message }}
                            </p>
                        @enderror

                        <form method="POST" action="{{ route('bookings.submit', $booking->id) }}"
                              onsubmit="return confirm('Ajukan reservasi ini untuk persetujuan?');">
                            @csrf
                            <button
                                type="submit"
                                class="mb-3 inline-flex items-center rounded-md bg-bpjs-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-bpjs-blue-500 focus:outline-none focus:ring-2 focus:ring-bpjs-blue-500 focus:ring-offset-2">
                                Ajukan Reservasi
                            </button>
                        </form>
                    @endcan

                    @can('reschedule', $booking)
                        <a href="{{ route('bookings.reschedule', $booking->id) }}"
                           class="mb-3 inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-bpjs-blue-500">
                            Jadwalkan Ulang
                        </a>
                    @endcan

                    @can('cancel', $booking)
                        @error('cancel')
                            <p class="mb-3 rounded-lg border border-red-100 bg-red-50 p-3 text-sm text-red-700">
                                {{ $message }}
                            </p>
                        @enderror

                        <form method="POST" action="{{ route('bookings.cancel', $booking->id) }}"
                              onsubmit="return confirm('Batalkan reservasi ini? Tindakan ini tidak dapat diurungkan.');">
                            @csrf
                            <label for="cancellation_reason" class="block text-sm font-medium text-slate-700">
                                Alasan Pembatalan
                                @if ($booking->status === \App\Enums\BookingStatus::Approved)
                                    <span class="text-red-500">*</span>
                                @endif
                            </label>
                            <textarea
                                id="cancellation_reason"
                                name="cancellation_reason"
                                rows="3"
                                class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-red-400 focus:ring-red-400"
                                placeholder="{{ $booking->status === \App\Enums\BookingStatus::Approved ? 'Wajib diisi untuk reservasi yang sudah disetujui.' : 'Opsional.' }}">{{ old('cancellation_reason') }}</textarea>
                            @error('cancellation_reason')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror

                            <div class="mt-4">
                                <button
                                    type="submit"
                                    class="inline-flex items-center rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-500 focus:outline-none focus:ring-2 focus:ring-red-400 focus:ring-offset-2">
                                    Batalkan Reservasi
                                </button>
                            </div>
                        </form>
                    @endcan
                </div>
            @endcanany

        </div>
    </div>
</x-app-layout>
