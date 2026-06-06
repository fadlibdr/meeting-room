<div class="py-2">
    @if($feedback)
        <div class="card card--pad bpjs-rise mb-4 flex items-center gap-2.5"
             style="border-color: var(--bpjs-green-200); background: var(--bpjs-green-50);">
            <span style="color: var(--bpjs-green-700);"><x-icon name="checkCircle" :size="18" /></span>
            <span class="text-sm font-medium" style="color: var(--bpjs-green-800);">{{ $feedback }}</span>
        </div>
    @endif

    @if($plainSecret)
        <div class="card card--pad bpjs-rise mb-5" style="border-color: var(--amber-200); background: var(--amber-50);">
            <h3 class="text-sm font-semibold" style="color: var(--amber-800);">{{ __('Secret penandatangan — salin sekarang') }}</h3>
            <p class="mt-1 text-sm" style="color: var(--amber-700);">{{ __('Secret hanya ditampilkan sekali. Gunakan untuk memverifikasi header X-Webhook-Signature (HMAC SHA-256).') }}</p>
            <code class="mono mt-3 block select-all" style="background:#fff; border:1px solid var(--amber-200); border-radius:8px; padding:10px 12px; font-size:12.5px; word-break:break-all;">{{ $plainSecret }}</code>
            <button type="button" wire:click="dismissSecret" class="mt-3 text-xs font-semibold text-slate-500 hover:text-slate-800">{{ __('Saya sudah menyalin') }}</button>
        </div>
    @endif

    @if($showForm)
        <form wire:submit="save" class="card bpjs-rise">
            <div class="card--pad space-y-4">
                <x-bpjs.field :label="__('Nama')" req for="wname" :error="$errors->first('name')">
                    <input wire:model="name" type="text" id="wname" class="input @error('name') input--err @enderror" />
                </x-bpjs.field>
                <x-bpjs.field label="URL" req for="wurl" :error="$errors->first('url')">
                    <input wire:model="url" type="url" id="wurl" placeholder="https://contoh.go.id/webhook" class="input @error('url') input--err @enderror" />
                </x-bpjs.field>
                <x-bpjs.field :label="__('Peristiwa')" :error="$errors->first('events')">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        @foreach($webhookEvents as $event)
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" wire:model="events" value="{{ $event->value }}"
                                       class="rounded border-slate-300 text-bpjs-blue-600 shadow-sm focus:ring-bpjs-blue-500" />
                                <span class="text-sm text-slate-700">{{ $event->label() }}</span>
                                <span class="mono text-xs text-slate-400">{{ $event->value }}</span>
                            </label>
                        @endforeach
                    </div>
                </x-bpjs.field>
                <label class="flex items-center cursor-pointer gap-2">
                    <input type="checkbox" wire:model="isActive" class="rounded border-slate-300 text-bpjs-blue-600 shadow-sm focus:ring-bpjs-blue-500" />
                    <span class="text-sm text-slate-700">{{ __('Aktif') }}</span>
                </label>
            </div>
            <div class="modal__foot" style="border-radius: 0 0 16px 16px;">
                <x-bpjs.button variant="ghost" type="button" wire:click="cancel">{{ __('Batal') }}</x-bpjs.button>
                <x-bpjs.button type="submit" icon="check">{{ $editingId ? __('Simpan Perubahan') : __('Buat Webhook') }}</x-bpjs.button>
            </div>
        </form>
    @else
        <div class="flex justify-end mb-4">
            <x-bpjs.button icon="plus" wire:click="newSubscription">{{ __('Tambah Webhook') }}</x-bpjs.button>
        </div>
        <div class="card bpjs-rise">
            <table class="dtable">
                <thead>
                    <tr>
                        <th>{{ __('Nama') }}</th>
                        <th>URL</th>
                        <th>{{ __('Peristiwa') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th class="text-right">{{ __('Aksi') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($subscriptions as $sub)
                        <tr>
                            <td class="font-semibold text-slate-900">{{ $sub->name }}</td>
                            <td class="mono text-slate-500" style="font-size:12px; max-width:220px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $sub->url }}</td>
                            <td class="font-mono text-slate-600" style="font-size:11px;">{{ count($sub->events) }}</td>
                            <td>
                                @if($sub->is_active)
                                    <x-bpjs.pill variant="green">{{ __('Aktif') }}</x-bpjs.pill>
                                @else
                                    <x-bpjs.pill variant="slate">{{ __('Nonaktif') }}</x-bpjs.pill>
                                @endif
                            </td>
                            <td class="text-right">
                                <div class="inline-flex items-center justify-end gap-3">
                                    <button wire:click="edit({{ $sub->id }})" type="button" class="text-sm font-semibold text-bpjs-blue-600 hover:text-bpjs-blue-700">{{ __('Edit') }}</button>
                                    <button wire:click="toggle({{ $sub->id }})" type="button" class="text-sm font-semibold text-slate-500 hover:text-slate-800">{{ $sub->is_active ? __('Nonaktifkan') : __('Aktifkan') }}</button>
                                    <button wire:click="delete({{ $sub->id }})" wire:confirm="{{ __('Hapus webhook ini?') }}" type="button" class="text-sm font-semibold text-red-700 hover:text-red-800">{{ __('Hapus') }}</button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-slate-500" style="padding:40px 16px;">{{ __('Belum ada webhook.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
</div>
