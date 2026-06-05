<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Models\AppSetting;
use App\Services\SettingsService;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Component;

class SettingsManager extends Component
{
    public ?int $editingId = null;

    public string|int|bool|null $editValue = null;

    public ?string $successMessage = null;

    public ?string $errorMessage = null;

    public function mount(): void
    {
        $this->authorize('viewAny', AppSetting::class);
    }

    /**
     * Begin editing a specific setting.
     */
    public function startEdit(int $settingId): void
    {
        $setting = AppSetting::findOrFail($settingId);
        $this->authorize('update', $setting);

        $this->editingId = $settingId;
        $this->editValue = $setting->getCastedValue();
        $this->successMessage = null;
        $this->errorMessage = null;
    }

    /**
     * Cancel editing without saving.
     */
    public function cancelEdit(): void
    {
        $this->editingId = null;
        $this->editValue = null;
    }

    /**
     * Save the edited value.
     */
    public function save(SettingsService $service): void
    {
        if ($this->editingId === null) {
            return;
        }

        $setting = AppSetting::findOrFail($this->editingId);
        $this->authorize('update', $setting);

        // Type validation
        $error = $this->validateValueForType($this->editValue, $setting->data_type);
        if ($error !== null) {
            $this->errorMessage = $error;

            return;
        }

        try {
            $service->set($setting->key, $this->editValue, auth()->id());
            $this->successMessage = "Pengaturan '{$setting->label}' berhasil diperbarui.";
            $this->editingId = null;
            $this->editValue = null;
        } catch (\RuntimeException $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    /**
     * Type-specific validation before save.
     */
    private function validateValueForType(mixed $value, string $dataType): ?string
    {
        return match ($dataType) {
            'integer' => is_numeric($value) && (int) $value >= 0
                ? null
                : 'Nilai harus berupa angka non-negatif.',
            'boolean' => is_bool($value) || $value === '0' || $value === '1' || $value === 0 || $value === 1
                ? null
                : 'Nilai boolean harus true atau false.',
            'string' => is_string($value) && strlen($value) <= 1000
                ? null
                : 'Nilai harus berupa teks (maksimal 1000 karakter).',
            default => null,
        };
    }

    /**
     * Get all settings grouped by their group column.
     *
     * @return Collection<string, Collection<int, AppSetting>>
     */
    public function getGroupedSettings(SettingsService $service): Collection
    {
        return $service->getAll()
            ->groupBy('group')
            ->map(fn ($settings) => $settings->sortBy('label'));
    }

    public function render(SettingsService $service): View
    {
        return view('livewire.admin.settings-manager', [
            'groupedSettings' => $this->getGroupedSettings($service),
        ])->layout('layouts.app', ['title' => 'Pengaturan', 'subtitle' => 'Konfigurasi sistem reservasi']);
    }
}
