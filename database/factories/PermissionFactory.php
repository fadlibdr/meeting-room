<?php

namespace Database\Factories;

use App\Models\Permission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Permission>
 */
class PermissionFactory extends Factory
{
    protected $model = Permission::class;

    public function definition(): array
    {
        $module = $this->faker->randomElement(['bookings', 'rooms', 'users', 'reports']);
        $action = $this->faker->randomElement(['view', 'create', 'update', 'delete', 'approve']);
        $code = "{$module}.{$action}";

        return [
            'module' => $module,
            'action' => $action,
            'code' => $code.'.'.$this->faker->unique()->randomNumber(4),
            'name' => ucfirst($action).' '.ucfirst($module),
            'description' => "Allow user to {$action} {$module}",
            'is_active' => true,
        ];
    }
}
