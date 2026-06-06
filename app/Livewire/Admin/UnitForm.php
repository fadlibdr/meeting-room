<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\NotIn;
use Illuminate\View\View;
use Livewire\Component;

class UnitForm extends Component
{
    public ?Unit $unit = null;

    public bool $isEditMode = false;

    public string $code = '';

    public string $name = '';

    public ?int $parentId = null;

    public bool $isActive = true;

    public function mount(?Unit $unit = null): void
    {
        if ($unit && $unit->exists) {
            $this->isEditMode = true;
            $this->unit = $unit;
            $this->code = $unit->code;
            $this->name = $unit->name;
            $this->parentId = $unit->parent_id;
            $this->isActive = $unit->is_active;
        }
    }

    /**
     * @return array<string, string|array<int, string|NotIn>>
     */
    protected function rules(): array
    {
        $id = $this->unit?->id;

        // A unit may not be its own parent, nor a descendant of itself (cycle guard).
        $parentRule = ['nullable', 'integer', 'exists:units,id'];
        $forbidden = $this->forbiddenParentIds();
        if ($forbidden !== []) {
            $parentRule[] = Rule::notIn($forbidden);
        }

        return [
            'code' => ['required', 'string', 'max:30', 'unique:units,code'.($id ? ','.$id : '')],
            'name' => ['required', 'string', 'max:150'],
            'parentId' => $parentRule,
            'isActive' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'code.required' => __('Kode unit wajib diisi.'),
            'code.unique' => __('Kode unit sudah digunakan.'),
            'name.required' => __('Nama unit wajib diisi.'),
            'parentId.not_in' => __('Unit tidak dapat menjadi induk dari dirinya sendiri atau sub-unitnya.'),
            'parentId.exists' => __('Unit induk tidak ditemukan.'),
        ];
    }

    public function save(): void
    {
        $authUser = auth()->user();
        $permission = $this->isEditMode ? 'users.update' : 'users.create';

        if (! $authUser instanceof User || ! $authUser->hasPermission($permission)) {
            abort(403);
        }

        $validated = $this->validate();

        DB::transaction(function () use ($validated) {
            $payload = [
                'code' => $validated['code'],
                'name' => $validated['name'],
                'parent_id' => $validated['parentId'] ?? null,
                'is_active' => $validated['isActive'] ?? true,
            ];

            if ($this->isEditMode && $this->unit instanceof Unit) {
                $this->unit->update($payload);
            } else {
                Unit::create($payload);
            }
        });

        session()->flash('status', $this->isEditMode ? __('Unit berhasil diperbarui.') : __('Unit berhasil dibuat.'));
        $this->redirectRoute('admin.units.index', navigate: true);
    }

    public function render(): View
    {
        $forbidden = $this->forbiddenParentIds();

        return view('livewire.admin.unit-form', [
            'parents' => Unit::query()
                ->when($forbidden !== [], fn ($q) => $q->whereNotIn('id', $forbidden))
                ->orderBy('name')
                ->get(),
            'isEditMode' => $this->isEditMode,
        ]);
    }

    /**
     * The unit itself plus all of its descendants — none may be chosen as its
     * parent, to prevent a cycle in the org tree.
     *
     * @return array<int, int>
     */
    private function forbiddenParentIds(): array
    {
        if (! $this->unit?->id) {
            return [];
        }

        $all = Unit::query()->select('id', 'parent_id')->get();
        $ids = [$this->unit->id];

        $walk = function (int $parentId) use (&$walk, $all, &$ids): void {
            foreach ($all->where('parent_id', $parentId) as $child) {
                if (! in_array($child->id, $ids, true)) {
                    $ids[] = $child->id;
                    $walk($child->id);
                }
            }
        };
        $walk($this->unit->id);

        return $ids;
    }
}
