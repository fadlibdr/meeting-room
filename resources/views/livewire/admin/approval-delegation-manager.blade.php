<div class="py-2">
    @if($feedback)
        <div class="card card--pad bpjs-rise mb-4 flex items-center gap-2.5"
             style="border-color: var(--bpjs-green-200); background: var(--bpjs-green-50);">
            <span style="color: var(--bpjs-green-700); display: inline-flex;"><x-icon name="checkCircle" :size="18" /></span>
            <span class="text-sm font-medium" style="color: var(--bpjs-green-800);">{{ $feedback }}</span>
        </div>
    @endif

    @if($showForm)
        <form wire:submit="save" class="card bpjs-rise">
            <div class="card--pad grid grid-cols-1 md:grid-cols-2 gap-5">
                <x-bpjs.field :label="__('Dari (approver)')" req for="fromUserId" :error="$errors->first('fromUserId')">
                    <select wire:model="fromUserId" id="fromUserId" class="select @error('fromUserId') input--err @enderror">
                        <option value="">{{ __('— Pilih pengguna —') }}</option>
                        @foreach($users as $u)
                            <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->email }})</option>
                        @endforeach
                    </select>
                </x-bpjs.field>
                <x-bpjs.field :label="__('Kepada (delegasi)')" req for="toUserId" :error="$errors->first('toUserId')">
                    <select wire:model="toUserId" id="toUserId" class="select @error('toUserId') input--err @enderror">
                        <option value="">{{ __('— Pilih pengguna —') }}</option>
                        @foreach($users as $u)
                            <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->email }})</option>
                        @endforeach
                    </select>
                </x-bpjs.field>
                <x-bpjs.field :label="__('Mulai')" req for="startsAt" :error="$errors->first('startsAt')">
                    <input wire:model="startsAt" type="date" id="startsAt" lang="id" class="input @error('startsAt') input--err @enderror" />
                </x-bpjs.field>
                <x-bpjs.field :label="__('Selesai')" for="endsAt" :hint="__('Kosongkan untuk tanpa batas akhir.')" :error="$errors->first('endsAt')">
                    <input wire:model="endsAt" type="date" id="endsAt" lang="id" class="input @error('endsAt') input--err @enderror" />
                </x-bpjs.field>
                <div class="md:col-span-2">
                    <x-bpjs.field :label="__('Alasan (opsional)')" for="reason" :error="$errors->first('reason')">
                        <input wire:model="reason" type="text" id="reason" class="input" />
                    </x-bpjs.field>
                </div>
            </div>
            <div class="modal__foot" style="border-radius: 0 0 16px 16px;">
                <x-bpjs.button variant="ghost" type="button" wire:click="cancel">{{ __('Batal') }}</x-bpjs.button>
                <x-bpjs.button type="submit" icon="check">{{ __('Buat Delegasi') }}</x-bpjs.button>
            </div>
        </form>
    @else
        <div class="flex justify-end mb-4">
            <x-bpjs.button icon="plus" wire:click="newDelegation">{{ __('Tambah Delegasi') }}</x-bpjs.button>
        </div>

        <div class="card bpjs-rise">
            <table class="dtable">
                <thead>
                    <tr>
                        <th>{{ __('Dari') }}</th>
                        <th>{{ __('Kepada') }}</th>
                        <th>{{ __('Periode') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th class="text-right">{{ __('Aksi') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($delegations as $d)
                        @php($active = $d->starts_at <= now() && ($d->ends_at === null || $d->ends_at >= now()))
                        <tr>
                            <td class="font-semibold text-slate-900">{{ $d->fromUser->name ?? '—' }}</td>
                            <td class="text-slate-700">{{ $d->toUser->name ?? '—' }}</td>
                            <td class="font-mono text-slate-500" style="font-size: 12px;">
                                {{ $d->starts_at->copy()->setTimezone($timezone)->format('d/m/Y') }}
                                – {{ $d->ends_at ? $d->ends_at->copy()->setTimezone($timezone)->format('d/m/Y') : '∞' }}
                            </td>
                            <td>
                                @if($active)
                                    <x-bpjs.pill variant="green">{{ __('Aktif') }}</x-bpjs.pill>
                                @else
                                    <x-bpjs.pill variant="slate">{{ __('Nonaktif') }}</x-bpjs.pill>
                                @endif
                            </td>
                            <td class="text-right">
                                <div class="inline-flex items-center justify-end gap-3">
                                    @if($active)
                                        <button wire:click="endNow({{ $d->id }})" wire:confirm="{{ __('Akhiri delegasi ini sekarang?') }}" type="button"
                                                class="text-sm font-semibold text-amber-700 hover:text-amber-800">{{ __('Akhiri') }}</button>
                                    @endif
                                    <button wire:click="delete({{ $d->id }})" wire:confirm="{{ __('Hapus delegasi ini?') }}" type="button"
                                            class="text-sm font-semibold text-red-700 hover:text-red-800">{{ __('Hapus') }}</button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-slate-500" style="padding: 40px 16px;">{{ __('Belum ada delegasi.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
</div>
