<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use Tests\TestCase;

class ActivityLogFilteringTest extends TestCase
{
    public function test_activity_logs_combined_filters_match_the_selected_actor_search_and_date_range(): void
    {
        ActivityLog::query()->delete();

        ActivityLog::create([
            'actor_name' => 'Jan Cruz',
            'actor_email' => 'jan@3yos.com',
            'action' => 'Created reservation',
            'method' => 'POST',
            'activity_date' => '2026-09-24',
            'activity_time' => '09:15:00',
            'description' => 'Created a new backup export for the event schedule.',
            'created_at' => '2026-09-24 09:15:00',
            'updated_at' => '2026-09-24 09:15:00',
        ]);

        ActivityLog::create([
            'actor_name' => 'Jan Cruz',
            'actor_email' => 'jan@3yos.com',
            'action' => 'Updated reservation',
            'method' => 'PATCH',
            'activity_date' => '2026-09-25',
            'activity_time' => '10:00:00',
            'description' => 'Updated reservation details.',
            'created_at' => '2026-09-25 10:00:00',
            'updated_at' => '2026-09-25 10:00:00',
        ]);

        ActivityLog::create([
            'actor_name' => 'Mario Sison',
            'actor_email' => 'mario@3yos.com',
            'action' => 'Created reservation',
            'method' => 'POST',
            'activity_date' => '2026-09-24',
            'activity_time' => '11:00:00',
            'description' => 'Created a reservation backup import.',
            'created_at' => '2026-09-24 11:00:00',
            'updated_at' => '2026-09-24 11:00:00',
        ]);

        $response = $this->withSession([
            'is_admin' => true,
            'admin_role' => 'full',
        ])->get(route('admin.activity-logs', [
            'actor' => 'jan@3yos.com',
            'search' => 'backup',
            'date_from' => '2026-09-24',
            'date_to' => '2026-09-24',
            'per_page' => 10,
        ]));

        $response->assertOk();
        $response->assertViewHas('logs', function ($logs) {
            return $logs->count() === 1
                && $logs->total() === 1
                && $logs->first()->actor_email === 'jan@3yos.com'
                && str_contains(strtolower($logs->first()->description), 'backup');
        });
    }

    public function test_activity_logs_filter_by_actor_and_never_paginate_below_ten(): void
    {
        foreach (range(1, 11) as $index) {
            ActivityLog::create([
                'actor_name' => 'Admin '.$index,
                'actor_email' => 'admin@example.com',
                'action' => 'Updated reservation',
                'method' => 'PATCH',
                'activity_date' => now()->toDateString(),
                'activity_time' => now()->toTimeString(),
                'description' => 'Updated reservation details.',
            ]);
        }

        ActivityLog::create([
            'actor_name' => 'Other admin',
            'actor_email' => 'other@example.com',
            'action' => 'Signed in',
            'method' => 'SESSION',
            'activity_date' => now()->toDateString(),
            'activity_time' => now()->toTimeString(),
            'description' => 'Signed in.',
        ]);

        $response = $this->withSession([
            'is_admin' => true,
            'admin_role' => 'full',
        ])->get(route('admin.activity-logs', ['actor' => 'admin@example.com', 'per_page' => 5]));

        $response->assertOk();
        $response->assertDontSee('Entries per page');
        $response->assertDontSee('name="per_page"');
        $response->assertViewHas('logs', function ($logs) {
            return $logs->count() === 10
                && $logs->total() === 11
                && $logs->every(fn (ActivityLog $log) => $log->actor_email === 'admin@example.com');
        });
    }
}