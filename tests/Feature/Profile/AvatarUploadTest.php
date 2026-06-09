<?php

declare(strict_types=1);

namespace Tests\Feature\Profile;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Tests\TestCase;

class AvatarUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_user_can_upload_an_avatar(): void
    {
        $user = User::factory()->create(['is_active' => true, 'avatar_path' => null]);

        Volt::actingAs($user)
            ->test('profile.update-profile-information-form')
            ->set('avatar', UploadedFile::fake()->image('me.jpg', 400, 400))
            ->call('updateProfileInformation')
            ->assertHasNoErrors();

        $user->refresh();
        $this->assertNotNull($user->avatar_path);
        Storage::disk('public')->assertExists($user->avatar_path);
        $this->assertStringContainsString('avatars/', $user->avatar_path);
        $this->assertNotNull($user->avatarUrl());
    }

    public function test_non_image_is_rejected(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        Volt::actingAs($user)
            ->test('profile.update-profile-information-form')
            ->set('avatar', UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf'))
            ->call('updateProfileInformation')
            ->assertHasErrors(['avatar']);

        $this->assertNull($user->fresh()->avatar_path);
    }

    public function test_replacing_avatar_deletes_old_file(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        Volt::actingAs($user)->test('profile.update-profile-information-form')
            ->set('avatar', UploadedFile::fake()->image('a.jpg'))
            ->call('updateProfileInformation');
        $first = $user->fresh()->avatar_path;
        Storage::disk('public')->assertExists($first);

        Volt::actingAs($user)->test('profile.update-profile-information-form')
            ->set('avatar', UploadedFile::fake()->image('b.jpg'))
            ->call('updateProfileInformation');
        $second = $user->fresh()->avatar_path;

        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertExists($second);
        Storage::disk('public')->assertMissing($first);
    }

    public function test_removing_avatar_clears_it(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        Volt::actingAs($user)->test('profile.update-profile-information-form')
            ->set('avatar', UploadedFile::fake()->image('a.jpg'))
            ->call('updateProfileInformation');
        $path = $user->fresh()->avatar_path;
        $this->assertNotNull($path);

        Volt::actingAs($user)->test('profile.update-profile-information-form')
            ->set('removeAvatar', true)
            ->call('updateProfileInformation')
            ->assertHasNoErrors();

        $this->assertNull($user->fresh()->avatar_path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_editing_name_keeps_existing_avatar(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        Volt::actingAs($user)->test('profile.update-profile-information-form')
            ->set('avatar', UploadedFile::fake()->image('a.jpg'))
            ->call('updateProfileInformation');
        $path = $user->fresh()->avatar_path;

        Volt::actingAs($user)->test('profile.update-profile-information-form')
            ->set('name', 'Nama Baru')
            ->call('updateProfileInformation')
            ->assertHasNoErrors();

        $this->assertSame($path, $user->fresh()->avatar_path);
        Storage::disk('public')->assertExists($path);
    }
}
