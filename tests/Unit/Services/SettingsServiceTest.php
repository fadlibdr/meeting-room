<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\AppSetting;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SettingsServiceTest extends TestCase
{
    use RefreshDatabase;

    private SettingsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(SettingsService::class);
        Cache::flush();
    }

    public function test_get_returns_default_when_key_does_not_exist(): void
    {
        $result = $this->service->get('nonexistent_key', 'fallback');

        $this->assertSame('fallback', $result);
    }

    public function test_get_returns_null_when_key_does_not_exist_and_no_default(): void
    {
        $result = $this->service->get('nonexistent_key');

        $this->assertNull($result);
    }

    public function test_get_returns_db_value_when_setting_exists(): void
    {
        AppSetting::factory()->integer(42)->create([
            'key' => 'test_buffer_minutes',
            'label' => 'Test Buffer',
        ]);

        $result = $this->service->get('test_buffer_minutes');

        $this->assertSame(42, $result);
    }

    public function test_get_falls_back_to_config_when_no_db_row(): void
    {
        config(['meeting_room.default_buffer_minutes' => 15]);

        $result = $this->service->get('booking.default_buffer_minutes');

        $this->assertSame(15, $result);
    }

    public function test_get_prefers_db_over_config(): void
    {
        config(['meeting_room.default_buffer_minutes' => 15]);
        AppSetting::factory()->integer(30)->create([
            'key' => 'booking.default_buffer_minutes',
            'label' => 'Default Buffer',
        ]);

        $result = $this->service->get('booking.default_buffer_minutes');

        $this->assertSame(30, $result);
    }

    public function test_set_updates_existing_value(): void
    {
        AppSetting::factory()->integer(10)->create([
            'key' => 'test_buffer',
            'label' => 'Test Buffer',
        ]);

        $this->service->set('test_buffer', 25);

        $this->assertSame(25, $this->service->get('test_buffer'));
    }

    public function test_set_throws_when_key_does_not_exist(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Setting key 'nonexistent_set' does not exist");

        $this->service->set('nonexistent_set', 'value');
    }

    public function test_set_throws_when_setting_is_read_only(): void
    {
        AppSetting::factory()->readOnly()->create([
            'key' => 'test_readonly',
            'value' => 'locked',
            'data_type' => 'string',
            'label' => 'Read Only',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Setting 'test_readonly' is marked read-only");

        $this->service->set('test_readonly', 'should_fail');
    }

    public function test_set_records_updated_by_user_id(): void
    {
        $user = User::factory()->create();

        AppSetting::factory()->integer(0)->create([
            'key' => 'test_audit_buffer',
            'label' => 'Test Audit',
        ]);

        $this->service->set('test_audit_buffer', 20, $user->id);

        $setting = AppSetting::where('key', 'test_audit_buffer')->first();
        $this->assertSame($user->id, $setting->updated_by_user_id);
    }

    public function test_get_all_returns_all_settings_when_no_group(): void
    {
        AppSetting::factory()->create(['key' => 'a_setting', 'label' => 'A', 'group' => 'general']);
        AppSetting::factory()->create(['key' => 'b_setting', 'label' => 'B', 'group' => 'booking']);

        $result = $this->service->getAll();

        $this->assertCount(2, $result);
    }

    public function test_get_all_filters_by_group(): void
    {
        AppSetting::factory()->create(['key' => 'general_a', 'label' => 'A', 'group' => 'general']);
        AppSetting::factory()->create(['key' => 'booking_a', 'label' => 'B', 'group' => 'booking']);
        AppSetting::factory()->create(['key' => 'booking_b', 'label' => 'C', 'group' => 'booking']);

        $result = $this->service->getAll('booking');

        $this->assertCount(2, $result);
    }

    public function test_forget_removes_cached_value(): void
    {
        AppSetting::factory()->integer(50)->create([
            'key' => 'test_cached_buffer',
            'label' => 'Cached Buffer',
        ]);

        // Prime the cache
        $this->service->get('test_cached_buffer');

        // Modify DB directly (bypassing service)
        AppSetting::where('key', 'test_cached_buffer')->update(['value' => '99']);

        // Without forget, cache still returns old value
        $this->assertSame(50, $this->service->get('test_cached_buffer'));

        // After forget, fresh DB read
        $this->service->forget('test_cached_buffer');
        $this->assertSame(99, $this->service->get('test_cached_buffer'));
    }

    public function test_set_invalidates_cache(): void
    {
        AppSetting::factory()->integer(10)->create([
            'key' => 'test_cache_invalidation',
            'label' => 'Cache Test',
        ]);

        // Prime cache
        $this->service->get('test_cache_invalidation');

        // Set should invalidate
        $this->service->set('test_cache_invalidation', 99);

        $this->assertSame(99, $this->service->get('test_cache_invalidation'));
    }
}
