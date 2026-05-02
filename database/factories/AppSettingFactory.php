<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AppSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AppSetting>
 */
class AppSettingFactory extends Factory
{
    protected $model = AppSetting::class;

    public function definition(): array
    {
        return [
            'key' => 'test.'.$this->faker->unique()->slug(2),
            'value' => $this->faker->word(),
            'data_type' => 'string',
            'label' => $this->faker->words(3, true),
            'description' => $this->faker->sentence(),
            'group' => 'general',
            'is_editable' => true,
            'updated_by_user_id' => null,
        ];
    }

    public function integer(int $value): self
    {
        return $this->state([
            'data_type' => 'integer',
            'value' => (string) $value,
        ]);
    }

    public function boolean(bool $value): self
    {
        return $this->state([
            'data_type' => 'boolean',
            'value' => $value ? '1' : '0',
        ]);
    }

    public function readOnly(): self
    {
        return $this->state(['is_editable' => false]);
    }
}
