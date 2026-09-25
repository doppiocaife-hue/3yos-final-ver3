<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;

class ActivityLogController extends Controller
{
    public function index(\Illuminate\Http\Request $request)
    {
        $filters = $request->validate([
            'actor' => ['nullable', 'string', 'max:255'],
            'action' => ['nullable', 'string', 'max:255'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);
        $perPage = max(10, min(100, (int) $request->input('per_page', 10)));
        $logs = ActivityLog::query()
            ->when($filters['actor'] ?? null, fn ($query, $actor) => $query->where('actor_email', $actor))
            ->when($filters['action'] ?? null, fn ($query, $action) => $query->whereRaw('LOWER(action) LIKE ?', ['%'.str($action)->lower()->toString().'%']))
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '<=', $date))
            ->latest()->paginate($perPage)->withQueryString();
        $actors = ActivityLog::whereNotNull('actor_email')->select('actor_email', 'actor_name')->distinct()->orderBy('actor_name')->get();

        return view('admin.activity-logs', compact('logs', 'actors', 'perPage'));
    }
}
