<?php

namespace App\Livewire\Admin;

use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Component;

class UserForm extends Component
{
    public ?User $user = null;

    public string $name = '';

    public string $email = '';

    public ?int $unitId = null;

    /** @var array<int> */
    public array $roleIds = [];

    public bool $isActive = true;

    public ?string $generatedPassword = null;

    public function mount(?User $user = null): void
    {
        if ($user && $user->exists) {
            $this->user = $user;
            $this->name = $user->name;
            $this->email = $user->email;
            $this->unitId = $user->unit_id;
            $this->roleIds = $user->roles->pluck('id')->toArray();
            $this->isActive = $user->is_active;
        }
    }

    /**
     * @return array<string, array<string>|string>
     */
    protected function rules(): array
    {
        $userId = $this->user?->id;

        return [
            'name' => ['required', 'string', 'min:3', 'max:100'],
            'email' => [
                'required',
                'email',
                'ends_with:@bpjs-kesehatan.go.id',
                'unique:users,email'.($userId ? ','.$userId : ''),
            ],
            'unitId' => ['required', 'integer', 'exists:units,id'],
            'roleIds' => ['required', 'array', 'min:1'],
            'roleIds.*' => ['integer', 'exists:roles,id'],
            'isActive' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'name.required' => 'Nama wajib diisi.',
            'name.min' => 'Nama minimal 3 karakter.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.ends_with' => 'Email harus berdomain @bpjs-kesehatan.go.id.',
            'email.unique' => 'Email sudah terdaftar.',
            'unitId.required' => 'Unit wajib dipilih.',
            'unitId.exists' => 'Unit tidak ditemukan.',
            'roleIds.required' => 'Minimal pilih 1 role.',
            'roleIds.min' => 'Minimal pilih 1 role.',
        ];
    }

    public function save(): void
    {
        $validated = $this->validate();

        DB::transaction(function () use ($validated) {
            if ($this->user && $this->user->exists) {
                $this->updateExistingUser($validated);
            } else {
                $this->createNewUser($validated);
            }
        });

        if ($this->user && $this->user->exists) {
            session()->flash('status', 'Pengguna berhasil diperbarui.');
            $this->redirectRoute('admin.users.index', navigate: true);
        }
        // For create mode, stay on page to show generated password
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function createNewUser(array $validated): void
    {
        $plain = Str::random(12);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($plain),
            'unit_id' => $validated['unitId'],
            'is_active' => $validated['isActive'] ?? true,
            'failed_login_attempts' => 0,
        ]);

        $user->roles()->sync($validated['roleIds']);

        $this->user = $user;
        $this->generatedPassword = $plain;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function updateExistingUser(array $validated): void
    {
        $this->user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'unit_id' => $validated['unitId'],
            'is_active' => $validated['isActive'] ?? true,
        ]);

        $this->user->roles()->sync($validated['roleIds']);
    }

    public function render(): View
    {
        return view('livewire.admin.user-form', [
            'units' => Unit::orderBy('name')->get(),
            'roles' => Role::where('is_active', true)->orderBy('name')->get(),
            'isEditMode' => $this->user && $this->user->exists,
        ]);
    }
}
