<?php

declare(strict_types=1);

namespace Tests\Feature\Pdp;

use App\Actions\AnonymizeUserAction;
use App\Models\Booking;
use App\Models\CalendarConnection;
use App\Models\Role;
use App\Models\User;
use App\Services\PersonalDataExporter;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DataSubjectRightsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function userWithRole(string $code): User
    {
        $user = User::factory()->create();
        $user->roles()->sync([Role::where('code', $code)->firstOrFail()->id]);

        return $user;
    }

    public function test_exporter_includes_profile_and_bookings_but_no_secrets(): void
    {
        $user = User::factory()->create(['name' => 'Budi', 'email' => 'budi@bpjs.go.id']);
        Booking::factory()->approved()->create(['requester_user_id' => $user->id, 'subject' => 'Rapat Saya']);
        CalendarConnection::factory()->create(['user_id' => $user->id, 'provider' => 'microsoft']);

        $data = app(PersonalDataExporter::class)->export($user);

        $this->assertSame('Budi', $data['profile']['name']);
        $this->assertSame('Rapat Saya', $data['bookings'][0]['subject']);
        $this->assertSame('microsoft', $data['calendar_connections'][0]['provider']);

        $json = json_encode($data);
        $this->assertStringNotContainsString('access_token', (string) $json);
        $this->assertStringNotContainsString('password', (string) $json);
    }

    public function test_user_can_download_their_own_data(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('data.export.mine'));

        $response->assertOk();
        $this->assertStringContainsString('application/json', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_anonymize_scrubs_pii_revokes_access_and_keeps_bookings(): void
    {
        $user = User::factory()->create(['name' => 'Siti', 'email' => 'siti@bpjs.go.id', 'employee_no' => 'EMP-9']);
        $user->roles()->sync([Role::where('code', 'requester')->firstOrFail()->id]);
        $booking = Booking::factory()->approved()->create(['requester_user_id' => $user->id]);
        CalendarConnection::factory()->create(['user_id' => $user->id]);
        $user->createToken('t', ['read']);

        app(AnonymizeUserAction::class)->execute($user->fresh(), actorId: null);

        $fresh = $user->fresh();
        $this->assertSame('Pengguna Dianonimkan', $fresh->name);
        $this->assertSame('anonymized-'.$user->id.'@anonymized.invalid', $fresh->email);
        $this->assertNull($fresh->employee_no);
        $this->assertFalse($fresh->is_active);
        $this->assertSame(0, $fresh->tokens()->count());
        $this->assertSame(0, CalendarConnection::where('user_id', $user->id)->count());
        $this->assertSame(0, $fresh->roles()->count());
        // Booking is retained for referential / audit integrity.
        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'requester_user_id' => $user->id]);
        $this->assertDatabaseHas('activity_logs', ['event' => 'anonymize', 'subject_id' => $user->id]);
    }

    public function test_admin_can_export_and_anonymize_but_requester_cannot(): void
    {
        $admin = $this->userWithRole('super_admin');
        $target = User::factory()->create();

        $this->actingAs($admin)->get(route('admin.users.data-export', $target->id))->assertOk();
        $this->actingAs($admin)->post(route('admin.users.anonymize', $target->id))
            ->assertRedirect(route('admin.users.index'));
        $this->assertSame('Pengguna Dianonimkan', $target->fresh()->name);

        $requester = $this->userWithRole('requester');
        $other = User::factory()->create();
        $this->actingAs($requester)->get(route('admin.users.data-export', $other->id))->assertForbidden();
        $this->actingAs($requester)->post(route('admin.users.anonymize', $other->id))->assertForbidden();
    }

    public function test_admin_cannot_anonymize_their_own_account(): void
    {
        $admin = $this->userWithRole('super_admin');

        $this->actingAs($admin)->post(route('admin.users.anonymize', $admin->id))
            ->assertSessionHasErrors('anonymize');
        $this->assertNotSame('Pengguna Dianonimkan', $admin->fresh()->name);
    }
}
