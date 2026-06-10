<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Enums\NotificationType;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Services\NotificationPreferenceResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Per-user notification preferences. For each (type, channel) that the admin
 * marked user_overridable, the user may toggle whether they receive it. Locked
 * channels show the admin's value, read-only.
 */
class NotificationPreferences extends Component
{
    /**
     * state[typeValue][channel] = ['overridable' => bool, 'enabled' => bool]
     *
     * @var array<string, array<string, array<string, bool>>>
     */
    public array $state = [];

    public ?string $feedback = null;

    public function mount(NotificationPreferenceResolver $resolver): void
    {
        $this->loadState($resolver);
    }

    private function loadState(NotificationPreferenceResolver $resolver): void
    {
        /** @var User $user */
        $user = auth()->user();

        foreach (NotificationType::configurableCases() as $type) {
            foreach (NotificationPreferenceResolver::CHANNELS as $channel) {
                $default = $resolver->default($type, $channel);
                $this->state[$type->value][$channel] = [
                    'overridable' => $default['user_overridable'],
                    'enabled' => $resolver->enabled($user, $type, $channel),
                ];
            }
        }
    }

    public function save(): void
    {
        /** @var User $user */
        $user = auth()->user();

        DB::transaction(function () use ($user): void {
            foreach (NotificationType::configurableCases() as $type) {
                foreach (NotificationPreferenceResolver::CHANNELS as $channel) {
                    $cell = $this->state[$type->value][$channel] ?? null;
                    if ($cell === null || empty($cell['overridable'])) {
                        continue; // locked channels are not user-stored
                    }

                    NotificationPreference::updateOrCreate(
                        ['user_id' => $user->id, 'type' => $type->value, 'channel' => $channel],
                        ['enabled' => (bool) ($cell['enabled'] ?? false)],
                    );
                }
            }
        });

        $this->feedback = __('Preferensi notifikasi disimpan.');
    }

    public function resetToDefault(NotificationPreferenceResolver $resolver): void
    {
        /** @var User $user */
        $user = auth()->user();

        NotificationPreference::where('user_id', $user->id)->delete();
        $this->loadState($resolver);
        $this->feedback = __('Preferensi dikembalikan ke pengaturan default.');
    }

    public function render(): View
    {
        return view('livewire.notification-preferences', [
            'types' => NotificationType::configurableCases(),
            'channels' => NotificationPreferenceResolver::CHANNELS,
            'channelLabels' => NotificationPreferenceResolver::channelLabels(),
        ])->layout('layouts.app', [
            'title' => __('Preferensi Notifikasi'),
            'subtitle' => __('Pilih saluran yang Anda terima per jenis notifikasi'),
        ]);
    }
}
