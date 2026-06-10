<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\AppSetting;
use App\Models\Role;
use App\Models\Room;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParameterExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function admin(): User
    {
        $u = User::factory()->create(['is_active' => true]);
        $u->roles()->sync([Role::where('code', 'super_admin')->firstOrFail()->id]);

        return $u;
    }

    private function csv(string $entity): string
    {
        $res = $this->actingAs($this->admin())->get(route('admin.export', $entity));
        $res->assertOk();
        $res->assertHeader('content-type', 'text/csv; charset=UTF-8');

        return $res->streamedContent();
    }

    public function test_users_export_contains_the_user(): void
    {
        $user = User::factory()->create(['name' => 'Budi Santoso', 'email' => 'budi@bpjs.test']);
        $this->assertStringContainsString('Budi Santoso', $this->csv('users'));
        $this->assertStringContainsString('budi@bpjs.test', $this->csv('users'));
    }

    public function test_rooms_export_contains_the_room(): void
    {
        Room::factory()->create(['name' => 'Ruang Garuda', 'code' => 'RM-G1']);
        $this->assertStringContainsString('Ruang Garuda', $this->csv('rooms'));
    }

    public function test_settings_export_masks_encrypted_values(): void
    {
        AppSetting::create([
            'key' => 'secret.thing', 'value' => encrypt('topsecret'),
            'data_type' => 'encrypted', 'label' => 'X', 'group' => 'sso', 'is_editable' => true,
        ]);

        $csv = $this->csv('settings');
        $this->assertStringContainsString('secret.thing', $csv);
        $this->assertStringNotContainsString('topsecret', $csv);
        $this->assertStringContainsString('••••••', $csv);
    }

    public function test_unknown_entity_404s(): void
    {
        $this->actingAs($this->admin())->get(route('admin.export', 'nonsense'))->assertNotFound();
    }

    public function test_export_requires_the_entity_permission(): void
    {
        $requester = User::factory()->create(['is_active' => true]);
        $requester->roles()->sync([Role::where('code', 'requester')->firstOrFail()->id]);

        $this->actingAs($requester)->get(route('admin.export', 'users'))->assertForbidden();
    }
}
