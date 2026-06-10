<?php

declare(strict_types=1);

namespace Tests\Feature\Profile;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class TelegramChatIdTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_save_a_telegram_chat_id(): void
    {
        $user = User::factory()->create(['is_active' => true, 'telegram_chat_id' => null]);

        Volt::actingAs($user)
            ->test('profile.update-profile-information-form')
            ->set('telegramChatId', '123456789')
            ->call('updateProfileInformation')
            ->assertHasNoErrors();

        $this->assertSame('123456789', $user->fresh()->telegram_chat_id);
    }

    public function test_blank_chat_id_clears_it(): void
    {
        $user = User::factory()->create(['is_active' => true, 'telegram_chat_id' => '999']);

        Volt::actingAs($user)
            ->test('profile.update-profile-information-form')
            ->set('telegramChatId', '')
            ->call('updateProfileInformation')
            ->assertHasNoErrors();

        $this->assertNull($user->fresh()->telegram_chat_id);
    }

    public function test_non_numeric_chat_id_is_rejected(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        Volt::actingAs($user)
            ->test('profile.update-profile-information-form')
            ->set('telegramChatId', 'not-a-number')
            ->call('updateProfileInformation')
            ->assertHasErrors(['telegramChatId']);
    }
}
