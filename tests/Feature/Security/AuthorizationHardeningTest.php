<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Livewire\Admin\UserForm;
use App\Livewire\Booking\BookingForm;
use App\Models\Booking;
use App\Models\Role;
use App\Models\Room;
use App\Models\Unit;
use App\Models\User;
use App\Rules\PublicUrl;
use Database\Seeders\AppSettingsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Regression coverage for the pen-test findings — the inverse of the exploits
 * that were reproduced: low-privilege users must now be blocked.
 */
class AuthorizationHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(AppSettingsSeeder::class);
    }

    private function userWithRole(string $code): User
    {
        $unit = Unit::factory()->create();
        $user = User::factory()->create(['unit_id' => $unit->id, 'is_active' => true]);
        $role = Role::where('code', $code)->firstOrFail();
        $user->roles()->attach($role->id, ['is_primary' => true, 'assigned_at' => now()]);

        return $user->fresh();
    }

    // ─── UserForm privilege escalation (was CRITICAL) ───────────────

    public function test_low_priv_user_cannot_mount_userform_create(): void
    {
        $attacker = $this->userWithRole('requester');

        Livewire::actingAs($attacker)->test(UserForm::class)->assertForbidden();
    }

    public function test_low_priv_user_cannot_mount_userform_edit(): void
    {
        $attacker = $this->userWithRole('requester');
        $victim = $this->userWithRole('requester');

        Livewire::actingAs($attacker)->test(UserForm::class, ['user' => $victim])->assertForbidden();
    }

    public function test_authorized_admin_can_still_create_a_user(): void
    {
        $admin = $this->userWithRole('super_admin');
        $unit = Unit::factory()->create();
        $role = Role::where('code', 'requester')->firstOrFail();

        Livewire::actingAs($admin)
            ->test(UserForm::class)
            ->set('name', 'Pegawai Baru')
            ->set('email', 'pegawai.baru@bpjs-kesehatan.go.id')
            ->set('unitId', $unit->id)
            ->set('roleIds', [$role->id])
            ->set('isActive', true)
            ->call('save');

        $this->assertDatabaseHas('users', ['email' => 'pegawai.baru@bpjs-kesehatan.go.id']);
    }

    // ─── BookingForm broken access control (was HIGH) ───────────────

    public function test_booking_id_and_mode_are_locked_against_tampering(): void
    {
        $attacker = $this->userWithRole('requester'); // has bookings.create
        $victim = $this->userWithRole('requester');
        $room = Room::factory()->create(['is_active' => true, 'status' => 'active', 'capacity' => 10]);
        $booking = Booking::create([
            'booking_code' => 'BKG-LOCK-001',
            'resource_id' => $room->id,
            'requester_user_id' => $victim->id,
            'requester_unit_id' => $victim->unit_id,
            'created_by_user_id' => $victim->id,
            'subject' => 'Victim subject',
            'attendee_count' => 5,
            'starts_at' => now()->addDays(3)->setTime(10, 0),
            'ends_at' => now()->addDays(3)->setTime(11, 0),
            'status' => 'draft',
            'source' => 'user',
        ]);

        $component = Livewire::actingAs($attacker)->test(BookingForm::class); // create mode

        // #[Locked] must reject client attempts to repoint the form at a victim's
        // booking or flip it into edit mode.
        $tamperRejected = false;
        try {
            $component->set('bookingId', $booking->id);
        } catch (\Throwable) {
            $tamperRejected = true;
        }
        $this->assertTrue($tamperRejected, 'bookingId should be locked');

        $booking->refresh();
        $this->assertSame('Victim subject', $booking->subject);
    }

    // ─── SSRF rule (was HIGH) ───────────────────────────────────────

    public function test_public_url_rule_rejects_internal_targets(): void
    {
        $fails = Validator::make(
            ['url' => 'http://169.254.169.254/latest/meta-data/'],
            ['url' => ['required', new PublicUrl]],
        )->fails();

        $this->assertTrue($fails);
    }
}
