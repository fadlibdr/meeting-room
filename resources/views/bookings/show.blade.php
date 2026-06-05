<x-app-layout title="Detail Reservasi" subtitle="Informasi lengkap dan riwayat reservasi">
    <div style="max-width: 760px; display: flex; flex-direction: column; gap: 16px;">

        {{-- Kembali --}}
        <a href="{{ route('bookings.index') }}" wire:navigate
           style="display: inline-flex; align-items: center; gap: 5px; color: var(--slate-500); font-size: 13px; font-weight: 500; text-decoration: none;">
            <x-icon name="chevronLeft" :size="15" /> Kembali
        </a>

        {{-- Header: kode + status pill --}}
        <x-bpjs.card rise class="flex items-start justify-between gap-4">
            <div>
                <div class="eyebrow">Kode Reservasi</div>
                <div class="h-display mono" style="font-size: 23px; font-weight: 700; color: var(--slate-900); margin-top: 3px;">
                    {{ $booking->booking_code }}
                </div>
                <div style="font-size: 14px; color: var(--slate-600); margin-top: 4px;">{{ $booking->subject }}</div>
            </div>
            <div class="flex items-center gap-2">
                @if ($booking->isRecurring())
                    <x-bpjs.pill variant="blue"><x-icon name="calendar" :size="13" /> Berulang</x-bpjs.pill>
                @endif
                <x-bpjs.status-pill :status="$booking->status" />
            </div>
        </x-bpjs.card>

        {{-- Informasi Rapat --}}
        <x-bpjs.card title="Informasi Rapat" rise>
            <dl class="r-cols-2" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px 24px; margin: 0;">
                <div>
                    <dt style="font-size: 12px; color: var(--slate-400);">Ruangan</dt>
                    <dd style="margin: 3px 0 0; font-size: 13.5px; color: var(--slate-900); font-weight: 600;">
                        {{ $booking->room->name }}
                        @if ($booking->room->floor)
                            <span style="color: var(--slate-500); font-weight: 400;">· Lantai {{ $booking->room->floor }}</span>
                        @endif
                    </dd>
                </div>
                <div>
                    <dt style="font-size: 12px; color: var(--slate-400);">Peserta</dt>
                    <dd style="margin: 3px 0 0; font-size: 13.5px; color: var(--slate-900); font-weight: 600;">
                        {{ $booking->attendee_count }} dari {{ $booking->room->capacity }} kursi
                    </dd>
                </div>
                <div>
                    <dt style="font-size: 12px; color: var(--slate-400);">Waktu Mulai</dt>
                    <dd style="margin: 3px 0 0; font-size: 13.5px; color: var(--slate-900); font-weight: 600;" class="mono">@displayDateTime($booking->starts_at)</dd>
                </div>
                <div>
                    <dt style="font-size: 12px; color: var(--slate-400);">Waktu Selesai</dt>
                    <dd style="margin: 3px 0 0; font-size: 13.5px; color: var(--slate-900); font-weight: 600;" class="mono">@displayDateTime($booking->ends_at)</dd>
                </div>
                <div>
                    <dt style="font-size: 12px; color: var(--slate-400);">Pemesan</dt>
                    <dd style="margin: 3px 0 0; font-size: 13.5px; color: var(--slate-900); font-weight: 600;">
                        {{ $booking->requester->name }}
                        @if ($booking->requesterUnit)
                            <span style="color: var(--slate-500); font-weight: 400;">· {{ $booking->requesterUnit->name }}</span>
                        @endif
                    </dd>
                </div>
                <div>
                    <dt style="font-size: 12px; color: var(--slate-400);">Lokasi</dt>
                    <dd style="margin: 3px 0 0; font-size: 13.5px; color: var(--slate-900); font-weight: 600;">{{ $booking->room->location ?? '—' }}</dd>
                </div>
            </dl>
            @if ($booking->agenda)
                <div style="margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--slate-100);">
                    <dt style="font-size: 12px; color: var(--slate-400);">Agenda</dt>
                    <dd style="margin: 5px 0 0; font-size: 13.5px; color: var(--slate-700); line-height: 1.6; white-space: pre-line;">{{ $booking->agenda }}</dd>
                </div>
            @endif
        </x-bpjs.card>

        {{-- Lampiran --}}
        <x-bpjs.card title="Lampiran" rise>
            @if (session('status'))
                <p class="bpjs-fade" style="margin-bottom: 12px; border: 1px solid var(--bpjs-green-200); background: var(--bpjs-green-50); color: var(--bpjs-green-800); border-radius: 10px; padding: 10px 12px; font-size: 13px;">{{ session('status') }}</p>
            @endif

            @if ($booking->attachments->isEmpty())
                <p style="font-size: 13px; color: var(--slate-400);">Belum ada lampiran.</p>
            @else
                <ul style="list-style: none; margin: 0; padding: 0;">
                    @foreach ($booking->attachments as $attachment)
                        <li class="flex items-center justify-between" style="padding: 11px 0; border-top: 1px solid var(--slate-100);">
                            <div style="min-width: 0; display: flex; align-items: center; gap: 11px;">
                                <span style="color: var(--slate-400);"><x-icon name="doc" :size="18" /></span>
                                <div style="min-width: 0;">
                                    <p style="font-size: 13.5px; font-weight: 600; color: var(--slate-800); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ $attachment->original_name }}</p>
                                    <p class="mono" style="font-size: 11px; color: var(--slate-400);">{{ number_format($attachment->size_bytes / 1024, 1) }} KB</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2" style="margin-left: 16px;">
                                <a href="{{ route('bookings.attachments.download', [$booking->id, $attachment->id]) }}"
                                   class="btn btn--ghost" style="padding: 6px 12px; font-size: 12.5px; border-radius: 8px;">
                                    Unduh
                                </a>
                                @can('manageAttachments', $booking)
                                    <form method="POST" action="{{ route('bookings.attachments.destroy', [$booking->id, $attachment->id]) }}"
                                          onsubmit="return confirm('Hapus lampiran ini?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn--danger" style="padding: 6px 12px; font-size: 12.5px; border-radius: 8px;">
                                            Hapus
                                        </button>
                                    </form>
                                @endcan
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif

            @can('manageAttachments', $booking)
                <form method="POST" action="{{ route('bookings.attachments.store', $booking->id) }}"
                      enctype="multipart/form-data" style="margin-top: 16px; border-top: 1px solid var(--slate-100); padding-top: 16px;">
                    @csrf
                    <x-bpjs.field label="Unggah Lampiran" for="attachment" :error="$errors->first('attachment')"
                                  hint="PDF, dokumen Office, atau gambar. Maksimal 10 MB.">
                        <input type="file" name="attachment" id="attachment"
                               style="display: block; width: 100%; font-size: 13px; color: var(--slate-600);" />
                    </x-bpjs.field>
                    <x-bpjs.button type="submit" icon="download" class="mt-3">Unggah</x-bpjs.button>
                </form>
            @endcan
        </x-bpjs.card>

        {{-- Status Persetujuan --}}
        <x-bpjs.card title="Status Persetujuan" rise>
            @switch($booking->status)
                @case(\App\Enums\BookingStatus::Submitted)
                    <p style="font-size: 13.5px; color: var(--slate-600);">
                        Menunggu persetujuan dari
                        <span style="font-weight: 600; color: var(--slate-900);">{{ $booking->currentApprover?->name ?? 'approver yang ditunjuk' }}</span>.
                    </p>
                    @break
                @case(\App\Enums\BookingStatus::Approved)
                    <p style="font-size: 13.5px; color: var(--slate-600);">
                        Disetujui pada <span style="font-weight: 600; color: var(--slate-900);">@displayDateTime($booking->approved_at)</span>.
                    </p>
                    @break
                @case(\App\Enums\BookingStatus::Rejected)
                    <p style="font-size: 13.5px; color: var(--slate-600);">Reservasi ditolak.</p>
                    @if ($booking->rejection_reason)
                        <p style="margin-top: 8px; border: 1px solid var(--red-100); background: var(--red-50); color: var(--red-700); border-radius: 10px; padding: 10px 12px; font-size: 13px;">
                            {{ $booking->rejection_reason }}
                        </p>
                    @endif
                    @break
                @case(\App\Enums\BookingStatus::Cancelled)
                    <p style="font-size: 13.5px; color: var(--slate-600);">Reservasi dibatalkan.</p>
                    @if ($booking->cancellation_reason)
                        <p style="margin-top: 8px; border: 1px solid var(--slate-200); background: var(--slate-50); color: var(--slate-600); border-radius: 10px; padding: 10px 12px; font-size: 13px;">
                            {{ $booking->cancellation_reason }}
                        </p>
                    @endif
                    @break
                @default
                    <p style="font-size: 13.5px; color: var(--slate-500);">{{ $booking->status->label() }}.</p>
            @endswitch
        </x-bpjs.card>

        {{-- Riwayat / timeline --}}
        <x-bpjs.card title="Riwayat" rise>
            @if ($timeline->isEmpty())
                <p style="font-size: 13px; color: var(--slate-400);">Belum ada riwayat.</p>
            @else
                <ol style="list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 16px;">
                    @foreach ($timeline as $entry)
                        <li style="display: flex; gap: 12px;">
                            <span style="margin-top: 5px; width: 9px; height: 9px; border-radius: 9999px; flex-shrink: 0; background: {{ $entry['type'] === 'approval' ? 'var(--bpjs-blue-400)' : 'var(--slate-300)' }};"></span>
                            <div style="flex: 1;">
                                <div style="font-size: 13.5px; font-weight: 600; color: var(--slate-800);">{{ $entry['title'] }}</div>
                                <div style="font-size: 12px; color: var(--slate-400); margin-top: 1px;">
                                    @displayDateTime($entry['at'])
                                    @if ($entry['actor'])
                                        · {{ $entry['actor'] }}
                                    @endif
                                </div>
                                @if ($entry['detail'])
                                    <div style="margin-top: 6px; font-size: 13px; color: var(--slate-600);">{{ $entry['detail'] }}</div>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ol>
            @endif
        </x-bpjs.card>

        {{-- Tindakan --}}
        @canany(['update', 'submit', 'cancel', 'reschedule', 'delete'], $booking)
            <x-bpjs.card title="Tindakan" rise>
                <div class="flex items-center gap-2 flex-wrap">
                    @can('update', $booking)
                        <x-bpjs.button variant="ghost" icon="settings" :href="route('bookings.edit', $booking->id)">Ubah Reservasi</x-bpjs.button>
                    @endcan

                    @can('reschedule', $booking)
                        <x-bpjs.button variant="ghost" icon="calendar" :href="route('bookings.reschedule', $booking->id)">Jadwalkan Ulang</x-bpjs.button>
                    @endcan

                    @can('submit', $booking)
                        <form method="POST" action="{{ route('bookings.submit', $booking->id) }}"
                              onsubmit="return confirm('Ajukan reservasi ini untuk persetujuan?');">
                            @csrf
                            <x-bpjs.button type="submit" icon="arrowRight">Ajukan Reservasi</x-bpjs.button>
                        </form>
                    @endcan
                </div>

                @can('submit', $booking)
                    @error('submit')
                        <p style="margin-top: 12px; border: 1px solid var(--red-100); background: var(--red-50); color: var(--red-700); border-radius: 10px; padding: 10px 12px; font-size: 13px;">
                            {{ $message }}
                        </p>
                    @enderror
                @endcan

                @can('cancel', $booking)
                    <div style="margin-top: 18px; border-top: 1px solid var(--slate-100); padding-top: 18px;">
                        @error('cancel')
                            <p style="margin-bottom: 12px; border: 1px solid var(--red-100); background: var(--red-50); color: var(--red-700); border-radius: 10px; padding: 10px 12px; font-size: 13px;">
                                {{ $message }}
                            </p>
                        @enderror

                        <form method="POST" action="{{ route('bookings.cancel', $booking->id) }}"
                              onsubmit="return confirm('Batalkan reservasi ini? Tindakan ini tidak dapat diurungkan.');">
                            @csrf
                            <x-bpjs.field
                                for="cancellation_reason"
                                :req="$booking->status === \App\Enums\BookingStatus::Approved"
                                :error="$errors->first('cancellation_reason')">
                                <x-slot:label>Alasan Pembatalan</x-slot:label>
                                <textarea
                                    id="cancellation_reason"
                                    name="cancellation_reason"
                                    rows="3"
                                    class="textarea {{ $errors->has('cancellation_reason') ? 'input--err' : '' }}"
                                    placeholder="{{ $booking->status === \App\Enums\BookingStatus::Approved ? 'Wajib diisi untuk reservasi yang sudah disetujui.' : 'Opsional.' }}">{{ old('cancellation_reason') }}</textarea>
                            </x-bpjs.field>

                            <div class="flex flex-wrap items-center gap-2.5" style="margin-top: 14px;">
                                <x-bpjs.button type="submit" variant="solid-danger" icon="x">Batalkan Reservasi</x-bpjs.button>
                                @if ($booking->isRecurring())
                                    <button type="submit"
                                            formaction="{{ route('bookings.cancel-series', $booking->id) }}"
                                            onclick="return confirm('Batalkan SEMUA jadwal dalam seri berulang ini? Alasan wajib diisi.');"
                                            class="btn btn--danger">
                                        <x-icon name="x" :size="17" /> Batalkan Seri
                                    </button>
                                @endif
                            </div>
                        </form>
                    </div>
                @endcan

                @can('delete', $booking)
                    <div style="margin-top: 18px; border-top: 1px solid var(--slate-100); padding-top: 18px;">
                        @error('delete')
                            <p style="margin-bottom: 12px; border: 1px solid var(--red-100); background: var(--red-50); color: var(--red-700); border-radius: 10px; padding: 10px 12px; font-size: 13px;">
                                {{ $message }}
                            </p>
                        @enderror

                        <p style="margin-bottom: 10px; font-size: 12px; color: var(--slate-500);">
                            Menghapus draf akan menghilangkannya secara permanen dan tidak dapat diurungkan.
                        </p>
                        <form method="POST" action="{{ route('bookings.destroy', $booking->id) }}"
                              onsubmit="return confirm('Hapus draf reservasi ini secara permanen? Tindakan ini tidak dapat diurungkan.');">
                            @csrf
                            @method('DELETE')
                            <x-bpjs.button type="submit" variant="danger" icon="x">Hapus Permanen</x-bpjs.button>
                        </form>
                    </div>
                @endcan
            </x-bpjs.card>
        @endcanany

    </div>
</x-app-layout>
