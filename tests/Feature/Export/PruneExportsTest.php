<?php

declare(strict_types=1);

namespace Tests\Feature\Export;

use App\Models\Export;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PruneExportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_prune_deletes_expired_files_and_rows_but_keeps_fresh(): void
    {
        Storage::fake(Export::DISK);
        $user = User::factory()->create();

        Storage::disk(Export::DISK)->put('exports/expired.xlsx', 'x');
        $expired = Export::factory()->expired()->create([
            'user_id' => $user->id,
            'path' => 'exports/expired.xlsx',
        ]);

        Storage::disk(Export::DISK)->put('exports/fresh.xlsx', 'y');
        $fresh = Export::factory()->completed('exports/fresh.xlsx')->create([
            'user_id' => $user->id,
        ]);

        $this->artisan('exports:prune')->assertSuccessful();

        $this->assertDatabaseMissing('exports', ['id' => $expired->id]);
        Storage::disk(Export::DISK)->assertMissing('exports/expired.xlsx');

        $this->assertDatabaseHas('exports', ['id' => $fresh->id]);
        Storage::disk(Export::DISK)->assertExists('exports/fresh.xlsx');
    }
}
