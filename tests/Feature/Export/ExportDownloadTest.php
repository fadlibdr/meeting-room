<?php

declare(strict_types=1);

namespace Tests\Feature\Export;

use App\Models\Export;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExportDownloadTest extends TestCase
{
    use RefreshDatabase;

    private function completedExportFor(User $user): Export
    {
        Storage::disk(Export::DISK)->put('exports/sample.xlsx', 'binary-contents');

        return Export::factory()->completed('exports/sample.xlsx')->create([
            'user_id' => $user->id,
        ]);
    }

    public function test_owner_can_download_completed_export(): void
    {
        Storage::fake(Export::DISK);
        $user = User::factory()->create();
        $export = $this->completedExportFor($user);

        $this->actingAs($user)
            ->get(route('exports.download', $export))
            ->assertOk()
            ->assertDownload($export->filename);
    }

    public function test_non_owner_gets_404(): void
    {
        Storage::fake(Export::DISK);
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $export = $this->completedExportFor($owner);

        $this->actingAs($stranger)
            ->get(route('exports.download', $export))
            ->assertNotFound();
    }

    public function test_pending_export_is_not_downloadable(): void
    {
        Storage::fake(Export::DISK);
        $user = User::factory()->create();
        $export = Export::factory()->create(['user_id' => $user->id]); // pending, no file

        $this->actingAs($user)
            ->get(route('exports.download', $export))
            ->assertNotFound();
    }

    public function test_expired_export_is_not_downloadable(): void
    {
        Storage::fake(Export::DISK);
        $user = User::factory()->create();
        Storage::disk(Export::DISK)->put('exports/old.xlsx', 'x');
        $export = Export::factory()->expired()->create([
            'user_id' => $user->id,
            'path' => 'exports/old.xlsx',
        ]);

        $this->actingAs($user)
            ->get(route('exports.download', $export))
            ->assertNotFound();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        Storage::fake(Export::DISK);
        $user = User::factory()->create();
        $export = $this->completedExportFor($user);

        $this->get(route('exports.download', $export))
            ->assertRedirect(route('login'));
    }
}
