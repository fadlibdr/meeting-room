<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Livewire\Admin\WebhookSubscriptionManager;
use App\Models\Role;
use App\Models\User;
use App\Models\WebhookSubscription;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class WebhookSubscriptionManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function userWithRole(string $code): User
    {
        $user = User::factory()->create();
        $user->roles()->sync([Role::where('code', $code)->firstOrFail()->id]);

        return $user;
    }

    public function test_requester_cannot_access_but_admin_can(): void
    {
        $this->actingAs($this->userWithRole('requester'))
            ->get(route('admin.webhooks.index'))->assertForbidden();

        $this->actingAs($this->userWithRole('super_admin'))
            ->get(route('admin.webhooks.index'))->assertOk();
    }

    public function test_creates_a_webhook_and_shows_the_secret_once(): void
    {
        Livewire::actingAs($this->userWithRole('super_admin'))
            ->test(WebhookSubscriptionManager::class)
            ->call('newSubscription')
            ->set('name', 'Portal')
            ->set('url', 'https://portal.test/hook')
            ->set('events', ['booking.approved', 'booking.cancelled'])
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('plainSecret', fn ($v) => is_string($v) && strlen($v) >= 40);

        $this->assertDatabaseHas('webhook_subscriptions', ['name' => 'Portal', 'url' => 'https://portal.test/hook']);
    }

    public function test_requires_url_and_at_least_one_event(): void
    {
        Livewire::actingAs($this->userWithRole('super_admin'))
            ->test(WebhookSubscriptionManager::class)
            ->call('newSubscription')
            ->set('name', 'Bad')
            ->set('url', 'not-a-url')
            ->set('events', [])
            ->call('save')
            ->assertHasErrors(['url', 'events']);
    }

    public function test_toggle_and_delete(): void
    {
        $sub = WebhookSubscription::factory()->create(['is_active' => true]);

        $component = Livewire::actingAs($this->userWithRole('super_admin'))
            ->test(WebhookSubscriptionManager::class);

        $component->call('toggle', $sub->id);
        $this->assertFalse($sub->refresh()->is_active);

        $component->call('delete', $sub->id);
        $this->assertDatabaseMissing('webhook_subscriptions', ['id' => $sub->id]);
    }
}
