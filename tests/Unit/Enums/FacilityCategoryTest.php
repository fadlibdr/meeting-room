<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\FacilityCategory;
use Tests\TestCase;

class FacilityCategoryTest extends TestCase
{
    public function test_values_returns_all_backing_values(): void
    {
        $this->assertSame(
            ['av', 'furniture', 'connectivity', 'comfort'],
            FacilityCategory::values(),
        );
    }

    public function test_every_case_has_a_non_empty_label(): void
    {
        foreach (FacilityCategory::cases() as $case) {
            $this->assertNotSame('', $case->label());
        }
    }
}
