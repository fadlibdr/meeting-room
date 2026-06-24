<?php

declare(strict_types=1);

namespace Tests\Feature\Support;

use App\Livewire\Support\ContactForm;
use App\Models\User;
use App\Notifications\SupportRequestNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class ContactFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_support_page_requires_authentication(): void
    {
        $this->get(route('support'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_the_form(): void
    {
        $this->actingAs(User::factory()->create(['is_active' => true]));
        $this->get(route('support'))->assertOk()->assertSee('Kirim', false);
    }

    public function test_submitting_persists_a_request_and_emails_support(): void
    {
        Notification::fake();
        config(['support.to' => 'support@bpjs.test']);

        $user = User::factory()->create(['is_active' => true, 'name' => 'Budi', 'email' => 'budi@bpjs.test']);

        Livewire::actingAs($user)
            ->test(ContactForm::class)
            ->set('category', 'booking')
            ->set('subject', 'Tidak bisa memesan')
            ->set('message', 'Saya tidak dapat membuat reservasi untuk ruang A.')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('sent', true);

        $this->assertDatabaseHas('support_requests', [
            'user_id' => $user->id,
            'category' => 'booking',
            'subject' => 'Tidak bisa memesan',
            'status' => 'open',
        ]);

        Notification::assertSentOnDemand(
            SupportRequestNotification::class,
            fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'support@bpjs.test'
        );
    }

    public function test_message_is_required_and_min_length(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        Livewire::actingAs($user)
            ->test(ContactForm::class)
            ->set('category', 'bug')
            ->set('message', 'short')
            ->call('submit')
            ->assertHasErrors(['message']);

        $this->assertDatabaseCount('support_requests', 0);
    }

    public function test_invalid_category_is_rejected(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        Livewire::actingAs($user)
            ->test(ContactForm::class)
            ->set('category', 'nonsense')
            ->set('message', 'A valid length message for testing.')
            ->call('submit')
            ->assertHasErrors(['category']);
    }
}
