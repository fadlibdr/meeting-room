<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ApprovalPolicy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApprovalPolicy>
 */
class ApprovalPolicyFactory extends Factory
{
    protected $model = ApprovalPolicy::class;

    public function definition(): array
    {
        return [
            'name' => 'Policy '.$this->faker->unique()->bothify('??-###'),
            'description' => $this->faker->optional()->sentence(),
            'is_active' => true,
        ];
    }
}
