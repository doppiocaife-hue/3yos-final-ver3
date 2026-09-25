<?php

namespace Tests\Feature;

use App\Models\Reservation;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    public function test_admin_dashboard_restores_the_calendar_without_a_sidebar_link(): void
    {
        $response = $this->withSession([
            'is_admin' => true,
            'admin_role' => 'full',
        ])->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('Reservation calendar');
        $response->assertSee('id="reservationCalendar"', false);
        $response->assertSee('Previous month');
        $response->assertSee('Next month');
        $response->assertDontSee('href="'.route('admin.dashboard').'#reservation-calendar"', false);
    }

    public function test_calendar_includes_event_details_for_hover_and_month_list(): void
    {
        Reservation::create([
            'full_name' => 'Calendar Test Client',
            'contact_number' => '+639171234567',
            'email' => 'calendar@example.com',
            'address' => '123 Main Street',
            'event_type' => 'Anniversary',
            'event_date' => now()->startOfMonth()->addDays(9)->toDateString(),
            'event_time' => '18:30',
            'venue' => 'Celebration Hall',
            'guest_count' => 80,
            'estimated_budget' => 56000,
            'status' => 'confirmed',
            'reservation_code' => 'RES-CALENDAR-01',
        ]);

        $response = $this->withSession([
            'is_admin' => true,
            'admin_role' => 'full',
        ])->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('Calendar Test Client');
        $response->assertSee('Anniversary');
        $response->assertSee('Celebration Hall');
        $response->assertSee('confirmed');
    }
}