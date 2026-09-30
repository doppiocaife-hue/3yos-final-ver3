<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Models\Reservation;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminReservationDetailTest extends TestCase
{
    private const ADMIN = ['is_admin' => true, 'admin_role' => 'full', 'admin_name' => 'Detail Tester', 'admin_email' => 'detail@3yos.com'];

    private function reservation(array $overrides = []): Reservation
    {
        $package = Package::create(['name' => 'Detail Package', 'slug' => 'detail-'.uniqid(), 'price' => 800, 'min_guests' => 20, 'max_guests' => 200]);

        return Reservation::create($overrides + [
            'package_id' => $package->id,
            'full_name' => 'Detail Client',
            'contact_number' => '09171234567',
            'email' => 'detail-'.uniqid().'@example.com',
            'address' => '1 Detail Street',
            'event_type' => 'Wedding',
            'event_date' => now()->addMonth()->toDateString(),
            'event_time' => '18:00',
            'venue' => 'Detail Hall',
            'guest_count' => 60,
            'estimated_budget' => 48000,
            'total_cost' => 48000,
            'status' => 'pending',
            'reservation_code' => 'RES-DET'.random_int(100000, 999999),
        ]);
    }

    public function test_guest_cannot_view_the_reservation_detail_page(): void
    {
        $reservation = $this->reservation();

        $this->get(route('admin.reservations.show', $reservation))
            ->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_view_the_reservation_detail_page(): void
    {
        $reservation = $this->reservation(['status' => 'confirmed']);

        $response = $this->withSession(self::ADMIN)->get(route('admin.reservations.show', $reservation));

        $response->assertOk();
        $response->assertSee($reservation->full_name);
        $response->assertSee($reservation->reservation_code);
        $response->assertSee('Customer');
        $response->assertSee('Event');
        $response->assertSee('Package');
        $response->assertSee('Contract');
        $response->assertSee('Payment');
        $response->assertSee('Notes');
        $response->assertSee('Activity');
        $response->assertSee('Submitted');
        $response->assertSee('Under Review');
        $response->assertSee('Accepted');
        $response->assertSee('Completed');
    }

    public function test_reservations_list_links_to_the_detail_page(): void
    {
        $reservation = $this->reservation();

        $response = $this->withSession(self::ADMIN)->get(route('admin.reservations'));

        $response->assertOk();
        $response->assertSee(route('admin.reservations.show', $reservation), false);
    }

    public function test_admin_can_save_an_internal_note_from_the_detail_page(): void
    {
        Mail::fake();
        $reservation = $this->reservation();

        $this->withSession(self::ADMIN)
            ->patch(route('admin.reservations.status', $reservation), ['admin_notes' => 'Client requested vegan menu.'])
            ->assertSessionHasNoErrors();

        $this->assertSame('Client requested vegan menu.', $reservation->fresh()->admin_notes);
        $this->assertSame('pending', $reservation->fresh()->status);
    }

    public function test_admin_created_booking_is_blocked_once_four_accepted_bookings_exist_for_the_date(): void
    {
        Mail::fake();
        $date = now()->addMonths(2)->toDateString();
        $this->reservation(['status' => 'confirmed', 'event_date' => $date]);
        $this->reservation(['status' => 'confirmed', 'event_date' => $date]);
        $this->reservation(['status' => 'confirmed', 'event_date' => $date]);
        $this->reservation(['status' => 'confirmed', 'event_date' => $date]);

        $package = Package::create(['name' => 'Overflow Package', 'slug' => 'overflow-'.uniqid(), 'price' => 500]);

        $response = $this->withSession(self::ADMIN)->post(route('admin.reservations.store'), [
            'full_name' => 'Overflow Client',
            'contact_number' => '09171234567',
            'email' => 'overflow@example.com',
            'address' => '1 Overflow Street',
            'event_type' => 'Birthday',
            'event_date' => $date,
            'event_time' => '18:00',
            'venue' => 'Overflow Hall',
            'guest_count' => 30,
            'package_id' => $package->id,
        ]);

        $response->assertSessionHasErrors('event_date');
        $this->assertDatabaseMissing('reservations', ['email' => 'overflow@example.com']);
    }
}
