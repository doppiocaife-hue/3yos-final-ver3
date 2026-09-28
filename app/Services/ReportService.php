<?php

namespace App\Services;

use App\Models\Inquiry;
use App\Models\Reservation;
use Carbon\Carbon;

class ReportService
{
    public function getSummary(string $period): array
    {
        $now = now(config('app.timezone'));
        [$start, $end] = match ($period) {
            'daily' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'weekly' => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()],
            'monthly' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            'yearly' => [$now->copy()->startOfYear(), $now->copy()->endOfYear()],
            default => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
        };

        $reservations = Reservation::whereBetween('created_at', [$start, $end]);

        return [
            'period' => $period,
            'period_start' => $start,
            'period_end' => $end,
            'generated_at' => $now->copy(),
            'reservation_count' => $reservations->count(),
            'confirmed_reservations' => (clone $reservations)->where('status', 'confirmed')->count(),
            'completed_events' => (clone $reservations)->where('status', 'completed')->count(),
            'cancelled_reservations' => (clone $reservations)->where('status', 'cancelled')->count(),
            'inquiry_count' => Inquiry::whereBetween('created_at', [$start, $end])->count(),
            'estimated_revenue' => (float) (clone $reservations)->whereIn('status', ['confirmed', 'completed'])->sum('estimated_budget'),
        ];
    }
}
