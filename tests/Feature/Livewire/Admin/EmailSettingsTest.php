<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire\Admin;

use App\Livewire\Admin\SettingsManager;
use App\Models\AppSetting;
use App\Models\Role;
use App\Models\User;
use App\Services\SettingsService;
use Database\Seeders\AppSettingsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class EmailSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(AppSettingsSeeder::class);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->roles()->sync([Role::where('code', 'super_admin')->firstOrFail()->id]);

        return $user;
    }

    private function passwordSettingId(): int
    {
        return (int) AppSetting::query()->where('key', 'email.password')->value('id');
    }

    public function test_password_field_is_write_only_and_never_emits_the_secret(): void
    {
        $secret = 'super-smtp-secret';
        app(SettingsService::class)->set('email.password', $secret);
        $cipher = (string) AppSetting::query()->where('key', 'email.password')->value('value');

        Livewire::actingAs($this->admin())
            ->test(SettingsManager::class)
            ->assertDontSee($secret)           // plain render: ciphertext shown as a "Tersimpan" pill, not the value
            ->assertDontSee($cipher)
            ->call('startEdit', $this->passwordSettingId())
            ->assertSet('editValue', '')        // never loads the stored secret into the form
            ->assertDontSee($secret);
    }

    public function test_saving_a_blank_password_keeps_the_stored_value(): void
    {
        app(SettingsService::class)->set('email.password', 'old-secret');

        Livewire::actingAs($this->admin())
            ->test(SettingsManager::class)
            ->call('startEdit', $this->passwordSettingId())
            ->set('editValue', '')
            ->call('save')
            ->assertSet('editingId', null);

        $this->assertSame('old-secret', app(SettingsService::class)->get('email.password'));
    }

    public function test_changing_the_password_encrypts_the_new_value(): void
    {
        Livewire::actingAs($this->admin())
            ->test(SettingsManager::class)
            ->call('startEdit', $this->passwordSettingId())
            ->set('editValue', 'new-secret')
            ->call('save')
            ->assertSet('editingId', null);

        $raw = AppSetting::query()->where('key', 'email.password')->value('value');
        $this->assertNotSame('new-secret', $raw);
        $this->assertSame('new-secret', app(SettingsService::class)->get('email.password'));
    }

    public function test_send_test_email_with_valid_address_reports_success(): void
    {
        Mail::fake();

        $component = Livewire::actingAs($this->admin())
            ->test(SettingsManager::class)
            ->set('testEmailAddress', 'tester@bpjs-kesehatan.go.id')
            ->call('sendTestEmail');

        $component->assertSet('errorMessage', null);
        $this->assertStringContainsString('berhasil', (string) $component->get('successMessage'));
    }

    public function test_send_test_email_rejects_an_invalid_address(): void
    {
        Mail::fake();

        Livewire::actingAs($this->admin())
            ->test(SettingsManager::class)
            ->set('testEmailAddress', 'not-an-email')
            ->call('sendTestEmail')
            ->assertSet('successMessage', null)
            ->assertSet('errorMessage', 'Alamat email tujuan tidak valid.');

        Mail::assertNothingSent();
    }
}
