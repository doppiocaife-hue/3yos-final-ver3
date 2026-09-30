<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Models\Reservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReservationAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private const DATE = '2030-10-08';

    private function reservation(array $overrides = []): Reservation
    {
        $package = Package::create(['name' => 'Silver', 'slug' => 'silver-'.uniqid(), 'price' => 750, 'min_guests' => 20, 'max_guests' => 200]);

        return Reservation::create($overrides + [
            'package_id' => $package->id,
            'full_name' => 'Availability Client',
            'contact_number' => '09171234567',
            'email' => 'availability-'.uniqid().'@example.com',
            'address' => '1 Availability Street',
            'event_type' => 'Wedding',
            'event_date' => self::DATE,
            'event_time' => '18:00',
            'venue' => 'Availability Hall',
            'guest_count' => 80,
            'estimated_budget' => 60000,
            'total_cost' => 60000,
            'status' => 'pending',
            'reservation_code' => 'RES-AVAIL'.random_int(100000, 999999),
        ]);
    }

    private function checkAvailability(): array
    {
        return $this->get(route('reservation.availability', ['date' => self::DATE]))->json();
    }

    #[DataProvider('acceptedCountProvider')]
    public function test_availability_by_accepted_count(int $acceptedCount, bool $expectedAvailable): void
    {
        for ($i = 0; $i < $acceptedCount; $i++) {
            $this->reservation(['status' => 'confirmed']);
        }

        $response = $this->checkAvailability();

        $this->assertSame($expectedAvailable, $response['available']);
        $this->assertSame($acceptedCount, $response['bookings']);
    }

    public static function acceptedCountProvider(): array
    {
        return [
            'case 1: 0 accepted -> available' => [0, true],
            'case 2: 1 accepted -> available' => [1, true],
            'case 3: 2 accepted -> available' => [2, true],
            'case 4: 3 accepted -> available' => [3, true],
            'case 5: 4 accepted -> fully booked' => [4, false],
        ];
    }

    public function test_case_6_three_accepted_plus_one_pending_is_available(): void
    {
        $this->reservation(['status' => 'confirmed']);
        $this->reservation(['status' => 'confirmed']);
        $this->reservation(['status' => 'confirmed']);
        $this->reservation(['status' => 'pending']);

        $response = $this->checkAvailability();

        $this->assertTrue($response['available']);
        $this->assertSame(3, $response['bookings']);
        $this->assertSame(1, $response['remaining']);
    }

    public function test_case_7_three_accepted_plus_one_cancelled_is_available(): void
    {
        $this->reservation(['status' => 'confirmed']);
        $this->reservation(['status' => 'confirmed']);
        $this->reservation(['status' => 'confirmed']);
        $this->reservation(['status' => 'cancelled']);

        $response = $this->checkAvailability();

        $this->assertTrue($response['available']);
        $this->assertSame(3, $response['bookings']);
    }

    public function test_case_8_four_accepted_plus_pending_and_cancelled_is_fully_booked(): void
    {
        $this->reservation(['status' => 'confirmed']);
        $this->reservation(['status' => 'confirmed']);
        $this->reservation(['status' => 'confirmed']);
        $this->reservation(['status' => 'confirmed']);
        $this->reservation(['status' => 'pending']);
        $this->reservation(['status' => 'pending']);
        $this->reservation(['status' => 'cancelled']);

        $response = $this->checkAvailability();

        $this->assertFalse($response['available']);
        $this->assertSame(4, $response['bookings']);
        $this->assertSame(0, $response['remaining']);
    }

    public function test_completed_bookings_do_not_count_toward_availability(): void
    {
        $this->reservation(['status' => 'completed']);
        $this->reservation(['status' => 'completed']);
        $this->reservation(['status' => 'completed']);
        $this->reservation(['status' => 'completed']);

        $response = $this->checkAvailability();

        $this->assertTrue($response['available']);
        $this->assertSame(0, $response['bookings']);
    }

    public function test_availability_uses_the_shared_reservation_model_constant(): void
    {
        $this->assertSame(4, Reservation::MAX_ACCEPTED_BOOKINGS_PER_DATE);

        for ($i = 0; $i < Reservation::MAX_ACCEPTED_BOOKINGS_PER_DATE; $i++) {
            $this->reservation(['status' => 'confirmed']);
        }

        $response = $this->checkAvailability();

        $this->assertFalse($response['available']);
    }
}
