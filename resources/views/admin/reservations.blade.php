@extends('layouts.admin')

@section('content')
<div class="content-card p-4">
    <div class="page-header">
        <div>
            <h1 class="fw-bold mb-1">Reservations</h1>
            <p class="text-muted mb-0">Review customer information, then accept, cancel, or update each booking.</p>
        </div>
        <div class="page-actions">
            <a class="btn luxury-btn" href="{{ route('admin.reservations.create') }}">Add reservation</a>
            <a class="btn btn-outline-secondary" href="{{ route('admin.reservations') }}">Refresh bookings</a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <form id="reservation-filter-form" method="GET" action="{{ route('admin.reservations') }}" class="filter-bar" data-live-filter data-live-filter-target="#reservation-results">
        <div class="filter-field filter-field--wide">
            <label class="form-label">Customer</label>
            <input type="search" name="search" class="form-control" value="{{ old('search', $search ?? '') }}" placeholder="Name, email, phone, package, event, ID">
        </div>
        <div class="filter-field">
            <label class="form-label">From date</label>
            <input type="date" name="date_from" class="form-control" value="{{ old('date_from', $dateFrom ?? '') }}">
        </div>
        <div class="filter-field">
            <label class="form-label">To date</label>
            <input type="date" name="date_to" class="form-control" value="{{ old('date_to', $dateTo ?? '') }}">
        </div>
        <div class="filter-field">
            <label class="form-label">Reservation status</label>
            <select name="status" class="form-select">
                <option value="">All statuses</option>
                <option value="pending" @selected($status === 'pending')>Pending</option>
                <option value="confirmed" @selected($status === 'confirmed')>Accepted</option>
                <option value="completed" @selected($status === 'completed')>Completed</option>
                <option value="cancelled" @selected($status === 'cancelled')>Cancelled</option>
            </select>
        </div>
        <div class="filter-field">
            <label class="form-label">Payment status</label>
            <select name="payment_status" class="form-select">
                <option value="">All payments</option>
                <option value="Unpaid" @selected($paymentStatus === 'Unpaid')>Unpaid</option>
                <option value="Downpayment" @selected($paymentStatus === 'Downpayment')>Downpayment</option>
                <option value="Fully Paid" @selected($paymentStatus === 'Fully Paid')>Fully Paid</option>
            </select>
        </div>
        <div class="filter-field">
            @if($status || $paymentStatus || ($search ?? '') !== '' || ($dateFrom ?? '') !== '' || ($dateTo ?? '') !== '')
                <a href="{{ route('admin.reservations') }}" class="btn btn-outline-secondary w-100" data-live-filter-clear="#reservation-filter-form">Clear</a>
            @endif
        </div>
    </form>

    <div class="row g-3 mb-4 reservation-stats">
        <div class="col-sm-6 col-xl-3">
            <div class="reservation-stat reservation-stat--customers"><span>Customers</span><strong>{{ $customerCount }}</strong><small>Unique customer emails</small></div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="reservation-stat reservation-stat--pending"><span>Pending</span><strong>{{ $pendingCount }}</strong><small>Need a decision</small></div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="reservation-stat reservation-stat--accepted"><span>Accepted</span><strong>{{ $acceptedCount }}</strong><small>Confirmed bookings</small></div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="reservation-stat reservation-stat--cancelled"><span>Cancelled</span><strong>{{ $cancelledCount }}</strong><small>Closed bookings</small></div>
        </div>
    </div>

    <div id="reservation-results" data-filter-count="{{ $matchingReservationCount }}" aria-live="polite">
    <div class="reservation-match-count text-muted small mb-2">{{ $matchingReservationCount }} matching reservation{{ $matchingReservationCount === 1 ? '' : 's' }}</div>
    <div class="reservation-table-container d-none d-md-block">
        <table class="table table-hover align-middle mb-0 reservations-table">
            <colgroup>
                <col style="width:9%"><col style="width:14%"><col style="width:8.5%"><col style="width:8.5%"><col style="width:11.5%">
                <col style="width:4.5%"><col style="width:6.5%"><col style="width:15.5%"><col style="width:8%"><col style="width:14%">
            </colgroup>
            <thead>
                <tr>
                    <th>Unique ID</th>
                    <th>Customer</th>
                    <th>Package</th>
                    <th>Event</th>
                    <th>Schedule</th>
                    <th>Guests</th>
                    <th>Contract</th>
                    <th>Status</th>
                    <th>Contract Price</th>
                    <th>Payment</th>
                </tr>
            </thead>
            <tbody>
                @forelse($reservations as $reservation)
                    @php($statusLabel = $reservation->status === 'confirmed' ? 'Accepted' : ucfirst($reservation->status))
                    @php($paymentType = $reservation->payment_type ?? $reservation->payment_status ?? 'Unpaid')
                    @php($outstandingBalance = $reservation->total_cost === null ? null : max(0, (float) $reservation->total_cost - (float) ($reservation->amount_paid ?? 0)))
                    <tr>
                        <td>
                            <span class="reservation-code">{{ $reservation->reservation_code ?? '—' }}</span>
                        </td>
                        <td>
                            <strong class="d-block">{{ $reservation->full_name }}</strong>
                            <a class="customer-contact customer-email" href="mailto:{{ $reservation->email }}" title="{{ $reservation->email }}">{{ $reservation->email }}</a>
                            <a class="customer-contact" href="tel:{{ $reservation->contact_number }}">{{ $reservation->contact_number }}</a>
                        </td>
                        <td>
                            @if($reservation->status === 'confirmed')
                                <form method="POST" action="{{ route('admin.reservations.status', $reservation) }}" class="cell-form" data-confirm-message="Update the package for this reservation?">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="status" value="confirmed">
                                    <select name="package_id" class="form-select form-select-sm cell-full">
                                        @foreach($packages as $package)
                                            <option value="{{ $package->id }}" @selected($reservation->package_id === $package->id)>{{ $package->name }}</option>
                                        @endforeach
                                    </select>
                                    <button class="btn btn-sm luxury-btn" type="submit">Save</button>
                                </form>
                            @else
                                <strong>{{ $reservation->package?->name ?? 'Custom package' }}</strong>
                            @endif
                        </td>
                        <td>
                            <span class="d-block">{{ $reservation->event_type }}</span>
                            <small class="text-muted">{{ $reservation->venue }}</small>
                        </td>
                        <td>
                            @if($reservation->status === 'confirmed')
                                <form method="POST" action="{{ route('admin.reservations.status', $reservation) }}" class="cell-form" data-confirm-message="Update the schedule for this reservation?">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="status" value="confirmed">
                                    <input type="date" name="event_date" value="{{ $reservation->event_date }}" class="form-control form-control-sm cell-full" required>
                                    <input type="time" name="event_time" value="{{ $reservation->event_time }}" class="form-control form-control-sm cell-full" required>
                                    <button class="btn btn-sm luxury-btn" type="submit">Save</button>
                                </form>
                            @else
                                <span class="d-block">{{ \Carbon\Carbon::parse($reservation->event_date)->format('M j, Y') }}</span>
                                <small class="text-muted">{{ $reservation->event_time }}</small>
                            @endif
                        </td>
                        <td><strong>{{ $reservation->guest_count }}</strong></td>
                        <td>
                            <div class="contract-cell">
                                @forelse($reservation->contractFiles() as $contractIndex => $contractPath)
                                    <div class="contract-item">
                                        <a class="contract-view-link" href="{{ asset('storage/' . $contractPath) }}" target="_blank" rel="noopener">View {{ $contractIndex + 1 }}</a>
                                        <form method="POST" action="{{ route('admin.reservations.contract.delete', [$reservation, $contractIndex]) }}">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="contract-delete" title="Delete contract image" aria-label="Delete contract image">×</button>
                                        </form>
                                    </div>
                                @empty
                                    <span class="contract-none">None</span>
                                @endforelse

                                <form method="POST" action="{{ route('admin.reservations.contract', $reservation) }}" enctype="multipart/form-data" class="contract-upload-form">
                                    @csrf
                                    <label class="contract-file-picker">
                                        <span>Upload</span>
                                        <input type="file" name="service_contract[]" accept="image/jpeg,image/png,image/webp" onchange="this.form.submit()" multiple required>
                                    </label>
                                </form>
                            </div>
                        </td>
                        <td>
                            <div class="status-cell">
                                <span class="status-badge status-badge--{{ $reservation->status }}">{{ $statusLabel }}</span>
                                <form method="POST" action="{{ route('admin.reservations.status', $reservation) }}" class="cell-form" data-confirm-status>
                                    @csrf @method('PATCH')
                                    <select name="status" class="form-select form-select-sm status-select status-select--{{ $reservation->status }}">
                                        <option value="pending" @selected($reservation->status === 'pending')>Pending</option>
                                        <option value="confirmed" @selected($reservation->status === 'confirmed')>Accepted</option>
                                        <option value="completed" @selected($reservation->status === 'completed')>Completed</option>
                                        <option value="cancelled" @selected($reservation->status === 'cancelled')>Cancelled</option>
                                    </select>
                                    <button class="btn btn-sm luxury-btn" type="submit">Save</button>
                                </form>
                            </div>
                        </td>
                        <td>
                            <form method="POST" action="{{ route('admin.reservations.status', $reservation) }}" class="cell-form">
                                @csrf @method('PATCH')
                                <input type="hidden" name="status" value="{{ $reservation->status }}">
                                <input type="number" name="total_cost" min="0" step="1" value="{{ old('total_cost', $reservation->total_cost) }}" class="form-control form-control-sm cell-full" placeholder="Price" aria-label="Contract price" required>
                                <button class="btn btn-sm luxury-btn" type="submit">Save</button>
                            </form>
                        </td>
                        <td>
                            <form method="POST" action="{{ route('admin.reservations.status', $reservation) }}" class="cell-form">
                                @csrf @method('PATCH')
                                <input type="hidden" name="status" value="{{ $reservation->status }}">
                                <select name="payment_type" class="form-select form-select-sm cell-full" aria-label="Payment type">
                                    <option value="Unpaid" @selected($paymentType === 'Unpaid')>Unpaid</option>
                                    <option value="Downpayment" @selected($paymentType === 'Downpayment')>Downpayment</option>
                                    <option value="Full Payment" @selected($paymentType === 'Full Payment')>Full Payment</option>
                                </select>
                                <label class="cell-label" for="amount-paid-{{ $reservation->id }}">Paid</label>
                                <input type="number" id="amount-paid-{{ $reservation->id }}" name="amount_paid" min="0" step="1" value="{{ old('amount_paid', (int) ($reservation->amount_paid ?? 0)) }}" class="form-control form-control-sm" placeholder="0">
                                <button class="btn btn-sm luxury-btn" type="submit">Save</button>
                                @if($outstandingBalance > 0)
                                    <span class="cell-note payment-warning" role="alert">Unpaid balance: &#8369;{{ number_format($outstandingBalance, 2) }}</span>
                                @else
                                    <span class="cell-note">Balance: @if($outstandingBalance !== null)&#8369;{{ number_format($outstandingBalance, 2) }}@else Set contract price @endif</span>
                                @endif
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="text-center text-muted py-4">No reservations found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="reservation-mobile-list d-md-none">
        @forelse($reservations as $reservation)
            @php($statusLabel = $reservation->status === 'confirmed' ? 'Accepted' : ucfirst($reservation->status))
            @php($paymentType = $reservation->payment_type ?? $reservation->payment_status ?? 'Unpaid')
            @php($outstandingBalance = $reservation->total_cost === null ? null : max(0, (float) $reservation->total_cost - (float) ($reservation->amount_paid ?? 0)))
            <article class="reservation-mobile-card">
                <div class="d-flex justify-content-between gap-3">
                    <div>
                        <h5 class="mb-1">{{ $reservation->full_name }}</h5>
                        <a class="customer-contact" href="tel:{{ $reservation->contact_number }}">{{ $reservation->contact_number }}</a>
                    </div>
                    <span class="status-badge status-badge--{{ $reservation->status }}">{{ $statusLabel }}</span>
                </div>
                <div class="mobile-event-info">
                    <div><span>Event</span><strong>{{ $reservation->event_type }}</strong></div>
                    <div><span>Date</span><strong>{{ \Carbon\Carbon::parse($reservation->event_date)->format('M j, Y') }}</strong></div>
                    <div><span>Guests</span><strong>{{ $reservation->guest_count }}</strong></div>
                </div>
                <div class="mb-3">
                    <span class="reservation-id-label">Unique ID</span>
                    <div class="fw-semibold">{{ $reservation->reservation_code ?? '—' }}</div>
                </div>
                <a class="customer-contact d-inline-block mb-3" href="mailto:{{ $reservation->email }}">{{ $reservation->email }}</a>
                @include('admin.partials.reservation-actions', ['reservation' => $reservation, 'packages' => $packages, 'mobile' => true])
                <details class="mt-3">
                    <summary>View booking details</summary>
                    <div class="mobile-detail-list">
                        <p><span>Package</span>{{ $reservation->package?->name ?? 'Custom package' }}</p>
                        <p><span>Estimated package total</span>₱{{ number_format($reservation->estimated_budget, 2) }}</p>
                        <p><span>Venue</span>{{ $reservation->venue }}</p>
                        <p><span>Service contract</span>
                            @if($reservation->service_contract)
                                <a class="customer-contact" href="{{ asset('storage/' . $reservation->service_contract) }}" target="_blank" rel="noopener">View image</a>
                            @else
                                <span class="contract-none">None</span>
                            @endif
                        </p>
                    </div>
                </details>
            </article>
        @empty
            <div class="text-center text-muted py-4">No reservations found.</div>
        @endforelse
    </div>
    </div>
