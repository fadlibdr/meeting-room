<div class="py-2">
    @if($feedback)
        <div class="card card--pad bpjs-rise mb-4 flex items-center gap-2.5"
             style="border-color: var(--bpjs-green-200); background: var(--bpjs-green-50);">
            <span style="color: var(--bpjs-green-700);"><x-icon name="checkCircle" :size="18" /></span>
            <span class="text-sm font-medium" style="color: var(--bpjs-green-800);">{{ $feedback }}</span>
        </div>
    @endif

    @if($showForm)
        <form wire:submit="save" class="card bpjs-rise">
            <div class="card--pad space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <x-bpjs.field :label="__('Jenis')" req for="rtype" :error="$errors->first('type')">
                        <select wire:model="type" id="rtype" class="select @error('type') input--err @enderror">
                            @foreach($types as $t)
                                <option value="{{ $t->value }}">{{ $t->label() }}</option>
                            @endforeach
                        </select>
                    </x-bpjs.field>

                    <x-bpjs.field :label="__('Kode')" req for="rcode" :error="$errors->first('code')">
                        <input wire:model="code" type="text" id="rcode" placeholder="EQP-01"
                               class="input @error('code') input--err @enderror" />
                    </x-bpjs.field>

                    <x-bpjs.field :label="__('Nama')" req for="rname" :error="$errors->first('name')">
                        <input wire:model="name" type="text" id="rname" placeholder="Proyektor Epson 1"
                               class="input @error('name') input--err @enderror" />
                    </x-bpjs.field>

                    <x-bpjs.field :label="__('Lokasi')" for="rloc" :error="$errors->first('location')">
                        <input wire:model="location" type="text" id="rloc"
                               class="input @error('location') input--err @enderror" />
                    </x-bpjs.field>

                    <x-bpjs.field :label="__('Kapasitas')" req for="rcap"
                                  :hint="__('Jumlah unit / kursi. Gunakan 1 untuk item tunggal.')"
                                  :error="$errors->first('capacity')">
                        <input wire:model="capacity" type="number" min="1" id="rcap"
                               class="input @error('capacity') input--err @enderror" />
                    </x-bpjs.field>

                    <x-bpjs.field :label="__('Buffer Setelah Pemakaian (menit)')" for="rbuf" :error="$errors->first('bookingBufferMinutes')">
                        <input wire:model="bookingBufferMinutes" type="number" min="0" id="rbuf"
                               class="input @error('bookingBufferMinutes') input--err @enderror" />
                    </x-bpjs.field>

                    <x-bpjs.field :label="__('Status')" req for="rstatus" :error="$errors->first('status')">
                        <select wire:model="status" id="rstatus" class="select @error('status') input--err @enderror">
                            @foreach($statuses as $s)
                                <option value="{{ $s->value }}">{{ $s->label() }}</option>
                            @endforeach
                        </select>
                    </x-bpjs.field>

                    <x-bpjs.field :label="__('Mode Approval')" req for="rmode"
                                  :hint="__('Pilih None agar pemesanan langsung disetujui.')"
                                  :error="$errors->first('approvalMode')">
                        <select wire:model="approvalMode" id="rmode" class="select @error('approvalMode') input--err @enderror">
                            @foreach($approvalModes as $m)
                                <option value="{{ $m->value }}">{{ $m->label() }}</option>
                            @endforeach
                        </select>
                    </x-bpjs.field>
                </div>

                <x-bpjs.field :label="__('Deskripsi')" for="rdesc" :error="$errors->first('description')">
                    <textarea wire:model="description" id="rdesc" rows="3"
                              class="textarea @error('description') input--err @enderror"></textarea>
                </x-bpjs.field>
            </div>
            <div class="modal__foot" style="border-radius: 0 0 16px 16px;">
                <x-bpjs.button variant="ghost" type="button" wire:click="cancel">{{ __('Batal') }}</x-bpjs.button>
                <x-bpjs.button type="submit" icon="check">{{ $editingId ? __('Simpan Perubahan') : __('Buat Sumber Daya') }}</x-bpjs.button>
            </div>
        </form>
    @else
        <div class="flex justify-end mb-4">
            <x-bpjs.button icon="plus" wire:click="newResource">{{ __('Tambah Sumber Daya') }}</x-bpjs.button>
        </div>
        <div class="card bpjs-rise">
            <table class="dtable">
                <thead>
                    <tr>
                        <th>{{ __('Jenis') }}</th>
                        <th>{{ __('Kode') }}</th>
                        <th>{{ __('Nama') }}</th>
                        <th>{{ __('Kapasitas') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th class="text-right">{{ __('Aksi') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($resources as $resource)
                        <tr>
                            <td><x-bpjs.pill variant="slate">{{ $resource->type->label() }}</x-bpjs.pill></td>
                            <td class="mono text-slate-500" style="font-size:12px;">{{ $resource->code }}</td>
                            <td class="font-semibold text-slate-900">{{ $resource->name }}</td>
                            <td class="text-slate-600">{{ $resource->capacity }}</td>
                            <td>
                                @if($resource->is_active)
                                    <x-bpjs.pill variant="green">{{ __('Aktif') }}</x-bpjs.pill>
                                @else
                                    <x-bpjs.pill variant="slate">{{ $resource->status->label() }}</x-bpjs.pill>
                                @endif
                            </td>
                            <td class="text-right">
                                <div class="inline-flex items-center justify-end gap-3">
                                    <button wire:click="edit({{ $resource->id }})" type="button" class="text-sm font-semibold text-bpjs-blue-600 hover:text-bpjs-blue-700">{{ __('Edit') }}</button>
                                    <button wire:click="toggle({{ $resource->id }})" type="button" class="text-sm font-semibold text-slate-500 hover:text-slate-800">{{ $resource->is_active ? __('Nonaktifkan') : __('Aktifkan') }}</button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-slate-500" style="padding:40px 16px;">{{ __('Belum ada sumber daya.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
</div>
