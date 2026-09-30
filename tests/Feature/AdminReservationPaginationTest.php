<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Models\Reservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminReservationPaginationTest extends TestCase
{
    use RefreshDatabase;

    private Package $package;

    protected function setUp(): void
    {
        parent::setUp();

        $this->package = Package::create([
            'name' => 'Pagination test package',
            'slug' => 'pagination-test-package',
            'price' => 500,
        ]);
    }

    public function test_filtered_reservations_are_paginated_ten_per_page(): void
    {
        foreach (range(1, 17) as $number) {
            $this->createReservation($number, 'pending');
        }
        foreach (range(18, 20) as $number) {
            $this->createReservation($number, 'confirmed');
        }

        $response = $this->withSession([
            'is_admin' => true,
            'admin_role' => 'full',
        ])->get(route('admin.reservations', ['status' => 'pending']));

        $response->assertOk();
        $response->assertSee('17 matching reservations');
        $response->assertSeeText('Showing 1–10 of 17 reservations');
        $response->assertViewHas('reservations', fn ($reservations) => $reservations->count() === 10
            && $reservations->total() === 17
            && $reservations->currentPage() === 1);
        $response->assertSee('status=pending');

        $secondPage = $this->get(route('admin.reservations', ['status' => 'pending', 'page' => 2]));

        $secondPage->assertOk();
        $secondPage->assertSeeText('Showing 11–17 of 17 reservations');
        $secondPage->assertViewHas('reservations', fn ($reservations) => $reservations->count() === 7
            && $reservations->total() === 17
            && $reservations->currentPage() === 2);
    }

    public function test_an_out_of_range_page_redirects_to_the_last_valid_page(): void
    {
        foreach (range(1, 21) as $number) {
            $this->createReservation($number);
        }

        Reservation::where('reservation_code', 'RES-PAGE-021')->delete();

        $response = $this->withSession([
            'is_admin' => true,
            'admin_role' => 'full',
        ])->get(route('admin.reservations', ['status' => 'pending', 'page' => 3]));

        $response->assertRedirect(route('admin.reservations', ['status' => 'pending', 'page' => 2]));
    }

    private function createReservation(int $number, string $status = 'pending'): Reservation
    {
        return Reservation::create([
            'package_id' => $this->package->id,
            'full_name' => 'Pagination Client '.$number,
            'contact_number' => '09171234567',
            'email' => 'pagination'.$number.'@example.com',
            'address' => '123 Pagination Street',
            'event_type' => 'Wedding',
            'event_date' => now()->addDays($number)->toDateString(),
            'event_time' => '18:00',
            'venue' => 'Pagination Venue',
            'guest_count' => 40,
            'estimated_budget' => 20000,
            'status' => $status,
            'reservation_code' => sprintf('RES-PAGE-%03d', $number),
        ]);
    }
}
