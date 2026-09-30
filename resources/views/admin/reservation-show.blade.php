@extends('layouts.admin')

@php
    $statusLabel = $reservation->status === 'confirmed' ? 'Accepted' : ucfirst($reservation->status);
    $financials = $reservation->financials();
    $outstandingBalance = $financials['remaining_balance_cents'] === null ? null : $financials['remaining_balance_cents'] / 100;
    $peso = fn ($amount) => '&#8369;'.number_format((float) $amount, 2);
@endphp

@section('content')
<div class="content-card p-4">
    <div class="page-header">
        <div>
            <a class="back-link" href="{{ route('admin.reservations') }}">&larr; Back to reservations</a>
            <h1 class="fw-bold mb-1">{{ $reservation->full_name }}</h1>
            <p class="text-muted mb-0">{{ $reservation->reservation_code ?? 'No reservation code' }} &middot; {{ $reservation->event_type }} on {{ \Carbon\Carbon::parse($reservation->event_date)->format('M j, Y') }}</p>
        </div>
        <span class="status-badge status-badge--{{ $reservation->status }} reservation-show-status">{{ $statusLabel }}</span>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <div class="row g-3">
        <div class="col-lg-6">
            <section class="card h-100">
                <h2 class="h6 fw-bold mb-3">Customer</h2>
                <dl class="detail-list">
                    <div><dt>Name</dt><dd>{{ $reservation->full_name }}</dd></div>
                    <div><dt>Phone</dt><dd><a href="tel:{{ $reservation->contact_number }}">{{ $reservation->contact_number }}</a></dd></div>
                    <div><dt>Email</dt><dd><a href="mailto:{{ $reservation->email }}">{{ $reservation->email }}</a></dd></div>
                    <div><dt>Address</dt><dd>{{ $reservation->address }}</dd></div>
                </dl>
            </section>
        </div>
        <div class="col-lg-6">
            <section class="card h-100">
                <h2 class="h6 fw-bold mb-3">Event</h2>
                <dl class="detail-list">
                    <div><dt>Event type</dt><dd>{{ $reservation->event_type }}</dd></div>
                    <div><dt>Date</dt><dd>{{ \Carbon\Carbon::parse($reservation->event_date)->format('F j, Y') }}</dd></div>
                    <div><dt>Time</dt><dd>{{ $reservation->event_time }}</dd></div>
                    <div><dt>Venue</dt><dd>{{ $reservation->venue }}</dd></div>
                    <div><dt>Guests</dt><dd>{{ number_format($reservation->guest_count) }}</dd></div>
                </dl>
            </section>
        </div>

        <div class="col-lg-6">
            <section class="card h-100">
                <h2 class="h6 fw-bold mb-3">Package</h2>
                <dl class="detail-list">
                    <div><dt>Package</dt><dd>{{ $reservation->package?->name ?? 'Custom package' }}</dd></div>
                    <div><dt>Additional services</dt><dd>{{ $reservation->additional_services ?: '—' }}</dd></div>
                    <div><dt>Special requests</dt><dd>{{ $reservation->special_requests ?: '—' }}</dd></div>
                    <div><dt>Additional notes (from guest)</dt><dd>{{ $reservation->additional_notes ?: '—' }}</dd></div>
                    <div><dt>Estimated total</dt><dd>{!! $peso($reservation->estimated_budget) !!}</dd></div>
                </dl>
            </section>
        </div>
        <div class="col-lg-6">
            <section class="card h-100">
                <h2 class="h6 fw-bold mb-3">Status</h2>
                <div class="reservation-timeline reservation-timeline--admin {{ $reservation->status === 'cancelled' ? 'reservation-timeline--cancelled' : '' }}">
                    @foreach($reservation->timelineSteps() as $step)
                        <div class="timeline-step timeline-step--{{ $step['state'] }}">
                            <span class="timeline-step-marker" aria-hidden="true">
                                @if($step['state'] === 'complete') &#10003;
                                @elseif($step['state'] === 'current') &#9679;
                                @elseif($step['state'] === 'cancelled') &#10007;
                                @endif
                            </span>
                            <div class="timeline-step-body">
                                <div class="timeline-step-label">{{ $step['label'] }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="reservation-actions-group">
                    @if($reservation->status === 'pending')
                        <form method="POST" action="{{ route('admin.reservations.status', $reservation) }}" data-confirm-message="Accept this reservation? The change will be saved immediately.">@csrf @method('PATCH')<input type="hidden" name="status" value="confirmed"><button class="btn btn-sm btn-success" type="submit">Accept</button></form>
                        <form method="POST" action="{{ route('admin.reservations.status', $reservation) }}" data-confirm-message="Cancel this reservation? The change will be saved immediately.">@csrf @method('PATCH')<input type="hidden" name="status" value="cancelled"><button class="btn btn-sm btn-danger" type="submit">Cancel</button></form>
                    @elseif($reservation->status === 'confirmed')
                        <form method="POST" action="{{ route('admin.reservations.status', $reservation) }}" data-confirm-message="Mark this reservation as completed?">@csrf @method('PATCH')<input type="hidden" name="status" value="completed"><button class="btn btn-sm luxury-btn" type="submit">Complete</button></form>
                        <form method="POST" action="{{ route('admin.reservations.status', $reservation) }}" data-confirm-message="Cancel this reservation? The change will be saved immediately.">@csrf @method('PATCH')<input type="hidden" name="status" value="cancelled"><button class="btn btn-sm btn-danger" type="submit">Cancel</button></form>
                    @endif
                </div>

                <details class="reservation-status-override">
                    <summary>Change status manually</summary>
                    <form method="POST" action="{{ route('admin.reservations.status', $reservation) }}" class="d-flex gap-2 mt-2" data-confirm-status>
                        @csrf @method('PATCH')
                        <select name="status" class="form-select form-select-sm status-select status-select--{{ $reservation->status }}">
                            <option value="pending" @selected($reservation->status === 'pending')>Pending</option>
                            <option value="confirmed" @selected($reservation->status === 'confirmed')>Accepted</option>
                            <option value="completed" @selected($reservation->status === 'completed')>Completed</option>
                            <option value="cancelled" @selected($reservation->status === 'cancelled')>Cancelled</option>
                        </select>
                        <button class="btn btn-sm btn-outline-secondary" type="submit">Save</button>
                    </form>
                </details>

                <div class="reservation-schedule-group">
                    <h3 class="reservation-subheading">Schedule</h3>
                    @if($reservation->status === 'confirmed')
                        <form method="POST" action="{{ route('admin.reservations.status', $reservation) }}" data-confirm-message="Update this reservation's schedule and package?">
                            @csrf @method('PATCH')
                            <input type="hidden" name="status" value="confirmed">
                            <div class="mb-2">
                                <label class="form-label" for="schedule-package">Package</label>
                                <select id="schedule-package" name="package_id" class="form-select form-select-sm">
                                    @foreach($packages as $package)
                                        <option value="{{ $package->id }}" @selected($reservation->package_id === $package->id)>{{ $package->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="row g-2 mb-2">
                                <div class="col-6">
                                    <label class="form-label" for="schedule-date">Date</label>
                                    <input id="schedule-date" type="date" name="event_date" value="{{ $reservation->event_date }}" class="form-control form-control-sm" required>
                                </div>
                                <div class="col-6">
                                    <label class="form-label" for="schedule-time">Time</label>
                                    <input id="schedule-time" type="time" name="event_time" value="{{ $reservation->event_time }}" class="form-control form-control-sm" required>
                                </div>
                            </div>
                            <button class="btn btn-sm luxury-btn" type="submit">Save schedule</button>
                        </form>
                    @else
                        <p class="text-muted small mb-0">Schedule and package can be edited once this reservation is accepted.</p>
                    @endif
                </div>
            </section>
        </div>

        <div class="col-lg-6">
            <section class="card h-100">
                <h2 class="h6 fw-bold mb-3">Contract</h2>
                <div class="contract-detail-list">
                    @forelse($reservation->contractFiles() as $contractIndex => $contractPath)
                        <div class="contract-detail-item">
                            <a href="{{ asset('storage/'.$contractPath) }}" target="_blank" rel="noopener">View contract {{ $contractIndex + 1 }}</a>
                            <form method="POST" action="{{ route('admin.reservations.contract.delete', [$reservation, $contractIndex]) }}" data-confirm-message="Delete this contract image?">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                            </form>
                        </div>
                    @empty
                        <p class="text-muted small mb-0">No contract files uploaded yet.</p>
                    @endforelse
                </div>
                <form method="POST" action="{{ route('admin.reservations.contract', $reservation) }}" enctype="multipart/form-data" class="mt-3">
                    @csrf
                    <label class="form-label" for="contract-upload">Upload contract image(s)</label>
                    <input id="contract-upload" class="form-control form-control-sm mb-2" type="file" name="service_contract[]" accept="image/jpeg,image/png,image/webp" multiple required>
                    <button class="btn btn-sm luxury-btn" type="submit">Upload</button>
                </form>
            </section>
        </div>
        <div class="col-lg-6">
            <section class="card h-100">
                <h2 class="h6 fw-bold mb-3">Payment</h2>
                <div class="summary-grid summary-grid--compact">
                    <div class="summary-item summary-item--accent"><span>Contract amount</span><strong>{!! $reservation->total_cost !== null ? $peso($reservation->total_cost) : 'Not set' !!}</strong></div>
                    <div class="summary-item"><span>Paid</span><strong>{!! $peso($financials['gross_paid_cents'] / 100) !!}</strong></div>
                    <div class="summary-item"><span>Refunded</span><strong>{!! $peso($financials['total_refunded_cents'] / 100) !!}</strong></div>
                    <div class="summary-item {{ ($outstandingBalance ?? 0) > 0 ? 'summary-item--warn' : '' }}"><span>Balance</span><strong>{!! $outstandingBalance !== null ? $peso($outstandingBalance) : '—' !!}</strong></div>
                    <div class="summary-item"><span>Payment status</span><strong><span class="status-badge status-badge--{{ \App\Models\Reservation::paymentStatusBadge($reservation->payment_status) }}">{{ \App\Models\Reservation::paymentStatusLabel($reservation->payment_status) }}</span></strong></div>
                </div>
                <a class="btn btn-sm btn-outline-secondary mt-2" href="{{ route('admin.reservations.payments', $reservation) }}">View payment history</a>
            </section>
        </div>

        <div class="col-12">
            <section class="card">
                <h2 class="h6 fw-bold mb-3">Notes</h2>
                <p class="text-muted small mb-2">Internal notes are only visible to admins.</p>
                <form method="POST" action="{{ route('admin.reservations.status', $reservation) }}">
                    @csrf @method('PATCH')
                    <textarea name="admin_notes" class="form-control mb-2" rows="3" placeholder="Add an internal note...">{{ old('admin_notes', $reservation->admin_notes) }}</textarea>
                    <button class="btn btn-sm luxury-btn" type="submit">Save note</button>
                </form>
            </section>
        </div>

        <div class="col-12">
            <section class="card">
                <h2 class="h6 fw-bold mb-3">Activity</h2>
                @forelse($activity as $entry)
                    <div class="activity-entry">
                        <div class="activity-entry-meta"><strong>{{ $entry->actor_name ?? 'Unknown administrator' }}</strong> &middot; {{ \Carbon\Carbon::parse($entry->activity_date.' '.$entry->activity_time)->format('M j, Y g:i A') }}</div>
                        <p class="mb-0">{{ $entry->description }}</p>
                    </div>
                @empty
                    <p class="text-muted small mb-0">No recorded activity for this reservation yet.</p>
                @endforelse
            </section>
        </div>
    </div>
</div>

<style>
    .detail-list { display: grid; gap: .65rem; margin: 0; }
    .detail-list > div { display: flex; flex-wrap: wrap; gap: .35rem .75rem; }
    .detail-list dt { flex: 0 0 150px; color: var(--muted); font-size: .72rem; font-weight: 800; letter-spacing: .05em; text-transform: uppercase; }
    .detail-list dd { flex: 1 1 200px; margin: 0; overflow-wrap: anywhere; }
    .detail-list dd a { color: var(--teal); text-decoration: none; }
    .reservation-show-status { font-size: .8rem; padding: 0 1rem; height: 30px; }
    .back-link { display: inline-block; margin-bottom: .4rem; color: var(--teal-dark); font-size: .8rem; font-weight: 700; text-decoration: none; }

    .reservation-timeline { display: grid; grid-auto-flow: column; grid-auto-columns: 1fr; margin: 0 0 1.1rem; }
    .reservation-timeline .timeline-step { position: relative; display: flex; flex-direction: column; align-items: center; text-align: center; padding: 0 .3rem; }
    .reservation-timeline .timeline-step-marker { position: relative; z-index: 1; display: grid; place-items: center; width: 28px; height: 28px; flex: 0 0 auto; border-radius: 50%; border: 2px solid var(--line); background: var(--surface); color: #b7b0a4; font-weight: 800; font-size: .82rem; line-height: 1; }
    .reservation-timeline .timeline-step:not(:last-child):before { content: ''; position: absolute; top: 13px; left: calc(50% + 14px); width: calc(100% - 28px); height: 2px; background: var(--line); z-index: 0; }
    .reservation-timeline .timeline-step--complete .timeline-step-marker { background: var(--teal-dark); border-color: var(--teal-dark); color: #fff; }
    .reservation-timeline .timeline-step--complete:not(:last-child):before { background: var(--teal-dark); }
    .reservation-timeline .timeline-step--current .timeline-step-marker { border-color: var(--teal-dark); color: var(--teal-dark); background: var(--surface); box-shadow: 0 0 0 4px rgba(13, 139, 131, .14); }
    .reservation-timeline .timeline-step--cancelled .timeline-step-marker { background: #a73838; border-color: #a73838; color: #fff; }
    .reservation-timeline .timeline-step-label { margin-top: .4rem; font-size: .68rem; font-weight: 800; color: var(--ink); }
    .reservation-timeline .timeline-step--upcoming .timeline-step-label { color: var(--muted); }
    .reservation-timeline .timeline-step--current .timeline-step-label { color: var(--teal-dark); }

    .reservation-actions-group { display: flex; flex-wrap: wrap; gap: .5rem; margin-bottom: .75rem; }
    .reservation-actions-group form { margin: 0; }
    .reservation-status-override { margin-bottom: 1.1rem; padding-bottom: 1.1rem; border-bottom: 1px solid var(--line); }
    .reservation-status-override summary { color: var(--teal-dark); font-size: .78rem; font-weight: 700; cursor: pointer; }
    .reservation-subheading { margin: 0 0 .6rem; font-size: .72rem; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; color: var(--muted); }

    .contract-detail-list { display: grid; gap: .5rem; }
    .contract-detail-item { display: flex; align-items: center; justify-content: space-between; gap: .75rem; padding: .5rem .7rem; border: 1px solid var(--line); border-radius: 8px; }
    .contract-detail-item a { color: var(--teal-dark); font-size: .8rem; font-weight: 700; text-decoration: none; }
    .contract-detail-item form { margin: 0; }

    .summary-grid--compact { grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); margin-bottom: 0; }
    .summary-item--warn { border-left: 4px solid #d49b28; }

    .activity-entry { padding: .65rem 0; border-top: 1px solid var(--line); font-size: .82rem; }
    .activity-entry:first-child { border-top: 0; padding-top: 0; }
    .activity-entry-meta { margin-bottom: .2rem; color: var(--muted); font-size: .7rem; }
</style>
@endsection
