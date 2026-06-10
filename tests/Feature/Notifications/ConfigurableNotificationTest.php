<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Enums\NotificationType;
use App\Livewire\Admin\NotificationSettingsManager;
use App\Livewire\NotificationPreferences;
use App\Models\Booking;
use App\Models\NotificationChannelDefault;
use App\Models\NotificationPreference;
use App\Models\Role;
use App\Models\Room;
use App\Models\User;
use App\Notifications\BookingApprovedNotification;
use App\Notifications\Channels\TelegramChannel;
use App\Services\NotificationPreferenceResolver;
use App\Services\SettingsService;
use Database\Seeders\AppSettingsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ConfigurableNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(AppSettingsSeeder::class);
    }

    private function booking(): Booking
    {
        return Booking::factory()->approved()->create(['resource_id' => Room::factory()->create()->id]);
    }

    private function resolver(): NotificationPreferenceResolver
    {
        return app(NotificationPreferenceResolver::class);
    }

    // ── Resolver ───────────────────────────────────────────────────────────

    public function test_in_app_is_on_by_default_and_telegram_off(): void
    {
        $user = User::factory()->create();

        $this->assertTrue($this->resolver()->enabled($user, NotificationType::BookingApproved, 'database'));
        $this->assertFalse($this->resolver()->enabled($user, NotificationType::BookingApproved, 'telegram'));
    }

    public function test_admin_default_disables_a_channel_for_everyone(): void
    {
        NotificationChannelDefault::create([
            'type' => NotificationType::BookingApproved->value, 'channel' => 'database',
            'enabled' => false, 'user_overridable' => false,
        ]);
        $user = User::factory()->create();

        $this->assertFalse($this->resolver()->enabled($user, NotificationType::BookingApproved, 'database'));
    }

    public function test_user_override_applies_only_when_overridable(): void
    {
        // telegram default: enabled by admin, overridable
        NotificationChannelDefault::create([
            'type' => NotificationType::BookingApproved->value, 'channel' => 'telegram',
            'enabled' => true, 'user_overridable' => true,
        ]);
        $user = User::factory()->create();

        NotificationPreference::create([
            'user_id' => $user->id, 'type' => NotificationType::BookingApproved->value,
            'channel' => 'telegram', 'enabled' => false,
        ]);

        $this->assertFalse($this->resolver()->enabled($user, NotificationType::BookingApproved, 'telegram'));
    }

    public function test_user_override_ignored_when_not_overridable(): void
    {
        NotificationChannelDefault::create([
            'type' => NotificationType::BookingApproved->value, 'channel' => 'mail',
            'enabled' => true, 'user_overridable' => false,
        ]);
        $user = User::factory()->create();
        NotificationPreference::create([
            'user_id' => $user->id, 'type' => NotificationType::BookingApproved->value,
            'channel' => 'mail', 'enabled' => false,
        ]);

        // Admin locked it on — the user's "off" is ignored.
        $this->assertTrue($this->resolver()->enabled($user, NotificationType::BookingApproved, 'mail'));
    }

    public function test_via_reflects_the_matrix(): void
    {
        app(SettingsService::class)->set('telegram.enabled', true);
        // Disable in-app, enable telegram for BookingApproved.
        NotificationChannelDefault::create(['type' => 'booking_approved', 'channel' => 'database', 'enabled' => false, 'user_overridable' => false]);
        NotificationChannelDefault::create(['type' => 'booking_approved', 'channel' => 'telegram', 'enabled' => true, 'user_overridable' => true]);

        $user = User::factory()->create(['telegram_chat_id' => '123', 'email_notifications' => false]);

        $channels = (new BookingApprovedNotification($this->booking()))->via($user);

        $this->assertNotContains('database', $channels);
        $this->assertContains(TelegramChannel::class, $channels);
    }

    // ── Admin UI ───────────────────────────────────────────────────────────

    public function test_admin_can_save_the_channel_matrix(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->roles()->sync([Role::where('code', 'super_admin')->firstOrFail()->id]);

        Livewire::actingAs($admin)
            ->test(NotificationSettingsManager::class)
            ->set('matrix.booking_approved.telegram.enabled', true)
            ->set('matrix.booking_approved.telegram.overridable', false)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('notification_channel_defaults', [
            'type' => 'booking_approved', 'channel' => 'telegram', 'enabled' => 1, 'user_overridable' => 0,
        ]);
    }

    public function test_admin_matrix_requires_permission(): void
    {
        $requester = User::factory()->create(['is_active' => true]);
        $requester->roles()->sync([Role::where('code', 'requester')->firstOrFail()->id]);

        Livewire::actingAs($requester)
            ->test(NotificationSettingsManager::class)
            ->call('save')
            ->assertForbidden();
    }

    // ── User UI ──────────────────────────────────────────────────────────────

    public function test_user_can_save_an_override_for_an_overridable_channel(): void
    {
        NotificationChannelDefault::create(['type' => 'booking_approved', 'channel' => 'mail', 'enabled' => true, 'user_overridable' => true]);
        $user = User::factory()->create(['is_active' => true]);

        Livewire::actingAs($user)
            ->test(NotificationPreferences::class)
            ->set('state.booking_approved.mail.enabled', false)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('notification_preferences', [
            'user_id' => $user->id, 'type' => 'booking_approved', 'channel' => 'mail', 'enabled' => 0,
        ]);
    }

    public function test_reset_clears_user_overrides(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        NotificationPreference::create(['user_id' => $user->id, 'type' => 'booking_approved', 'channel' => 'mail', 'enabled' => false]);

        Livewire::actingAs($user)
            ->test(NotificationPreferences::class)
            ->call('resetToDefault')
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('notification_preferences', ['user_id' => $user->id]);
    }
}
