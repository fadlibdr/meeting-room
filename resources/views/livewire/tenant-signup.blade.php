<div class="w-full" style="max-width: 440px; margin: 0 auto;">
    @if($completed)
        <div class="card card--pad bpjs-rise text-center space-y-3">
            <span style="color: var(--bpjs-green-700);"><x-icon name="checkCircle" :size="32" /></span>
            <h1 class="text-lg font-bold text-slate-900">{{ __('Workspace berhasil dibuat') }}</h1>
            <p class="text-sm text-slate-600">
                {{ __('Organisasi Anda telah disiapkan dengan slug:') }}
                <span class="mono font-semibold">{{ $workspaceSlug }}</span>.
            </p>
            <p class="text-sm text-slate-500">{{ __('Administrator akan mengonfigurasi domain Anda; setelah itu Anda dapat masuk sebagai pemilik.') }}</p>
            <a href="{{ route('login') }}" class="text-sm font-semibold text-bpjs-blue-600 hover:text-bpjs-blue-700">{{ __('Ke halaman masuk') }}</a>
        </div>
    @else
        <form wire:submit="register" class="card bpjs-rise">
            <div class="card--pad space-y-4">
                <h1 class="text-lg font-bold text-slate-900">{{ __('Daftarkan Organisasi') }}</h1>
                <x-bpjs.field :label="__('Nama Organisasi')" req for="orgName" :error="$errors->first('orgName')">
                    <input wire:model="orgName" type="text" id="orgName" class="input @error('orgName') input--err @enderror" />
                </x-bpjs.field>
                <x-bpjs.field :label="__('Nama Anda')" req for="name" :error="$errors->first('name')">
                    <input wire:model="name" type="text" id="name" class="input @error('name') input--err @enderror" />
                </x-bpjs.field>
                <x-bpjs.field label="Email" req for="email" :error="$errors->first('email')">
                    <input wire:model="email" type="email" id="email" class="input @error('email') input--err @enderror" />
                </x-bpjs.field>
                <x-bpjs.field :label="__('Kata Sandi')" req for="password" :error="$errors->first('password')">
                    <input wire:model="password" type="password" id="password" class="input @error('password') input--err @enderror" />
                </x-bpjs.field>
                <x-bpjs.field :label="__('Konfirmasi Kata Sandi')" req for="passwordConfirmation">
                    <input wire:model="passwordConfirmation" type="password" id="passwordConfirmation" class="input" />
                </x-bpjs.field>
            </div>
            <div class="modal__foot" style="border-radius: 0 0 16px 16px;">
                <x-bpjs.button type="submit" size="lg" block>{{ __('Buat Workspace') }}</x-bpjs.button>
            </div>
        </form>
    @endif
</div>
