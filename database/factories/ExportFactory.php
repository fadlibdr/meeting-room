<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ExportFormat;
use App\Enums\ExportStatus;
use App\Models\Export;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Export>
 */
class ExportFactory extends Factory
{
    protected $model = Export::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => 'bookings',
            'format' => ExportFormat::Xlsx,
            'status' => ExportStatus::Pending,
            'scope' => 'own',
            'filters' => [],
            'filename' => null,
            'path' => null,
            'row_count' => null,
            'error' => null,
            'completed_at' => null,
            'expires_at' => null,
        ];
    }

    public function completed(string $path = 'exports/test.xlsx'): static
    {
        return $this->state(fn () => [
            'status' => ExportStatus::Completed,
            'path' => $path,
            'filename' => 'bookings-export.xlsx',
            'row_count' => 3,
            'completed_at' => now(),
            'expires_at' => now()->addDay(),
        ]);
    }

    public function expired(): static
    {
        return $this->completed()->state(fn () => [
            'expires_at' => now()->subDay(),
        ]);
    }
}
