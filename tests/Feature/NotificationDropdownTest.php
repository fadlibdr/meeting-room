<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\NotificationDropdown;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class NotificationDropdownTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function makeNotification(User $user, string $message, bool $read = false, string $url = '/bookings/1'): DatabaseNotification
    {
        /** @var DatabaseNotification $notification */
        $notification = $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\BookingApprovedNotification',
            'data' => [
                'type' => 'booking_approved',
                'message' => $message,
                'url' => $url,
            ],
            'read_at' => $read ? now() : null,
        ]);

        return $notification;
    }

    public function test_shows_unread_notifications_for_the_user(): void
    {
        $user = User::factory()->create();
        $this->makeNotification($user, 'Reservasi RPL-001 telah disetujui.');

        Livewire::actingAs($user)
            ->test(NotificationDropdown::class)
            ->assertSee('Reservasi RPL-001 telah disetujui.');
    }

    public function test_mark_as_read_marks_a_single_notification(): void
    {
        $user = User::factory()->create();
        $notification = $this->makeNotification($user, 'Test message');

        $this->assertSame(1, $user->unreadNotifications()->count());

        Livewire::actingAs($user)
            ->test(NotificationDropdown::class)
            ->call('markAsRead', $notification->id);

        $this->assertSame(0, $user->unreadNotifications()->count());
    }

    public function test_mark_as_read_redirects_to_the_booking_url(): void
    {
        $user = User::factory()->create();
        $notification = $this->makeNotification($user, 'Test', url: '/bookings/42');

        Livewire::actingAs($user)
            ->test(NotificationDropdown::class)
            ->call('markAsRead', $notification->id)
            ->assertRedirect('/bookings/42');
    }

    public function test_mark_all_as_read_clears_unread(): void
    {
        $user = User::factory()->create();
        $this->makeNotification($user, 'A');
        $this->makeNotification($user, 'B');
        $this->makeNotification($user, 'C');

        $this->assertSame(3, $user->unreadNotifications()->count());

        Livewire::actingAs($user)
            ->test(NotificationDropdown::class)
            ->call('markAllAsRead');

        $this->assertSame(0, $user->unreadNotifications()->count());
    }

    public function test_only_shows_the_current_users_notifications(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $this->makeNotification($alice, 'Notifikasi milik Alice');
        $this->makeNotification($bob, 'Notifikasi milik Bob');

        Livewire::actingAs($alice)
            ->test(NotificationDropdown::class)
            ->assertSee('Notifikasi milik Alice')
            ->assertDontSee('Notifikasi milik Bob');
    }
}
