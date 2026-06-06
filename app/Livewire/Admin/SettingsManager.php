<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Models\AppSetting;
use App\Services\SettingsService;
use Illuminate\Mail\Message;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Livewire\Component;

class SettingsManager extends Component
{
    public ?int $editingId = null;

    public string|int|bool|null $editValue = null;

    public ?string $successMessage = null;

    public ?string $errorMessage = null;

    /** Recipient for the "Kirim Email Uji" test-send. */
    public string $testEmailAddress = '';

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
        // Never load a stored secret into the form — encrypted fields start blank.
        $this->editValue = $setting->data_type === 'encrypted' ? '' : $setting->getCastedValue();
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

        // Write-only secret: a blank encrypted field keeps the stored value.
        if ($setting->data_type === 'encrypted' && ($this->editValue === null || $this->editValue === '')) {
            $this->successMessage = __("Pengaturan ':label' tidak diubah.", ['label' => $setting->label]);
            $this->editingId = null;
            $this->editValue = null;

            return;
        }

        try {
            $service->set($setting->key, $this->editValue, auth()->id());
            $this->successMessage = __("Pengaturan ':label' berhasil diperbarui.", ['label' => $setting->label]);
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
            'encrypted' => $value === null || is_string($value)
                ? null
                : 'Nilai harus berupa teks.',
            default => null,
        };
    }

    /**
     * Send a test email using the currently-persisted SMTP settings (already
     * layered onto config by MailSettingsServiceProvider at boot). Lets an
     * admin verify the transport before flipping the master email toggle on.
     */
    public function sendTestEmail(): void
    {
        $gate = AppSetting::query()->where('group', 'email')->first();
        if ($gate === null) {
            abort(404);
        }
        $this->authorize('update', $gate);

        $address = trim($this->testEmailAddress);
        if ($address === '' || ! filter_var($address, FILTER_VALIDATE_EMAIL)) {
            $this->successMessage = null;
            $this->errorMessage = __('Alamat email tujuan tidak valid.');

            return;
        }

        try {
            Mail::raw(
                'Ini adalah email uji dari Sistem Pemesanan Ruang Rapat BPJS Kesehatan. '
                .'Jika Anda menerima pesan ini, konfigurasi email sudah benar.',
                function (Message $message) use ($address): void {
                    $message->to($address)->subject('Email Uji — Meeting Room BPJS Kesehatan');
                },
            );
            $this->errorMessage = null;
            $this->successMessage = __('Email uji berhasil dikirim ke :address.', ['address' => $address]);
        } catch (\Throwable $e) {
            $this->successMessage = null;
            $this->errorMessage = __('Gagal mengirim email uji:').' '.$e->getMessage();
        }
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
        ])->layout('layouts.app', ['title' => __('Pengaturan'), 'subtitle' => __('Konfigurasi sistem reservasi')]);
    }
}
