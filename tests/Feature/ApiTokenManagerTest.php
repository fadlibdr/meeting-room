<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\ApiTokenManager;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ApiTokenManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_a_token_and_sees_it_once(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(ApiTokenManager::class)
            ->set('name', 'Portal')
            ->set('abilities', ['read', 'booking:write'])
            ->call('createToken')
            ->assertHasNoErrors()
            ->assertSet('plainTextToken', fn ($v) => is_string($v) && $v !== '');

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'Portal',
        ]);
    }

    public function test_token_requires_at_least_one_ability(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(ApiTokenManager::class)
            ->set('name', 'Kosong')
            ->set('abilities', [])
            ->call('createToken')
            ->assertHasErrors('abilities');
    }

    public function test_user_can_revoke_a_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('Lama', ['read']);
        $id = $token->accessToken->getKey();

        Livewire::actingAs($user)
            ->test(ApiTokenManager::class)
            ->call('revoke', $id);

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $id]);
    }

    public function test_a_user_cannot_revoke_another_users_token(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $token = $owner->createToken('Milik Owner', ['read']);
        $id = $token->accessToken->getKey();

        Livewire::actingAs($other)
            ->test(ApiTokenManager::class)
            ->call('revoke', $id);

        $this->assertDatabaseHas('personal_access_tokens', ['id' => $id]);
    }
}
