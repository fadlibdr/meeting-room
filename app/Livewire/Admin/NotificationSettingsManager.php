<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Enums\NotificationType;
use App\Models\NotificationChannelDefault;
use App\Models\User;
use App\Services\NotificationPreferenceResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Admin matrix for the default channels of each notification type: per
 * (type, channel) an "enabled" default and whether users may override it.
 */
class NotificationSettingsManager extends Component
{
    /**
     * matrix[typeValue][channel] = ['enabled' => bool, 'overridable' => bool]
     *
     * @var array<string, array<string, array<string, bool>>>
     */
    public array $matrix = [];

    public ?string $feedback = null;

    public function mount(NotificationPreferenceResolver $resolver): void
    {
        foreach (NotificationType::configurableCases() as $type) {
            foreach (NotificationPreferenceResolver::CHANNELS as $channel) {
                $default = $resolver->default($type, $channel);
                $this->matrix[$type->value][$channel] = [
                    'enabled' => $default['enabled'],
                    'overridable' => $default['user_overridable'],
                ];
            }
        }
    }

    public function save(): void
    {
        $user = auth()->user();
        abort_unless($user instanceof User && $user->hasPermission('app-settings.update'), 403);

        DB::transaction(function (): void {
            foreach (NotificationType::configurableCases() as $type) {
                foreach (NotificationPreferenceResolver::CHANNELS as $channel) {
                    $cell = $this->matrix[$type->value][$channel] ?? ['enabled' => false, 'overridable' => true];
                    NotificationChannelDefault::updateOrCreate(
                        ['type' => $type->value, 'channel' => $channel],
                        ['enabled' => (bool) ($cell['enabled'] ?? false), 'user_overridable' => (bool) ($cell['overridable'] ?? false)],
                    );
                }
            }
        });

        $this->feedback = __('Pengaturan notifikasi disimpan.');
    }

    public function render(): View
    {
        return view('livewire.admin.notification-settings-manager', [
            'types' => NotificationType::configurableCases(),
            'channels' => NotificationPreferenceResolver::CHANNELS,
            'channelLabels' => NotificationPreferenceResolver::channelLabels(),
        ])->layout('layouts.app', [
            'title' => __('Pengaturan Notifikasi'),
            'subtitle' => __('Saluran default per jenis notifikasi'),
        ]);
    }
}
