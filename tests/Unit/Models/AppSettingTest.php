<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\AppSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_string_cast_returns_string(): void
    {
        $setting = AppSetting::factory()->create([
            'key' => 'test_string_key',
            'value' => 'hello world',
            'data_type' => 'string',
            'label' => 'Test',
        ]);

        $this->assertSame('hello world', $setting->getCastedValue());
        $this->assertIsString($setting->getCastedValue());
    }

    public function test_integer_cast_returns_int(): void
    {
        $setting = AppSetting::factory()->integer(42)->create([
            'key' => 'test_int_key',
            'label' => 'Test Int',
        ]);

        $this->assertSame(42, $setting->getCastedValue());
        $this->assertIsInt($setting->getCastedValue());
    }

    public function test_integer_cast_returns_zero_for_zero_value(): void
    {
        $setting = AppSetting::factory()->integer(0)->create([
            'key' => 'test_int_zero',
            'label' => 'Test Int Zero',
        ]);

        $this->assertSame(0, $setting->getCastedValue());
        $this->assertIsInt($setting->getCastedValue());
    }

    public function test_boolean_true_cast_returns_bool_true(): void
    {
        $setting = AppSetting::factory()->boolean(true)->create([
            'key' => 'test_bool_true_key',
            'label' => 'Test Bool True',
        ]);

        $this->assertTrue($setting->getCastedValue());
        $this->assertIsBool($setting->getCastedValue());
    }

    public function test_boolean_false_cast_returns_bool_false(): void
    {
        $setting = AppSetting::factory()->boolean(false)->create([
            'key' => 'test_bool_false_key',
            'label' => 'Test Bool False',
        ]);

        $this->assertFalse($setting->getCastedValue());
        $this->assertIsBool($setting->getCastedValue());
    }

    public function test_json_cast_returns_array(): void
    {
        $data = ['enabled' => true, 'limit' => 10];
        $setting = AppSetting::factory()->create([
            'key' => 'test_json_key',
            'value' => json_encode($data),
            'data_type' => 'json',
            'label' => 'Test JSON',
        ]);

        $this->assertSame($data, $setting->getCastedValue());
        $this->assertIsArray($setting->getCastedValue());
    }

    public function test_null_value_returns_null_regardless_of_data_type(): void
    {
        $setting = AppSetting::factory()->create([
            'key' => 'test_null_key',
            'value' => null,
            'data_type' => 'integer',
            'label' => 'Test Null',
        ]);

        $this->assertNull($setting->getCastedValue());
    }

    public function test_unknown_data_type_returns_string(): void
    {
        $setting = AppSetting::factory()->create([
            'key' => 'test_unknown_key',
            'value' => 'some value',
            'data_type' => 'unknown_type',
            'label' => 'Test Unknown',
        ]);

        $this->assertSame('some value', $setting->getCastedValue());
    }
}
