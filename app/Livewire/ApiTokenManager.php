<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Enums\ApiAbility;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Stage 3 C — per-user API token management.
 *
 * Any authenticated user manages their own Sanctum personal access tokens. A
 * token's abilities never exceed its owner: a `booking:write` token can only
 * create bookings the user is already permitted to (the API authorises via the
 * same policies). The plaintext token is shown exactly once after creation.
 */
class ApiTokenManager extends Component
{
    public string $name = '';

    /** @var list<string> */
    public array $abilities = ['read'];

    public ?string $plainTextToken = null;

    public function createToken(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:80'],
            'abilities' => ['required', 'array', 'min:1'],
            'abilities.*' => [Rule::in(ApiAbility::values())],
        ]);

        /** @var User $user */
        $user = auth()->user();

        $token = $user->createToken($validated['name'], $validated['abilities']);
        $this->plainTextToken = $token->plainTextToken;

        $this->reset('name');
        $this->abilities = ['read'];
    }

    public function revoke(int $tokenId): void
    {
        /** @var User $user */
        $user = auth()->user();
        $user->tokens()->whereKey($tokenId)->delete();
    }

    public function dismissToken(): void
    {
        $this->plainTextToken = null;
    }

    public function render(): View
    {
        /** @var User $user */
        $user = auth()->user();

        return view('livewire.api-token-manager', [
            'tokens' => $user->tokens()->latest()->get(),
            'allAbilities' => ApiAbility::cases(),
        ])->layout('layouts.app', [
            'title' => __('Token API'),
            'subtitle' => __('Kelola token akses API pribadi Anda'),
        ]);
    }
}
