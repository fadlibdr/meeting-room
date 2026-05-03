<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            // Foundation: roles, permissions, role-permission matrix
            RolesAndPermissionsSeeder::class,

            // Organizational structure
            UnitsSeeder::class,

            // Users (depends on units + roles)
            UsersSeeder::class,

            // System settings (used by services like BookingConflictService)
            AppSettingsSeeder::class,

            // Master data: facilities catalog
            FacilitiesSeeder::class,

            // Rooms (depends on facilities)
            RoomsSeeder::class,

            // Bookings (depends on rooms + users)
            BookingsSeeder::class,
        ]);
    }
}
