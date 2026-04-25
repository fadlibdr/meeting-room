<?php

namespace Database\Seeders;

use App\Models\Unit;
use Illuminate\Database\Seeder;

class UnitsSeeder extends Seeder
{
    public function run(): void
    {
        // Parent units (top-level direktorat)
        $sdmUmum = Unit::firstOrCreate(
            ['code' => 'DIR-SDM-UMUM'],
            [
                'name' => 'Direktorat SDM dan Umum',
                'parent_id' => null,
                'is_active' => true,
            ]
        );

        $teknologi = Unit::firstOrCreate(
            ['code' => 'DIR-IT'],
            [
                'name' => 'Direktorat Teknologi Informasi',
                'parent_id' => null,
                'is_active' => true,
            ]
        );

        $kepesertaan = Unit::firstOrCreate(
            ['code' => 'DIR-KEPESERTAAN'],
            [
                'name' => 'Direktorat Kepesertaan',
                'parent_id' => null,
                'is_active' => true,
            ]
        );

        // Child units (biro under direktorat)
        Unit::firstOrCreate(
            ['code' => 'BIRO-UMUM'],
            [
                'name' => 'Biro Umum',
                'parent_id' => $sdmUmum->id,
                'is_active' => true,
            ]
        );

        Unit::firstOrCreate(
            ['code' => 'BIRO-PENGEMBANGAN-IT'],
            [
                'name' => 'Biro Pengembangan Sistem Informasi',
                'parent_id' => $teknologi->id,
                'is_active' => true,
            ]
        );
    }
}
