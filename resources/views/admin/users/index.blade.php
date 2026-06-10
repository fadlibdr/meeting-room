<x-app-layout :title="__('Pengguna')" :subtitle="__('Kelola akun, peran, dan akses pengguna.')">
    @hasPermission('users.view')
        <div class="mb-4 flex justify-end">
            <a href="{{ route('admin.export', 'users') }}" class="btn btn--ghost">{{ __('Ekspor CSV') }}</a>
        </div>
    @endhasPermission
    <livewire:admin.user-list />
</x-app-layout>
