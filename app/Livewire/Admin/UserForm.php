<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use App\Support\EmailDomainPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\NotIn;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class UserForm extends Component
{
    #[Locked]
    public ?User $user = null;

    #[Locked]
    public bool $isEditMode = false;

    public string $name = '';

    public string $email = '';

    public ?int $unitId = null;

    public ?int $approverUserId = null;

    /** @var array<int> */
    public array $roleIds = [];

    public bool $isActive = true;

    public ?string $generatedPassword = null;

    /** Optional manual password when an admin resets a user's password (edit mode). */
    public string $newPassword = '';

    /** The password set by the last reset, shown once so the admin can relay it. */
    public ?string $resetResult = null;

    /**
     * Admin reset of a user's password (edit mode). Uses the typed password if
     * given, otherwise generates a strong random one and reveals it once.
     */
    public function resetPassword(): void
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User && $actor->hasPermission('users.update'), 403);
        abort_unless($this->isEditMode && $this->user instanceof User, 403);

        $plain = $this->newPassword !== ''
            ? $this->validate(['newPassword' => ['string', 'min:8', 'max:72']], [], ['newPassword' => 'kata sandi'])['newPassword']
            : Str::random(14);

        $this->user->forceFill(['password' => Hash::make($plain)])->save();

        $this->resetResult = $plain;
        $this->newPassword = '';
    }

    public function mount(?User $user = null): void
    {
        $actor = auth()->user();
        $isEdit = $user && $user->exists;

        // Livewire action methods are independently-reachable HTTP endpoints, so
        // route middleware does NOT protect this component — authorize here and
        // again in every mutating action below.
        abort_unless(
            $actor instanceof User && $actor->hasPermission($isEdit ? 'users.update' : 'users.create'),
            403,
        );

        if ($isEdit) {
            $this->isEditMode = true;
            $this->user = $user;
            $this->name = $user->name;
            $this->email = $user->email;
            $this->unitId = $user->unit_id;
            $this->approverUserId = $user->approver_user_id;
            $this->roleIds = $user->roles->pluck('id')->toArray();
            $this->isActive = $user->is_active;
        }
    }

    /**
     * @return array<string, string|array<int, string|NotIn>>
     */
    protected function rules(): array
    {
        $userId = $this->user?->id;

        // Optional approver; a user may not be their own approver.
        $approverRule = ['nullable', 'integer', 'exists:users,id'];
        if ($userId !== null) {
            $approverRule[] = Rule::notIn([$userId]);
        }

        return [
            'name' => ['required', 'string', 'min:3', 'max:100'],
            'email' => [
                'required',
                'email',
                ...app(EmailDomainPolicy::class)->rules(),
                'unique:users,email'.($userId ? ','.$userId : ''),
            ],
            'unitId' => ['required', 'integer', 'exists:units,id'],
            'approverUserId' => $approverRule,
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
            'name.required' => __('Nama wajib diisi.'),
            'name.min' => __('Nama minimal 3 karakter.'),
            'email.required' => __('Email wajib diisi.'),
            'email.email' => __('Format email tidak valid.'),
            'email.ends_with' => app(EmailDomainPolicy::class)->message(),
            'email.unique' => __('Email sudah terdaftar.'),
            'unitId.required' => __('Unit wajib dipilih.'),
            'unitId.exists' => __('Unit tidak ditemukan.'),
            'approverUserId.exists' => __('Approver tidak ditemukan.'),
            'approverUserId.not_in' => __('Pengguna tidak dapat menjadi approver bagi dirinya sendiri.'),
            'roleIds.required' => __('Minimal pilih 1 role.'),
            'roleIds.min' => __('Minimal pilih 1 role.'),
        ];
    }

    public function save(): void
    {
        $actor = auth()->user();
        abort_unless(
            $actor instanceof User && $actor->hasPermission($this->isEditMode ? 'users.update' : 'users.create'),
            403,
        );

        $validated = $this->validate();

        DB::transaction(function () use ($validated) {
            if ($this->isEditMode) {
                $this->updateExistingUser($validated);
            } else {
                $this->createNewUser($validated);
            }
        });

        if ($this->isEditMode) {
            session()->flash('status', __('Pengguna berhasil diperbarui.'));
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
            'approver_user_id' => $validated['approverUserId'] ?? null,
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
            'approver_user_id' => $validated['approverUserId'] ?? null,
            'is_active' => $validated['isActive'] ?? true,
        ]);

        $this->user->roles()->sync($validated['roleIds']);
    }

    public function render(): View
    {
        return view('livewire.admin.user-form', [
            'units' => Unit::orderBy('name')->get(),
            'roles' => Role::where('is_active', true)->orderBy('name')->get(),
            // Eligible approvers: active users who can actually approve
            // (unit_approver / ga_admin), excluding the user being edited.
            'approvers' => User::query()
                ->where('is_active', true)
                ->when($this->user?->id, fn ($q, $id) => $q->whereKeyNot($id))
                ->whereHas('roles', fn ($q) => $q->whereIn('code', ['unit_approver', 'ga_admin']))
                ->orderBy('name')
                ->get(),
            'isEditMode' => $this->isEditMode,
        ]);
    }
}
