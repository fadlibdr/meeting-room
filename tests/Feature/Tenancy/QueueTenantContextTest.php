<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Jobs\SyncBookingToCalendarsJob;
use App\Models\Booking;
use App\Models\BookingCalendarEvent;
use App\Models\CalendarConnection;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Calendar\CalendarSyncService;
use App\Support\Tenancy\RunsPerTenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class QueueTenantContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_tenant_runs_once_when_off_and_per_tenant_when_on(): void
    {
        $runner = new class
        {
            use RunsPerTenant;

            public int $calls = 0;

            public function go(): void
            {
                $this->eachTenant(function (): void {
                    $this->calls++;
                });
            }
        };

        config(['tenancy.enabled' => false]);
        $runner->go();
        $this->assertSame(1, $runner->calls); // single-tenant: once

        config(['tenancy.enabled' => true]);
        Tenant::factory()->count(2)->create(); // + the default tenant = 3 active
        $runner->calls = 0;
        $runner->go();
        $this->assertSame(Tenant::where('status', 'active')->count(), $runner->calls);
    }

    public function test_sync_job_runs_within_the_bookings_tenant(): void
    {
        config([
            'tenancy.enabled' => true,
            'calendar.sync.enabled' => true,
            'calendar.microsoft.enabled' => true,
            'calendar.microsoft.graph_base' => 'https://graph.microsoft.com/v1.0',
        ]);
        Http::fake(['graph.microsoft.com/*' => Http::response(['id' => 'evt-1'], 200)]);

        $tenant = Tenant::factory()->create();
        $context = app(TenantContext::class);

        $bookingId = $context->runFor($tenant->id, function (): int {
            $user = User::factory()->create();
            CalendarConnection::factory()->create(['user_id' => $user->id, 'provider' => 'microsoft']);

            return Booking::factory()->approved()->create(['requester_user_id' => $user->id])->id;
        });

        // Run the job with NO ambient context — it must set the booking's tenant itself.
        (new SyncBookingToCalendarsJob($bookingId, CalendarSyncService::UPSERT))
            ->handle(app(CalendarSyncService::class), $context);

        // The created mapping was stamped with the booking's tenant (proving the
        // job ran in that tenant's context, not the DB default).
        $event = BookingCalendarEvent::withoutGlobalScope('tenant')->where('booking_id', $bookingId)->first();
        $this->assertNotNull($event);
        $this->assertSame($tenant->id, $event->tenant_id);
    }
}
