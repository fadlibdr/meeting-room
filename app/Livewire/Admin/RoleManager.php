<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\PermissionCacheService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Configurable RBAC — manage roles and their permission matrix.
 *
 * Permissions are a code-defined catalog (they map to real gates); admins
 * configure which permissions each role holds, and may create/rename/delete
 * their own roles. System roles (is_system) cannot be deleted, and super_admin
 * is fully locked (always holds every permission — the recovery role).
 */
class RoleManager extends Component
{
    public bool $showForm = false;

    public bool $creating = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $code = '';

    public string $description = '';

    public string $scope = 'operational';

    /** @var array<int, int> */
    public array $permissionIds = [];

    public ?string $feedback = null;

    private const SUPER_ADMIN = 'super_admin';

    public function newRole(): void
    {
        $this->guard('roles.create');
        $this->resetForm();
        $this->creating = true;
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->guard('roles.update');
        $role = Role::with('permissions:id')->findOrFail($id);

        $this->editingId = $role->id;
        $this->creating = false;
        $this->name = $role->name;
        $this->code = $role->code;
        $this->description = $role->description ?? '';
        $this->scope = $role->scope;
        $this->permissionIds = $role->permissions->pluck('id')->map(fn ($id) => (int) $id)->all();
        $this->showForm = true;
    }

    public function togglePermission(int $permissionId): void
    {
        if (in_array($permissionId, $this->permissionIds, true)) {
            $this->permissionIds = array_values(array_filter($this->permissionIds, fn ($id) => $id !== $permissionId));
        } else {
            $this->permissionIds[] = $permissionId;
        }
    }

    /**
     * Toggle every permission in a module on/off at once (matrix convenience).
     */
    public function toggleModule(string $module): void
    {
        $ids = Permission::query()
            ->where('module', $module)
            ->where('is_active', true)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $allSelected = $ids !== [] && array_diff($ids, $this->permissionIds) === [];

        if ($allSelected) {
            $this->permissionIds = array_values(array_diff($this->permissionIds, $ids));
        } else {
            $this->permissionIds = array_values(array_unique([...$this->permissionIds, ...$ids]));
        }
    }

    public function save(): void
    {
        $this->guard($this->creating ? 'roles.create' : 'roles.update');

        $role = $this->creating ? null : Role::findOrFail($this->editingId);

        // super_admin is locked — never editable through the UI.
        abort_if($role !== null && $role->code === self::SUPER_ADMIN, 403);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:50', 'regex:/^[a-z][a-z0-9_]*$/', Rule::unique('roles', 'code')->ignore($role?->id)],
            'description' => ['nullable', 'string', 'max:255'],
            'scope' => ['required', 'string', 'in:strategic,operational,support'],
            'permissionIds' => ['array'],
            'permissionIds.*' => ['integer', 'exists:permissions,id'],
        ], [], ['code' => 'kode', 'name' => 'nama']);

        DB::transaction(function () use ($role, $validated): void {
            if ($role === null) {
                $role = Role::create([
                    'code' => $validated['code'],
                    'name' => $validated['name'],
                    'description' => $validated['description'] ?: null,
                    'scope' => $validated['scope'],
                    'is_system' => false,
                    'is_active' => true,
                ]);
            } else {
                // System roles keep their immutable code; only name/desc/scope/perms change.
                $role->update([
                    'code' => $role->is_system ? $role->code : $validated['code'],
                    'name' => $validated['name'],
                    'description' => $validated['description'] ?: null,
                    'scope' => $validated['scope'],
                ]);
            }

            $role->permissions()->sync($validated['permissionIds']);
            app(PermissionCacheService::class)->forgetByRole($role->id);
        });

        $this->feedback = __('Peran berhasil disimpan.');
        $this->closeForm();
    }

    public function delete(int $id): void
    {
        $this->guard('roles.delete');
        $role = Role::withCount('users')->findOrFail($id);

        abort_if($role->is_system, 403);

        if ($role->users_count > 0) {
            $this->feedback = __('Peran tidak dapat dihapus karena masih dipakai oleh :n pengguna.', ['n' => $role->users_count]);

            return;
        }

        $roleId = $role->id;
        $role->permissions()->detach();
        $role->delete();
        app(PermissionCacheService::class)->forgetByRole($roleId);

        $this->feedback = __('Peran dihapus.');
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'code', 'description', 'permissionIds', 'creating']);
        $this->scope = 'operational';
    }

    private function guard(string $permission): void
    {
        $user = auth()->user();
        abort_unless($user instanceof User && $user->hasPermission($permission), 403);
    }

    /**
     * @return EloquentCollection<int, Role>
     */
    private function roles(): EloquentCollection
    {
        return Role::query()
            ->withCount(['users', 'permissions'])
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->get();
    }

    public function render(): View
    {
        $permissions = Permission::query()
            ->where('is_active', true)
            ->orderBy('module')
            ->orderBy('action')
            ->get()
            ->groupBy('module');

        return view('livewire.admin.role-manager', [
            'roles' => $this->roles(),
            'permissionsByModule' => $permissions,
            'lockedCode' => self::SUPER_ADMIN,
        ])->layout('layouts.app', [
            'title' => __('Peran & Hak Akses'),
            'subtitle' => __('Kelola peran dan matriks izin'),
        ]);
    }
}
