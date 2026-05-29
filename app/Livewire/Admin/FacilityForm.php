<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Models\RoomFacility;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;

class FacilityForm extends Component
{
    /** @var array<int, string> */
    public const CATEGORIES = ['av', 'furniture', 'connectivity', 'comfort'];

    public ?RoomFacility $facility = null;

    public bool $isEditMode = false;

    public string $code = '';

    public string $name = '';

    public string $category = '';

    public string $icon = '';

    public bool $isActive = true;

    public function mount(?RoomFacility $facility = null): void
    {
        if ($facility && $facility->exists) {
            $this->isEditMode = true;
            $this->facility = $facility;
            $this->code = $facility->code;
            $this->name = $facility->name;
            $this->category = $facility->category ?? '';
            $this->icon = $facility->icon ?? '';
            $this->isActive = $facility->is_active;
        }
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    protected function rules(): array
    {
        $id = $this->facility?->id;

        return [
            'code' => ['required', 'string', 'max:50', 'unique:room_facilities,code'.($id ? ','.$id : '')],
            'name' => ['required', 'string', 'max:100'],
            'category' => ['nullable', Rule::in(self::CATEGORIES)],
            'icon' => ['nullable', 'string', 'max:50'],
            'isActive' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'code.required' => 'Kode fasilitas wajib diisi.',
            'code.unique' => 'Kode fasilitas sudah digunakan.',
            'name.required' => 'Nama fasilitas wajib diisi.',
            'category.in' => 'Kategori tidak valid.',
        ];
    }

    public function save(): void
    {
        $authUser = auth()->user();
        $permission = $this->isEditMode ? 'rooms.update' : 'rooms.create';

        if (! $authUser instanceof User || ! $authUser->hasPermission($permission)) {
            abort(403);
        }

        $validated = $this->validate();

        DB::transaction(function () use ($validated) {
            $payload = [
                'code' => $validated['code'],
                'name' => $validated['name'],
                'category' => $validated['category'] ?: null,
                'icon' => $validated['icon'] ?: null,
                'is_active' => $validated['isActive'] ?? true,
            ];

            if ($this->isEditMode && $this->facility instanceof RoomFacility) {
                $this->facility->update($payload);
            } else {
                RoomFacility::create($payload);
            }
        });

        session()->flash('status', $this->isEditMode ? 'Fasilitas berhasil diperbarui.' : 'Fasilitas berhasil dibuat.');
        $this->redirectRoute('admin.facilities.index', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.admin.facility-form', [
            'categories' => self::CATEGORIES,
            'isEditMode' => $this->isEditMode,
        ]);
    }
}
