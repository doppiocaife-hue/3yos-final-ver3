<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Models\Reservation;
use Carbon\Carbon;
use Tests\TestCase;

class AnalyticsTest extends TestCase
{
    public function test_full_admin_can_view_analytics_with_fully_paid_completed_revenue(): void
    {
        Carbon::setTestNow('2026-09-23 12:00:00');

        $package = Package::create([
            'name' => 'Celebration Package',
            'slug' => 'celebration-package',
            'price' => 500,
            'min_guests' => 20,
            'max_guests' => 200,
        ]);

        $this->createReservation($package->id, [
            'status' => 'confirmed',
            'estimated_budget' => 15000,
            'total_cost' => 22000,
            'payment_status' => 'Fully Paid',
            'amount_paid' => 22000,
        ]);
        $this->createReservation($package->id, [
            'status' => 'completed',
            'estimated_budget' => 18000,
            'total_cost' => null,
            'payment_status' => 'Fully Paid',
            'amount_paid' => 18000,
        ]);
        $this->createReservation($package->id, [
            'status' => 'pending',
            'estimated_budget' => 9000,
            'total_cost' => 12000,
            'payment_status' => 'Fully Paid',
            'amount_paid' => 12000,
        ]);
        $this->createReservation($package->id, [
            'status' => 'confirmed',
            'estimated_budget' => 50000,
            'total_cost' => 30000,
            'payment_status' => 'Downpayment',
            'amount_paid' => 6000,
        ]);

        $response = $this->withSession(['is_admin' => true, 'admin_role' => 'full'])
            ->get(route('admin.analytics'));

        $response->assertOk();
        $response->assertViewHas('monthlyRevenue', function ($monthlyRevenue) {
            return $monthlyRevenue->last()->revenue === 18000.0;
        });
        $response->assertSee('&#8369;18,000', false);
        $response->assertSee('Celebration Package');
        $response->assertSee("new Chart(document.getElementById('revenueChart')", false);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createReservation(int $packageId, array $overrides): void
    {
        Reservation::create(array_merge([
            'package_id' => $packageId,
            'full_name' => 'Analytics Client',
            'contact_number' => '09171234567',
            'email' => 'analytics@example.com',
            'address' => '123 Analytics Street',
            'event_type' => 'Wedding',
            'event_date' => now()->addMonth()->toDateString(),
            'event_time' => '18:00',
            'venue' => 'Analytics Hall',
            'guest_count' => 100,
            'estimated_budget' => 0,
            'status' => 'pending',
        ], $overrides));
    }
}
