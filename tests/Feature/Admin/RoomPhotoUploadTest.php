<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Livewire\Admin\RoomForm;
use App\Models\Role;
use App\Models\Room;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class RoomPhotoUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('public');
    }

    private function admin(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->sync([Role::where('code', 'ga_admin')->firstOrFail()->id]);

        return $user;
    }

    public function test_a_photo_can_be_uploaded_when_creating_a_room(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(RoomForm::class)
            ->set('code', 'RM-PHOTO')
            ->set('name', 'Ruang Foto')
            ->set('capacity', 8)
            ->set('photo', UploadedFile::fake()->image('room.jpg', 800, 600))
            ->call('save')
            ->assertHasNoErrors();

        $room = Room::where('code', 'RM-PHOTO')->firstOrFail();
        $this->assertNotNull($room->photo_path);
        Storage::disk('public')->assertExists($room->photo_path);
        $this->assertStringContainsString('room-photos/', $room->photo_path);
    }

    public function test_non_image_files_are_rejected(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(RoomForm::class)
            ->set('code', 'RM-BAD')
            ->set('name', 'Ruang')
            ->set('capacity', 4)
            ->set('photo', UploadedFile::fake()->create('notes.pdf', 200, 'application/pdf'))
            ->call('save')
            ->assertHasErrors(['photo']);

        $this->assertDatabaseMissing('resources', ['code' => 'RM-BAD']);
    }

    public function test_replacing_a_photo_deletes_the_old_file(): void
    {
        $this->actingAs($this->admin());
        $room = Room::factory()->create(['photo_path' => null]);

        // First upload.
        Livewire::test(RoomForm::class, ['room' => $room])
            ->set('photo', UploadedFile::fake()->image('first.jpg'))
            ->call('save')
            ->assertHasNoErrors();

        $firstPath = $room->fresh()->photo_path;
        Storage::disk('public')->assertExists($firstPath);

        // Replace it.
        Livewire::test(RoomForm::class, ['room' => $room->fresh()])
            ->set('photo', UploadedFile::fake()->image('second.jpg'))
            ->call('save')
            ->assertHasNoErrors();

        $secondPath = $room->fresh()->photo_path;
        $this->assertNotSame($firstPath, $secondPath);
        Storage::disk('public')->assertExists($secondPath);
        Storage::disk('public')->assertMissing($firstPath); // old file cleaned up
    }

    public function test_removing_a_photo_clears_it(): void
    {
        $this->actingAs($this->admin());
        $room = Room::factory()->create();

        Livewire::test(RoomForm::class, ['room' => $room])
            ->set('photo', UploadedFile::fake()->image('p.jpg'))
            ->call('save');

        $path = $room->fresh()->photo_path;
        $this->assertNotNull($path);

        Livewire::test(RoomForm::class, ['room' => $room->fresh()])
            ->set('removePhoto', true)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertNull($room->fresh()->photo_path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_saving_without_touching_photo_keeps_the_existing_one(): void
    {
        $this->actingAs($this->admin());
        $room = Room::factory()->create();

        Livewire::test(RoomForm::class, ['room' => $room])
            ->set('photo', UploadedFile::fake()->image('p.jpg'))
            ->call('save');
        $path = $room->fresh()->photo_path;

        // Edit again, change only the name — photo must persist.
        Livewire::test(RoomForm::class, ['room' => $room->fresh()])
            ->set('name', 'Nama Baru')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame($path, $room->fresh()->photo_path);
        Storage::disk('public')->assertExists($path);
    }
}
