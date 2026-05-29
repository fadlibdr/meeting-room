<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Enums\BookingStatus;
use App\Livewire\Booking\BookingList;
use App\Models\Booking;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class BookingListTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function userWithRole(string $roleCode): User
    {
        $user = User::factory()->create();
        $role = Role::where('code', $roleCode)->firstOrFail();
        $user->roles()->sync([$role->id]);

        return $user;
    }

    public function test_user_with_view_permission_can_open_the_list(): void
    {
        $this->actingAs($this->userWithRole('requester'))
            ->get(route('bookings.index'))->assertOk();
    }

    public function test_system_admin_without_booking_permission_is_forbidden(): void
    {
        $this->actingAs($this->userWithRole('system_admin'))
            ->get(route('bookings.index'))->assertForbidden();
    }

    public function test_requester_sees_only_their_own_bookings(): void
    {
        $requester = $this->userWithRole('requester');
        Booking::factory()->create(['requester_user_id' => $requester->id, 'subject' => 'Rapat Milik Saya']);
        Booking::factory()->create(['subject' => 'Rapat Milik Orang Lain']);

        $this->actingAs($requester);

        Livewire::test(BookingList::class)
            ->assertSee('Rapat Milik Saya')
            ->assertDontSee('Rapat Milik Orang Lain');
    }

    public function test_view_all_role_sees_every_booking(): void
    {
        Booking::factory()->create(['subject' => 'Booking Satu']);
        Booking::factory()->create(['subject' => 'Booking Dua']);

        $this->actingAs($this->userWithRole('ga_admin'));

        Livewire::test(BookingList::class)
            ->assertSee('Booking Satu')
            ->assertSee('Booking Dua');
    }

    public function test_filter_by_status(): void
    {
        $requester = $this->userWithRole('requester');
        Booking::factory()->create(['requester_user_id' => $requester->id, 'subject' => 'Workshop Mawar', 'status' => BookingStatus::Draft]);
        Booking::factory()->approved()->create(['requester_user_id' => $requester->id, 'subject' => 'Seminar Melati']);

        $this->actingAs($requester);

        Livewire::test(BookingList::class)
            ->set('statusFilter', BookingStatus::Approved->value)
            ->assertSee('Seminar Melati')
            ->assertDontSee('Workshop Mawar');
    }

    public function test_search_by_subject(): void
    {
        $requester = $this->userWithRole('requester');
        Booking::factory()->create(['requester_user_id' => $requester->id, 'subject' => 'Rapat Anggaran Tahunan']);
        Booking::factory()->create(['requester_user_id' => $requester->id, 'subject' => 'Briefing Pagi Singkat']);

        $this->actingAs($requester);

        Livewire::test(BookingList::class)
            ->set('search', 'Anggaran')
            ->assertSee('Rapat Anggaran Tahunan')
            ->assertDontSee('Briefing Pagi Singkat');
    }

    public function test_date_range_filter_on_start(): void
    {
        $requester = $this->userWithRole('requester');
        Booking::factory()->create(['requester_user_id' => $requester->id, 'subject' => 'Acara Lama', 'starts_at' => Carbon::parse('2026-01-10 09:00:00'), 'ends_at' => Carbon::parse('2026-01-10 11:00:00')]);
        Booking::factory()->create(['requester_user_id' => $requester->id, 'subject' => 'Acara Baru', 'starts_at' => Carbon::parse('2026-05-20 09:00:00'), 'ends_at' => Carbon::parse('2026-05-20 11:00:00')]);

        $this->actingAs($requester);

        Livewire::test(BookingList::class)
            ->set('dateFrom', '2026-05-01')
            ->assertSee('Acara Baru')
            ->assertDontSee('Acara Lama');
    }

    public function test_row_shows_booking_code_and_subject(): void
    {
        $requester = $this->userWithRole('requester');
        Booking::factory()->create(['requester_user_id' => $requester->id, 'booking_code' => 'BKG-TEST-0001', 'subject' => 'Booking Tampilan']);

        $this->actingAs($requester);

        Livewire::test(BookingList::class)
            ->assertSee('BKG-TEST-0001')
            ->assertSee('Booking Tampilan');
    }

    public function test_clear_filters_resets_state(): void
    {
        $this->actingAs($this->userWithRole('requester'));

        Livewire::test(BookingList::class)
            ->set('statusFilter', BookingStatus::Draft->value)
            ->set('search', 'abc')
            ->call('clearFilters')
            ->assertSet('statusFilter', '')
            ->assertSet('search', '');
    }

    public function test_create_button_shown_for_requester(): void
    {
        $this->actingAs($this->userWithRole('requester'));

        Livewire::test(BookingList::class)->assertSee('Buat Booking');
    }

    public function test_create_button_hidden_without_create_permission(): void
    {
        $this->actingAs($this->userWithRole('ga_admin'));

        Livewire::test(BookingList::class)->assertDontSee('Buat Booking');
    }
}
