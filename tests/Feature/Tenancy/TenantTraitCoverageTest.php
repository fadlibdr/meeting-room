<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Models\ActivityLog;
use App\Models\ApprovalDelegation;
use App\Models\ApprovalPolicy;
use App\Models\ApprovalPolicyStep;
use App\Models\AppSetting;
use App\Models\Booking;
use App\Models\BookingApproval;
use App\Models\BookingAttachment;
use App\Models\BookingCalendarEvent;
use App\Models\BookingStatusHistory;
use App\Models\CalendarConnection;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Export;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\RoomBlockSchedule;
use App\Models\RoomFacility;
use App\Models\RoomFacilityItem;
use App\Models\RoomOperatingHour;
use App\Models\Unit;
use App\Models\User;
use App\Models\UserRole;
use App\Models\WebhookDelivery;
use App\Models\WebhookSubscription;
use Tests\TestCase;

/**
 * Stage 4a P2a — every tenant-owned model must carry BelongsToTenant, or it is
 * a cross-tenant leak waiting to happen. This guards the rollout's completeness.
 */
class TenantTraitCoverageTest extends TestCase
{
    /** @return list<class-string> */
    private function tenantModels(): array
    {
        return [
            \App\Models\Resource::class, Booking::class, Unit::class,
            Role::class, RolePermission::class, UserRole::class,
            User::class, RoomFacility::class, RoomFacilityItem::class,
            RoomOperatingHour::class, RoomBlockSchedule::class,
            BookingApproval::class, BookingAttachment::class,
            BookingStatusHistory::class, ApprovalPolicy::class,
            ApprovalPolicyStep::class, ApprovalDelegation::class,
            ActivityLog::class, AppSetting::class, Export::class,
            CalendarConnection::class, BookingCalendarEvent::class,
            WebhookSubscription::class, WebhookDelivery::class,
        ];
    }

    public function test_all_tenant_models_use_the_belongs_to_tenant_trait(): void
    {
        foreach ($this->tenantModels() as $model) {
            $this->assertContains(
                BelongsToTenant::class,
                class_uses_recursive($model),
                $model.' must use BelongsToTenant (tenant leak risk)',
            );
        }
    }

    public function test_global_catalog_models_are_not_tenant_scoped(): void
    {
        // Permissions are a shared global catalog (ADR-029) — must NOT be scoped.
        $this->assertNotContains(
            BelongsToTenant::class,
            class_uses_recursive(Permission::class),
        );
    }
}
