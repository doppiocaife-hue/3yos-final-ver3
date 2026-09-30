@extends('layouts.admin')

@section('content')
@php
    $statusBadgeClass = fn ($status) => match ($status) {
        'new' => 'status-badge--pending',
        'in_progress' => 'status-badge--completed',
        'responded' => 'status-badge--confirmed',
        'closed' => 'status-badge--neutral',
        default => 'status-badge--neutral',
    };
    $statusLabel = fn ($status) => ucwords(str_replace('_', ' ', $status));
@endphp
<div class="content-card p-4">
    <div class="page-header">
        <div><h1 class="fw-bold mb-1">Inquiries</h1><p class="text-muted mb-0">Track messages from prospective clients.</p></div>
        @if($needsResponseCount > 0)
            <a href="{{ route('admin.inquiries', ['needs_response' => 1]) }}" class="badge-soft badge-soft--warn">{{ $needsResponseCount }} need{{ $needsResponseCount === 1 ? 's' : '' }} a response</a>
        @endif
    </div>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

    <form id="inquiry-filter-form" method="GET" action="{{ route('admin.inquiries') }}" class="filter-bar" data-live-filter data-live-filter-target="#inquiry-results">
        <div class="filter-field filter-field--wide">
            <label class="form-label" for="inquiry-search">Search</label>
            <input id="inquiry-search" type="search" name="search" class="form-control" value="{{ old('search', $search ?? '') }}" placeholder="Name, email, subject, category">
        </div>
        <div class="filter-field">
            <label class="form-label" for="inquiry-status">Status</label>
            <select id="inquiry-status" name="status" class="form-select">
                <option value="">All statuses</option>
                <option value="new" @selected($status === 'new')>New</option>
                <option value="in_progress" @selected($status === 'in_progress')>In Progress</option>
                <option value="responded" @selected($status === 'responded')>Responded</option>
                <option value="closed" @selected($status === 'closed')>Closed</option>
            </select>
        </div>
        <div class="filter-field filter-field--checkbox">
            <label class="form-check">
                <input type="checkbox" name="needs_response" value="1" class="form-check-input" @checked($needsResponse) onchange="this.form.requestSubmit()">
                <span class="form-check-label">Needs response only</span>
            </label>
        </div>
        <div class="filter-field">
            @if($status || $needsResponse || ($search ?? '') !== '')
                <a href="{{ route('admin.inquiries') }}" class="btn btn-outline-secondary w-100" data-live-filter-clear="#inquiry-filter-form">Clear</a>
            @endif
        </div>
    </form>

    <div id="inquiry-results" aria-live="polite">
    <div class="table-responsive d-none d-md-block">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Client</th>
                    <th>Inquiry</th>
                    <th class="d-none d-lg-table-cell">Message</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($inquiries as $inquiry)
                    <tr>
                        <td>
                            <strong>{{ $inquiry->full_name }}</strong>
                            <br><small class="text-muted">{{ $inquiry->email }}</small>
                        </td>
                        <td>
                            {{ $inquiry->subject }}
                            <br><small class="text-muted">{{ $inquiry->category }}</small>
                        </td>
                        <td class="d-none d-lg-table-cell text-muted">{{ \Illuminate\Support\Str::limit($inquiry->message, 80) }}</td>
                        <td><span class="status-badge {{ $statusBadgeClass($inquiry->status) }}">{{ $statusLabel($inquiry->status) }}</span></td>
                        <td>
                            <div class="table-actions">
                                <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.inquiries.show', $inquiry) }}">View</a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center py-4 text-muted">No inquiries found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="inquiry-mobile-list d-md-none">
        @forelse($inquiries as $inquiry)
            <article class="inquiry-mobile-card">
                <h5 class="mb-1">{{ $inquiry->full_name }}</h5>
                <a class="customer-contact" href="mailto:{{ $inquiry->email }}">{{ $inquiry->email }}</a>
                <p class="mb-2">{{ $inquiry->subject }}</p>
                <p class="mb-3 inquiry-status-line">Status: <span class="status-badge {{ $statusBadgeClass($inquiry->status) }}">{{ $statusLabel($inquiry->status) }}</span></p>
                <div class="text-end">
                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.inquiries.show', $inquiry) }}">View</a>
                </div>
            </article>
        @empty
            <div class="text-center text-muted py-4">No inquiries found.</div>
        @endforelse
    </div>
    </div>
</div>

<style>
    .filter-field--checkbox { display: flex; align-items: center; min-height: var(--control-h); }
    .filter-field--checkbox .form-check { display: flex; align-items: center; gap: .5rem; margin: 0; cursor: pointer; }
    .filter-field--checkbox .form-check-label { font-size: .84rem; color: var(--ink); cursor: pointer; }
    .inquiry-mobile-card { margin-bottom: .75rem; padding: 1rem; border: 1px solid var(--line); border-radius: var(--radius); background: var(--surface); }
    .inquiry-mobile-card:last-child { margin-bottom: 0; }
    .inquiry-mobile-card h5 { font-size: 1rem; }
    .inquiry-mobile-card p { font-size: .85rem; color: var(--ink); }
    .inquiry-status-line { display: flex; align-items: center; gap: .4rem; font-weight: 700; color: var(--muted); }
    .inquiry-mobile-card .customer-contact { display: block; margin-bottom: .35rem; color: var(--teal); font-size: .8rem; text-decoration: none; overflow-wrap: anywhere; }
</style>
@endsection
