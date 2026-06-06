<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ApprovalDelegation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApprovalDelegation>
 */
class ApprovalDelegationFactory extends Factory
{
    protected $model = ApprovalDelegation::class;

    public function definition(): array
    {
        return [
            'from_user_id' => User::factory(),
            'to_user_id' => User::factory(),
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'reason' => null,
        ];
    }
}
