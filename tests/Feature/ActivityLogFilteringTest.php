<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use Tests\TestCase;

class ActivityLogFilteringTest extends TestCase
{
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