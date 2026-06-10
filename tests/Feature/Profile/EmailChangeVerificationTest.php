<?php

declare(strict_types=1);

namespace Tests\Feature\Profile;

use App\Models\User;
use App\Notifications\EmailChangeVerificationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Livewire\Volt\Volt;
use Tests\TestCase;

class EmailChangeVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_changing_email_stages_it_and_sends_a_link_without_changing_the_account_email(): void
    {
        Notification::fake();
        $user = User::factory()->create(['is_active' => true, 'email' => 'old@bpjs.test']);

        Volt::actingAs($user)
            ->test('profile.update-profile-information-form')
            ->set('email', 'new@bpjs.test')
            ->call('updateProfileInformation')
            ->assertHasNoErrors()
            ->assertSet('pendingEmail', 'new@bpjs.test');

        $user->refresh();
        $this->assertSame('old@bpjs.test', $user->email);        // unchanged
        $this->assertSame('new@bpjs.test', $user->pending_email); // staged
        $this->assertNotNull($user->pending_email_token);

        Notification::assertSentOnDemand(
            EmailChangeVerificationNotification::class,
            fn ($n, $channels, $notifiable) => $notifiable->routes['mail'] === 'new@bpjs.test',
        );
    }

    public function test_confirming_applies_the_new_email(): void
    {
        $user = User::factory()->create(['email' => 'old@bpjs.test']);
        $user->forceFill(['pending_email' => 'new@bpjs.test', 'pending_email_token' => 'tok'])->save();

        $url = URL::signedRoute('email.change.verify', ['token' => 'tok']);
        $this->get($url)->assertRedirect();

        $user->refresh();
        $this->assertSame('new@bpjs.test', $user->email);
        $this->assertNull($user->pending_email);
        $this->assertNull($user->pending_email_token);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_unsigned_or_bad_token_is_rejected(): void
    {
        // Unsigned → 403 from the signed middleware.
        $this->get('/email/change/verify/whatever')->assertForbidden();

        // Signed but unknown token → graceful redirect, no change.
        $user = User::factory()->create(['email' => 'old@bpjs.test']);
        $user->forceFill(['pending_email' => 'new@bpjs.test', 'pending_email_token' => 'realtok'])->save();

        $this->get(URL::signedRoute('email.change.verify', ['token' => 'wrongtok']))->assertRedirect();
        $this->assertSame('old@bpjs.test', $user->fresh()->email);
    }

    public function test_changing_only_name_does_not_stage_an_email(): void
    {
        Notification::fake();
        $user = User::factory()->create(['is_active' => true, 'email' => 'me@bpjs.test']);

        Volt::actingAs($user)
            ->test('profile.update-profile-information-form')
            ->set('name', 'New Name')
            ->call('updateProfileInformation')
            ->assertHasNoErrors()
            ->assertSet('pendingEmail', null);

        $this->assertNull($user->fresh()->pending_email);
        Notification::assertNothingSent();
    }
}
