@extends('layouts.admin')

@section('content')
<div class="content-card p-4">
    <div class="d-flex flex-column flex-md-row justify-content-between gap-3 mb-4 align-items-stretch">
        <div>
            <h1 class="fw-bold mb-1">Reservations</h1>
            <p class="text-muted mb-0">Review customer information, then accept, cancel, or update each booking.</p>
        </div>
        <div class="d-flex flex-wrap gap-2 align-self-md-start">
            <a class="btn luxury-btn" href="{{ route('admin.reservations.create') }}">Add reservation</a>
            <a class="btn btn-outline-secondary" href="{{ route('admin.reservations') }}">Refresh bookings</a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <form id="reservation-filter-form" method="GET" action="{{ route('admin.reservations') }}" class="row g-3 align-items-end mb-4 reservation-filter" data-live-filter data-live-filter-target="#reservation-results">
        <div class="col-md-4 col-xl-3">
            <label class="form-label fw-semibold mb-1">Customer</label>
            <input type="search" name="search" class="form-control" value="{{ old('search', $search ?? '') }}" placeholder="Name, email, phone, package, event, ID">
        </div>
        <div class="col-md-3 col-xl-2">
            <label class="form-label fw-semibold mb-1">From date</label>
            <input type="date" name="date_from" class="form-control" value="{{ old('date_from', $dateFrom ?? '') }}">
        </div>
        <div class="col-md-3 col-xl-2">
            <label class="form-label fw-semibold mb-1">To date</label>
            <input type="date" name="date_to" class="form-control" value="{{ old('date_to', $dateTo ?? '') }}">
        </div>
        <div class="col-md-4 col-xl-2">
            <label class="form-label fw-semibold mb-1">Reservation status</label>
            <select name="status" class="form-select">
                <option value="">All statuses</option>
                <option value="pending" @selected($status === 'pending')>Pending</option>
                <option value="confirmed" @selected($status === 'confirmed')>Accepted</option>
                <option value="completed" @selected($status === 'completed')>Completed</option>
                <option value="cancelled" @selected($status === 'cancelled')>Cancelled</option>
            </select>
        </div>
        <div class="col-md-4 col-xl-2">
            <label class="form-label fw-semibold mb-1">Payment status</label>
            <select name="payment_status" class="form-select">
                <option value="">All payments</option>
                <option value="Unpaid" @selected($paymentStatus === 'Unpaid')>Unpaid</option>
                <option value="Downpayment" @selected($paymentStatus === 'Downpayment')>Downpayment</option>
                <option value="Fully Paid" @selected($paymentStatus === 'Fully Paid')>Fully Paid</option>
            </select>
        </div>
        <div class="col-md-4 col-xl-2 d-flex gap-2 reservation-filter-actions">
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
                <col style="width:8%"><col style="width:14%"><col style="width:9%"><col style="width:9%"><col style="width:10%">
                <col style="width:5%"><col style="width:7%"><col style="width:11%"><col style="width:10%"><col style="width:17%">
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
                            <div class="fw-semibold text-break">{{ $reservation->reservation_code ?? '—' }}</div>
                        </td>
                        <td>
                            <strong>{{ $reservation->full_name }}</strong><br>
                            <a class="customer-contact" href="mailto:{{ $reservation->email }}">{{ $reservation->email }}</a><br>
                            <a class="customer-contact" href="tel:{{ $reservation->contact_number }}">{{ $reservation->contact_number }}</a>
                        </td>
                        <td>
                            @if($reservation->status === 'confirmed')
                                <form method="POST" action="{{ route('admin.reservations.status', $reservation) }}" class="schedule-edit-form" data-confirm-message="Update the package for this reservation?">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="status" value="confirmed">
                                    <select name="package_id" class="form-select form-select-sm">
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
                            {{ $reservation->event_type }}<br>
                            <small class="text-muted">{{ $reservation->venue }}</small>
                        </td>
                        <td>
                            @if($reservation->status === 'confirmed')
                                <form method="POST" action="{{ route('admin.reservations.status', $reservation) }}" class="schedule-edit-form" data-confirm-message="Update the schedule for this reservation?">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="status" value="confirmed">
                                    <input type="date" name="event_date" value="{{ $reservation->event_date }}" class="form-control form-control-sm" required>
                                    <input type="time" name="event_time" value="{{ $reservation->event_time }}" class="form-control form-control-sm" required>
                                    <button class="btn btn-sm luxury-btn" type="submit">Save</button>
                                </form>
                            @else
                                {{ \Carbon\Carbon::parse($reservation->event_date)->format('M j, Y') }}<br>
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
                                <form method="POST" action="{{ route('admin.reservations.status', $reservation) }}" data-confirm-status>
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
                            <form method="POST" action="{{ route('admin.reservations.status', $reservation) }}" class="payment-form">
                                @csrf @method('PATCH')
                                <input type="hidden" name="status" value="{{ $reservation->status }}">
                                <div class="payment-stack">
                                    <input type="number" name="total_cost" min="0" step="1" value="{{ old('total_cost', $reservation->total_cost) }}" class="form-control form-control-sm" placeholder="Enter contract price" required>
                                    <button class="btn btn-sm luxury-btn" type="submit">Save</button>
                                </div>
                            </form>
                        </td>
                        <td>
                            <form method="POST" action="{{ route('admin.reservations.status', $reservation) }}" class="payment-form">
                                @csrf @method('PATCH')
                                <input type="hidden" name="status" value="{{ $reservation->status }}">
                                <div class="payment-stack">
                                    <select name="payment_type" class="form-select form-select-sm">
                                        <option value="Unpaid" @selected($paymentType === 'Unpaid')>Unpaid</option>
                                        <option value="Downpayment" @selected($paymentType === 'Downpayment')>Downpayment</option>
                                        <option value="Full Payment" @selected($paymentType === 'Full Payment')>Full Payment</option>
                                    </select>
                                    <label class="payment-field-label">Paid</label>
                                    <input type="number" name="amount_paid" min="0" step="1" value="{{ old('amount_paid', (int) ($reservation->amount_paid ?? 0)) }}" class="form-control form-control-sm" placeholder="0">
                                    <small class="payment-balance">Balance: @if($outstandingBalance !== null)&#8369;{{ number_format($outstandingBalance, 2) }}@else Set contract price @endif</small>
                                    @if($outstandingBalance > 0)
                                        <div class="payment-warning" role="alert">Unpaid balance: &#8369;{{ number_format($outstandingBalance, 2) }}</div>
                                    @endif
                                    <button class="btn btn-sm luxury-btn" type="submit">Save</button>
                                </div>
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
    .reservation-stat { height: 100%; padding: 1rem 1.1rem; border: 1px solid var(--line); border-radius: 12px; background: var(--surface); }
    .reservation-stat span, .reservation-stat small { display: block; }
    .reservation-stat span, .mobile-event-info span, .mobile-detail-list span { font-size: .68rem; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; }
    .reservation-stat strong { display: block; font-size: 1.7rem; line-height: 1.1; margin: .22rem 0; }
    .reservation-stat small, .mobile-event-info span, .mobile-detail-list span { color: var(--muted); }
    .reservation-stat--customers { border-left: 4px solid #5279a8; }
    .reservation-stat--pending { border-left: 4px solid #d49b28; }
    .reservation-stat--accepted { border-left: 4px solid #21895b; }
    .reservation-stat--cancelled { border-left: 4px solid #c74e4e; }
    .status-badge { display: inline-block; height: max-content; padding: .35rem .65rem; border-radius: 999px; font-size: .75rem; font-weight: 800; }
    .status-badge--pending { color: #714d00; background: #fff1c9; }
    .status-badge--confirmed { color: #0a5a35; background: #d9f7e6; }
    .status-badge--completed { color: #164f85; background: #dceeff; }
    .status-badge--cancelled { color: #8d2020; background: #ffe0e0; }
    body.dark-mode .status-badge--pending { color: #f7d57a; background: rgba(146, 99, 0, 0.28); border: 1px solid rgba(247, 213, 122, 0.45); }
    body.dark-mode .status-badge--confirmed { color: #9ae3b7; background: rgba(24, 96, 64, 0.34); border: 1px solid rgba(154, 227, 183, 0.45); }
    body.dark-mode .status-badge--completed { color: #9ad0ff; background: rgba(24, 76, 128, 0.38); border: 1px solid rgba(154, 208, 255, 0.45); }
    body.dark-mode .status-badge--cancelled { color: #ffb0b0; background: rgba(127, 34, 34, 0.34); border: 1px solid rgba(255, 176, 176, 0.4); }
    .customer-contact { font-size: .82rem; color: var(--teal); text-decoration: none; }
    .reservation-mobile-card { padding: 1rem; margin-bottom: .75rem; border: 1px solid var(--line); border-radius: 12px; background: var(--surface); }
    .reservation-mobile-card h5 { font-size: 1rem; }
    .mobile-event-info { display: grid; grid-template-columns: repeat(3, 1fr); gap: .5rem; padding: .8rem 0; }
    .mobile-event-info strong { display: block; font-size: .82rem; margin-top: .15rem; }
    .mobile-detail-list { padding-top: .75rem; }
    .mobile-detail-list p { margin: 0 0 .65rem; }
    .mobile-detail-list p:last-child { margin: 0; }
    .reservation-mobile-card summary { cursor: pointer; font-weight: 700; color: var(--teal); }
    .reservation-actions { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: .5rem; padding-top: .75rem; border-top: 1px solid var(--line); }
    .reservation-actions form { display: flex; gap: .5rem; }
    .reservation-actions .form-select { width: auto; }
    .reservation-action-group { display: flex; flex-direction: column; gap: .3rem; min-width: 0; }
    .reservation-action-label { font-size: .64rem; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; color: var(--muted); }
    .reservation-action-group > form { display: flex; gap: .5rem; }
    .reservation-action-group .form-select { width: auto; }
    .contract-upload-form { display: flex; align-items: center; gap: .4rem; }
    .contract-file-picker { position: relative; display: inline-flex; align-items: center; justify-content: center; min-height: 26px; padding: .24rem .45rem; border: 1px solid var(--line); border-radius: 7px; background: var(--surface); color: var(--ink); font-size: .6rem; font-weight: 700; white-space: nowrap; cursor: pointer; }
    .contract-file-picker input { position: absolute; width: 1px; height: 1px; opacity: 0; overflow: hidden; }
    .contract-item { display: inline-flex; align-items: center; gap: .25rem; }
    .contract-delete { border: 0; background: transparent; color: #c74e4e; font-size: .95rem; line-height: 1; cursor: pointer; padding: 0 .15rem; }
    .contract-delete:hover { color: #8d2020; }
    .contract-view-link { font-size: .6rem; color: var(--teal-dark); font-weight: 700; text-decoration: none; }
    .contract-view-link:hover { text-decoration: underline; }
    .contract-none { display: inline-flex; padding: .18rem .38rem; border: 1px solid var(--line); border-radius: 999px; color: var(--muted); font-size: .6rem; font-weight: 700; }
    .contract-cell { display: flex; align-items: flex-start; flex-direction: column; gap: .18rem; min-width: 0; }
    .status-cell { display: flex; align-items: center; gap: .4rem; flex-wrap: wrap; }
    .status-cell form { display: flex; align-items: center; gap: .35rem; min-width: 0; }
    .status-cell .status-select { width: 116px; min-height: 31px; padding: .3rem 1.7rem .3rem .55rem; border-width: 1px; border-radius: 7px; font-size: .72rem; font-weight: 800; line-height: 1.2; }
    .status-select--pending { color: #714d00; border-color: #e6bd52; background-color: #fff8e1; }
    .status-select--confirmed { color: #0a5a35; border-color: #8fd4ab; background-color: #e7f9ed; }
    .status-select--completed { color: #164f85; border-color: #93c4ed; background-color: #eaf5ff; }
    .status-select--cancelled { color: #8d2020; border-color: #efa6a6; background-color: #fff0f0; }
    body.dark-mode .status-select--pending { color: #f7d57a; border-color: rgba(247, 213, 122, .45); background-color: rgba(146, 99, 0, .28); }
    body.dark-mode .status-select--confirmed { color: #9ae3b7; border-color: rgba(154, 227, 183, .45); background-color: rgba(24, 96, 64, .34); }
    body.dark-mode .status-select--completed { color: #9ad0ff; border-color: rgba(154, 208, 255, .45); background-color: rgba(24, 76, 128, .38); }
    body.dark-mode .status-select--cancelled { color: #ffb0b0; border-color: rgba(255, 176, 176, .4); background-color: rgba(127, 34, 34, .34); }
    .status-cell .btn { height: 31px; padding: .3rem .55rem; font-size: .7rem; }
    .payment-stack { display: flex; flex-direction: column; gap: .35rem; min-width: 150px; }
    .payment-field-label { font-size: .6rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: var(--muted); }
    .payment-balance { font-size: .72rem; color: var(--muted); }
    .payment-warning { padding: .4rem .55rem; border: 1px solid #edc467; border-radius: 7px; background: #fff7df; color: #704d00; font-size: .72rem; font-weight: 800; line-height: 1.3; }
    body.dark-mode .payment-warning { border-color: rgba(247, 213, 122, .45); background: rgba(146, 99, 0, .28); color: #f7d57a; }
    .schedule-edit-form { display: flex; flex-direction: column; gap: .35rem; min-width: 130px; }
    .payment-form { display: flex; }
    .reservation-table-container { width: 100%; max-width: 100%; overflow-x: hidden; overflow-x: clip; }
    .reservations-table { width: 100%; max-width: 100%; table-layout: fixed; border-collapse: collapse; }
    .reservations-table th, .reservations-table td { min-width: 0; padding: .42rem .3rem; white-space: normal; overflow-wrap: anywhere; vertical-align: top; font-size: .72rem; }
    .reservations-table th { font-size: .62rem; line-height: 1.2; }
    .reservations-table td strong, .reservations-table .customer-contact { overflow-wrap: anywhere; word-break: break-word; }
    .reservations-table .customer-contact { font-size: .68rem; }
    .reservations-table .form-control, .reservations-table .form-select { width: 100%; min-width: 0; max-width: 100%; padding: .28rem .3rem; border-radius: 6px; font-size: .68rem; line-height: 1.2; }
    .reservations-table .schedule-edit-form, .reservations-table .payment-form, .reservations-table .payment-stack { width: 100%; min-width: 0; }
    .reservations-table .schedule-edit-form, .reservations-table .payment-stack { gap: .22rem; }
    .reservations-table .btn { width: 100%; min-width: 0; min-height: 25px; padding: .22rem .15rem; font-size: .63rem; line-height: 1.1; white-space: normal; }
    .reservations-table .status-cell { align-items: stretch; flex-direction: column; gap: .25rem; }
    .reservations-table .status-badge { padding: .25rem .2rem; font-size: .62rem; line-height: 1.15; text-align: center; white-space: normal; }
    .reservations-table .status-cell form { display: flex; flex-direction: column; align-items: stretch; gap: .2rem; width: 100%; }
    .reservations-table .status-cell .status-select { width: 100%; min-height: 26px; padding: .25rem 1.1rem .25rem .3rem; font-size: .64rem; }
    .reservations-table .status-cell .btn { height: auto; }
    .reservations-table .contract-cell { width: 100%; }
    .reservations-table .contract-item { max-width: 100%; flex-wrap: wrap; }
    .reservations-table .contract-file-picker { width: 100%; max-width: 100%; min-height: 24px; padding: .2rem; white-space: normal; }
    .reservations-table .contract-view-link { overflow-wrap: anywhere; }
    .reservations-table .payment-field-label, .reservations-table .payment-balance { overflow-wrap: anywhere; }
    .reservations-table .payment-warning { padding: .28rem; font-size: .62rem; overflow-wrap: anywhere; }
    .reservations-table .payment-stack .payment-balance { font-size: .62rem; }
    .reservation-filter.row { display: grid; grid-template-columns: minmax(190px, 2fr) repeat(4, minmax(135px, 1fr)) minmax(140px, auto); gap: .75rem; margin: 0 0 1.5rem; }
    .reservation-filter > [class*="col-"] { width: auto; max-width: none; padding: 0; flex: none; }
    .reservation-filter-actions { min-width: 0; }
    @media (max-width: 767px) {
        .reservation-filter.row { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .reservation-filter-actions { grid-column: 1 / -1; }
        .reservation-action-group { width: 100%; }
        .reservation-action-group > form { width: 100%; }
        .reservation-action-group .form-select, .contract-upload-form .form-control, .payment-stack .form-select, .payment-stack .form-control { width: 100%; min-width: 0; }
        .reservation-actions { display: block; }
        .reservation-actions form { display: flex; width: 100%; }
        .reservation-actions .btn { width: auto; }
        .payment-stack { min-width: 0; }
    }
    @media (min-width: 768px) and (max-width: 1199px) {
        .reservation-filter.row { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .reservation-filter-actions { grid-column: span 1; }
    }
    @media (max-width: 480px) {
        .reservation-filter.row { grid-template-columns: minmax(0, 1fr); }
        .reservation-filter-actions { grid-column: auto; }
    }
</style>
@endsection