</div>

<style>
    .reservation-stat { height: 100%; padding: 1rem 1.15rem; border: 1px solid var(--line); border-radius: var(--radius); background: var(--surface); box-shadow: var(--shadow); }
    .reservation-stat span, .reservation-stat small { display: block; }
    .reservation-stat span, .mobile-event-info span, .mobile-detail-list span { font-size: .68rem; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; }
    .reservation-stat strong { display: block; margin: .2rem 0; font-size: 1.7rem; line-height: 1.1; }
    .reservation-stat small, .mobile-event-info span, .mobile-detail-list span { color: var(--muted); }
    .reservation-stat--customers { border-left: 4px solid #5279a8; }
    .reservation-stat--pending { border-left: 4px solid #d49b28; }
    .reservation-stat--accepted { border-left: 4px solid #21895b; }
    .reservation-stat--cancelled { border-left: 4px solid #c74e4e; }
    .reservation-match-count { margin-bottom: .5rem; }
    .customer-contact { display: block; color: var(--teal); font-size: .8rem; text-decoration: none; overflow-wrap: anywhere; }
    .reservation-mobile-card { margin-bottom: .75rem; padding: 1rem; border: 1px solid var(--line); border-radius: var(--radius); background: var(--surface); }
    .reservation-mobile-card h5 { font-size: 1rem; }
    .mobile-event-info { display: grid; grid-template-columns: repeat(3, 1fr); gap: .5rem; padding: .8rem 0; }
    .mobile-event-info strong { display: block; margin-top: .15rem; font-size: .82rem; }
    .mobile-detail-list { padding-top: .75rem; }
    .mobile-detail-list p { margin: 0 0 .65rem; }
    .mobile-detail-list p:last-child { margin: 0; }
    .reservation-mobile-card summary { color: var(--teal); font-weight: 700; cursor: pointer; }
    .reservation-actions { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: .5rem; padding-top: .75rem; border-top: 1px solid var(--line); }
    .reservation-actions form { display: flex; gap: .5rem; }
    .reservation-action-group { display: flex; min-width: 0; flex-direction: column; gap: .3rem; }
    .reservation-action-label { color: var(--muted); font-size: .64rem; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
    .reservation-action-group > form { display: flex; gap: .5rem; }
    .reservation-action-group .form-select { width: auto; }
    .payment-warning { color: #8a5a00; font-weight: 800; }
    body.dark-mode .payment-warning { color: #f7d57a; }
    .reservation-action-group > .payment-warning { padding: .35rem .55rem; border: 1px solid #edc467; border-radius: 7px; background: #fff7df; font-size: .72rem; }
    body.dark-mode .reservation-action-group > .payment-warning { border-color: rgba(247, 213, 122, .45); background: rgba(146, 99, 0, .28); }

    /* Desktop table: readable type, compact controls, scrolls inside its card only when space runs out. */
    .reservations-table { min-width: 1100px; table-layout: fixed; }
    .reservations-table thead th { white-space: normal; }
    .reservations-table th, .reservations-table td { padding-right: .45rem; padding-left: .45rem; }
    .reservations-table td { font-size: .78rem; vertical-align: middle; overflow-wrap: break-word; }
    .reservations-table td:first-child, .reservations-table th:first-child { padding-left: .85rem; }
    .reservation-code { font-size: .74rem; font-weight: 700; letter-spacing: .01em; white-space: nowrap; }
    .reservations-table .btn-sm { padding-right: .5rem; padding-left: .5rem; }
    .reservations-table .customer-contact { font-size: .76rem; }
    .reservations-table .customer-email { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .reservations-table .cell-form > .btn { margin-left: auto; }
    .cell-label { color: var(--muted); font-size: .66rem; font-weight: 800; letter-spacing: .04em; text-transform: uppercase; }
    .status-cell { display: flex; flex-direction: column; align-items: stretch; gap: .3rem; }
    .status-cell .status-badge { align-self: flex-start; }
    .contract-cell { display: flex; min-width: 0; flex-direction: column; align-items: flex-start; gap: .3rem; }
    .contract-upload-form { display: flex; margin: 0; }
    .contract-file-picker { position: relative; display: inline-flex; height: 24px; align-items: center; justify-content: center; padding: 0 .55rem; border: 1px solid var(--line); border-radius: 999px; background: var(--surface); color: var(--ink); font-size: .68rem; font-weight: 700; white-space: nowrap; cursor: pointer; }
    .contract-file-picker:hover { border-color: #aebdc4; }
    .contract-file-picker input { position: absolute; width: 1px; height: 1px; overflow: hidden; opacity: 0; }
    .contract-item { display: inline-flex; align-items: center; gap: .2rem; }
    .contract-item form { margin: 0; }
    .contract-delete { padding: 0 .15rem; border: 0; background: transparent; color: #c74e4e; font-size: .95rem; line-height: 1; cursor: pointer; }
    .contract-delete:hover { color: #8d2020; }
    .contract-view-link { color: var(--teal-dark); font-size: .72rem; font-weight: 700; text-decoration: none; white-space: nowrap; }
    .contract-view-link:hover { text-decoration: underline; }
    .contract-none { display: inline-flex; height: 22px; align-items: center; padding: 0 .5rem; border: 1px solid var(--line); border-radius: 999px; color: var(--muted); font-size: .66rem; font-weight: 700; }

    @media (max-width: 767.98px) {
        .reservation-action-group, .reservation-action-group > form { width: 100%; }
        .reservation-action-group .form-select, .reservation-action-group .form-control { width: 100%; min-width: 0; }
        .reservation-actions { display: block; }
        .reservation-actions > * + * { margin-top: .6rem; }
        .reservation-actions form { display: flex; width: 100%; }
    }
</style>
@endsection
