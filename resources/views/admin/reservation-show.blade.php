@extends('layouts.admin')

@php
    $statusLabel = $reservation->status === 'confirmed' ? 'Accepted' : ucfirst($reservation->status);
    $financials = $reservation->financials();
    $outstandingBalance = $financials['remaining_balance_cents'] === null ? null : $financials['remaining_balance_cents'] / 100;
    $peso = fn ($amount) => '&#8369;'.number_format((float) $amount, 2);
@endphp

@section('content')
<div class="content-card p-4 reservation-detail-page">
    <div class="page-header reservation-detail-header">
        <div>
            <a class="back-link" href="{{ route('admin.reservations') }}">&larr; Back to reservations</a>
            <h1 class="fw-bold mb-1">{{ $reservation->full_name }}</h1>
            <p class="text-muted mb-0">{{ $reservation->reservation_code ?? 'No reservation code' }} &middot; {{ $reservation->event_type }} &middot; {{ \Carbon\Carbon::parse($reservation->event_date)->format('M j, Y') }}</p>
            <p class="text-muted small mt-1 mb-0"><span class="fw-semibold">Booked on</span> {{ $reservation->created_at?->timezone(config('app.timezone'))->format('F j, Y \a\t g:i A') ?? '—' }}</p>
        </div>
        <span class="status-badge status-badge--{{ $reservation->status }} reservation-show-status">{{ $statusLabel }}</span>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <div class="row g-3 align-items-start reservation-detail-layout">
        <div class="col-lg-7">
            <section class="card reservation-information-card" id="reservation-information" aria-labelledby="reservation-information-heading">
                <h2 class="h6 fw-bold mb-3" id="reservation-information-heading">Reservation Information</h2>
                <section class="reservation-info-section" aria-labelledby="reservation-customer-heading">
                    <h3 class="reservation-subheading" id="reservation-customer-heading">Customer</h3>
                    <dl class="detail-list">
                        <div><dt>Name</dt><dd>{{ $reservation->full_name }}</dd></div>
                        <div><dt>Phone</dt><dd><a href="tel:{{ $reservation->contact_number }}">{{ $reservation->contact_number }}</a></dd></div>
                        <div><dt>Email</dt><dd><a href="mailto:{{ $reservation->email }}">{{ $reservation->email }}</a></dd></div>
                        <div><dt>Address</dt><dd>{{ $reservation->address }}</dd></div>
                    </dl>
                </section>
                <section class="reservation-info-section" aria-labelledby="reservation-event-heading">
                    <h3 class="reservation-subheading" id="reservation-event-heading">Event</h3>
                    <dl class="detail-list">
                        <div><dt>Event type</dt><dd>{{ $reservation->event_type }}</dd></div>
                        <div><dt>Date</dt><dd>{{ \Carbon\Carbon::parse($reservation->event_date)->format('F j, Y') }}</dd></div>
                        <div><dt>Time</dt><dd>{{ $reservation->event_time }}</dd></div>
                        <div><dt>Venue</dt><dd>{{ $reservation->venue }}</dd></div>
                        <div><dt>Guests</dt><dd>{{ number_format($reservation->guest_count) }}</dd></div>
                    </dl>
                </section>
                <section class="reservation-info-section" aria-labelledby="reservation-package-heading">
                    <h3 class="reservation-subheading" id="reservation-package-heading">Package</h3>
                    <dl class="detail-list">
                        <div><dt>Package</dt><dd>{{ $reservation->package?->name ?? 'Custom package' }}</dd></div>
                        <div><dt>Additional services</dt><dd>{{ $reservation->additional_services ?: '—' }}</dd></div>
                        <div><dt>Special requests</dt><dd>{{ $reservation->special_requests ?: '—' }}</dd></div>
                        <div><dt>Additional notes (from guest)</dt><dd>{{ $reservation->additional_notes ?: '—' }}</dd></div>
                        <div><dt>Estimated total</dt><dd>{!! $peso($reservation->estimated_budget) !!}</dd></div>
                    </dl>
                </section>
                <section class="reservation-info-section reservation-contract-section" aria-labelledby="reservation-contract-heading">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                        <h3 class="reservation-subheading mb-0" id="reservation-contract-heading">Contract</h3>
                        <a class="small" href="{{ route('admin.support') }}#category-admin-contracts">How do contracts work?</a>
                    </div>
                    <div class="contract-detail-list">
                        @forelse($reservation->contractFiles() as $contractIndex => $contractPath)
                            @php($contractExists = \Illuminate\Support\Facades\Storage::disk('local')->exists($contractPath))
                            @php($contractMimeType = $contractExists ? \Illuminate\Support\Facades\Storage::disk('local')->mimeType($contractPath) : null)
                            @php($contractPreviewable = in_array($contractMimeType, ['image/jpeg', 'image/png', 'image/webp'], true))
                            <div class="contract-detail-item">
                                <div class="contract-detail-meta">
                                    <strong>Contract {{ $contractIndex + 1 }}</strong>
                                    <span>{{ basename($contractPath) }}</span>
                                    @unless($contractExists)<small class="text-danger">File is no longer available.</small>@endunless
                                </div>
                                <div class="contract-detail-actions">
                                    @if($contractExists && $contractPreviewable)
                                        <button type="button" class="btn btn-sm btn-outline-secondary" data-contract-preview
                                            data-preview-url="{{ route('admin.reservations.contract.preview', [$reservation, $contractIndex]) }}"
                                            data-download-url="{{ route('admin.reservations.contract.download', [$reservation, $contractIndex]) }}"
                                            data-filename="{{ basename($contractPath) }}"
                                            aria-label="View Contract {{ $contractIndex + 1 }}">View</button>
                                    @elseif($contractExists)
                                        <span class="text-muted small">Preview unavailable</span>
                                    @endif
                                    @if($contractExists)
                                        <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.reservations.contract.download', [$reservation, $contractIndex]) }}">Download</a>
                                    @endif
                                    <form method="POST" action="{{ route('admin.reservations.contract.delete', [$reservation, $contractIndex]) }}" data-confirm-message="Delete this contract image?">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                    </form>
                                </div>
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
            </section>
        </div>
        <div class="col-lg-5 d-flex flex-column gap-3">
            <section class="card reservation-status-card" id="reservation-status" aria-labelledby="reservation-status-heading">
                <h2 class="h6 fw-bold mb-3" id="reservation-status-heading">Status</h2>
                <p class="mb-3">Reservation Status: <span class="status-badge status-badge--{{ $reservation->status }}">{{ \App\Models\Reservation::statusLabel($reservation->status) }}</span></p>
                @php($canManuallyComplete = $reservation->status === \App\Models\Reservation::STATUS_CONFIRMED && \Illuminate\Support\Carbon::parse($reservation->event_date, config('app.timezone'))->toDateString() === now(config('app.timezone'))->toDateString())
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
                        <form method="POST" action="{{ route('admin.reservations.cancel', $reservation) }}" data-confirm-message="Cancel this reservation? The change will be saved immediately.">@csrf<button class="btn btn-sm btn-danger" type="submit">Cancel</button></form>
                    @elseif($reservation->status === 'confirmed')
                        @if($canManuallyComplete)
                            <form method="POST" action="{{ route('admin.reservations.complete', $reservation) }}" data-confirm-message="Mark this reservation as completed?">@csrf<button class="btn btn-sm luxury-btn" type="submit">Complete</button></form>
                        @endif
                        <form method="POST" action="{{ route('admin.reservations.cancel', $reservation) }}" data-confirm-message="Cancel this reservation? The change will be saved immediately.">@csrf<button class="btn btn-sm btn-danger" type="submit">Cancel</button></form>
                    @endif
                </div>
                @if($reservation->status === 'pending')
                    <hr>
                    <h3 class="h6 fw-bold">Set contract and review initial payment</h3>
                    <p class="small text-muted">Enter the agreed contract price, record a downpayment or full payment, and review its receipt before accepting.</p>
                    <form method="POST" enctype="multipart/form-data"
                        id="reservation-acceptance-form"
                        action="{{ route('admin.reservations.accept', $reservation) }}"
                        data-acceptance-form
                        data-receipt-form
                        data-require-receipt-review
                        data-analyze-url="{{ route('admin.reservations.payments.receipt.analyze', $reservation) }}">
                        @csrf
                        <input type="hidden" name="receipt_review_token" value="">
                        <div class="mb-2">
                            <label class="form-label" for="acceptance-contract-price">Final contract price (₱)</label>
                            <input class="form-control form-control-sm" type="number" id="acceptance-contract-price" name="total_cost"
                                value="{{ old('total_cost') }}" min="0.01" max="9999999999" step="0.01" inputmode="decimal" required>
                        </div>
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label class="form-label" for="acceptance-payment-date">Payment date</label>
                                <input class="form-control form-control-sm" type="date" id="acceptance-payment-date" name="payment_date"
                                    value="{{ old('payment_date', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label" for="acceptance-payment-type">Payment type</label>
                                <select class="form-select form-select-sm" id="acceptance-payment-type" name="payment_type" required>
                                    <option value="Downpayment" @selected(old('payment_type', 'Downpayment') === 'Downpayment')>Downpayment</option>
                                    <option value="Full Payment" @selected(old('payment_type') === 'Full Payment')>Full Payment</option>
                                </select>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label" for="acceptance-amount">Payment amount (₱)</label>
                                <input class="form-control form-control-sm" type="number" id="acceptance-amount" name="amount"
                                    value="{{ old('amount') }}" min="0.01" max="9999999999" step="0.01" inputmode="decimal" required>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label" for="acceptance-payment-method">Payment method</label>
                                <select class="form-select form-select-sm" id="acceptance-payment-method" name="payment_method" required>
                                    @foreach(\App\Models\ReservationPayment::METHODS as $method)
                                        <option value="{{ $method }}" @selected(old('payment_method', 'Cash') === $method)>{{ $method }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="mt-2">
                            <label class="form-label" for="acceptance-reference-number">Reference / transaction number <span class="text-muted fw-normal">(optional)</span></label>
                            <input class="form-control form-control-sm" type="text" id="acceptance-reference-number" name="reference_number"
                                value="{{ old('reference_number') }}" maxlength="100" autocomplete="off" placeholder="Enter the transaction reference">
                        </div>
                        <div class="mt-2">
                            <label class="form-label" for="acceptance-receipt-image">Payment receipt image</label>
                            <input class="form-control form-control-sm" type="file" id="acceptance-receipt-image" name="receipt_image"
                                accept="image/jpeg,image/png,image/webp" data-receipt-file required>
                            <div class="form-text">JPG, PNG, or WEBP; maximum 5MB. The receipt is analyzed automatically when selected.</div>
                            <div class="receipt-review mt-3" data-receipt-review hidden>
                                <button type="button" class="receipt-preview-trigger" data-acceptance-receipt-preview data-receipt-preview-trigger
                                    aria-haspopup="dialog" aria-controls="acceptance-receipt-lightbox" hidden aria-label="View selected receipt full size">
                                    <img class="receipt-review-preview" alt="" data-receipt-preview>
                                    <span>Click to view full receipt</span>
                                </button>
                                <button class="btn btn-sm btn-outline-primary mt-2" type="button" data-analyze-receipt>Analyze receipt</button>
                                <button class="btn btn-sm btn-outline-secondary mt-2" type="button" data-clear-receipt>Remove image</button>
                                <p class="small mt-2 mb-2" role="status" aria-live="polite" data-receipt-status></p>
                                <div class="receipt-review-details small" data-receipt-details hidden></div>
                                <label class="form-check mt-2" data-receipt-confirmation hidden>
                                    <input class="form-check-input" type="checkbox" name="receipt_confirmed" value="1">
                                    <span class="form-check-label">I reviewed the extracted details and confirm them before accepting this reservation.</span>
                                </label>
                            </div>
                        </div>
                        <div class="mt-2">
                            <label class="form-label" for="acceptance-payment-notes">Payment notes <span class="text-muted fw-normal">(optional)</span></label>
                            <textarea class="form-control form-control-sm" id="acceptance-payment-notes" name="notes" rows="2" maxlength="1000" placeholder="Reference number, who paid, etc.">{{ old('notes') }}</textarea>
                        </div>
                        <p class="small text-muted mt-2 mb-0" data-acceptance-balance
                            data-paid-cents="{{ $financials['gross_paid_cents'] }}">Enter the contract price and payment to see the remaining balance.</p>
                        <button class="btn btn-sm btn-success mt-3 w-100" type="submit" data-save-payment>Confirm Payment &amp; Accept Reservation</button>
                    </form>
                @endif
            </section>
            <section class="card reservation-payment-card" id="reservation-payment" aria-labelledby="reservation-payment-heading">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                    <h2 class="h6 fw-bold mb-0" id="reservation-payment-heading">Payment</h2>
                    <a class="small" href="{{ route('admin.support') }}#category-admin-payments">How do payments work?</a>
                </div>
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
    </div>
    <div class="row g-3 mt-0">
        <div class="col-12">
            <section class="card reservation-edit-card">
                <details class="reservation-schedule-group" id="reservation-editor" data-reservation-editor @if($errors->any()) open @endif>
                    <summary class="reservation-disclosure-heading">
                        <span>
                            <strong>Edit confirmed reservation</strong>
                            <small>Update event details agreed during the client meeting.</small>
                        </span>
                        <span class="reservation-disclosure-action" aria-hidden="true"><span class="disclosure-label-closed">Edit</span><span class="disclosure-label-open">Close</span><span class="reservation-disclosure-chevron"></span></span>
                    </summary>
                    @if($reservation->status === 'confirmed')
                        <form method="POST" action="{{ route('admin.reservations.update', $reservation) }}" data-confirm-message="Save changes to this confirmed reservation?">
                            @csrf @method('PATCH')
                            <div class="mb-2">
                                <label class="form-label" for="schedule-event-type">Event type</label>
                                <select id="schedule-event-type" name="event_type" class="form-select form-select-sm" required>
                                    @foreach(['Wedding', 'Birthday', 'Debut', 'Anniversary', 'Corporate Event', 'Baptism', 'Graduation', 'Other'] as $eventType)
                                        <option value="{{ $eventType }}" @selected(old('event_type', $reservation->event_type) === $eventType)>{{ $eventType }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-2">
                                <label class="form-label" for="schedule-package">Package</label>
                                <select id="schedule-package" name="package_id" class="form-select form-select-sm">
                                    @foreach($packages as $package)
                                        <option value="{{ $package->id }}" @selected((int) old('package_id', $reservation->package_id) === $package->id)>{{ $package->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="row g-2 mb-2">
                                <div class="col-sm-6">
                                    <label class="form-label" for="schedule-date">Date</label>
                                    <input id="schedule-date" type="date" name="event_date" value="{{ old('event_date', $reservation->event_date) }}" class="form-control form-control-sm" required>
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label" for="schedule-time">Time</label>
                                    <input id="schedule-time" type="time" name="event_time" value="{{ old('event_time', \Carbon\Carbon::parse($reservation->event_time)->format('H:i')) }}" class="form-control form-control-sm" required>
                                </div>
                            </div>
                            <div class="mb-2">
                                <label class="form-label" for="schedule-venue">Venue</label>
                                <input id="schedule-venue" type="text" name="venue" value="{{ old('venue', $reservation->venue) }}" class="form-control form-control-sm" minlength="3" maxlength="255" required>
                            </div>
                            <div class="mb-2">
                                <label class="form-label" for="schedule-guests">Guest count</label>
                                <input id="schedule-guests" type="number" name="guest_count" value="{{ old('guest_count', $reservation->guest_count) }}" class="form-control form-control-sm" min="1" max="1000" required>
                            </div>
                            <div class="mb-2">
                                <label class="form-label" for="schedule-services">Additional services</label>
                                <textarea id="schedule-services" name="additional_services" class="form-control form-control-sm" rows="2" maxlength="1000">{{ old('additional_services', $reservation->additional_services) }}</textarea>
                            </div>
                            <div class="mb-2">
                                <label class="form-label" for="schedule-special-requests">Special requests</label>
                                <textarea id="schedule-special-requests" name="special_requests" class="form-control form-control-sm" rows="2" maxlength="1000">{{ old('special_requests', $reservation->special_requests) }}</textarea>
                            </div>
                            <div class="mb-2">
                                <label class="form-label" for="schedule-additional-notes">Event notes</label>
                                <textarea id="schedule-additional-notes" name="additional_notes" class="form-control form-control-sm" rows="2" maxlength="1000">{{ old('additional_notes', $reservation->additional_notes) }}</textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="schedule-reason">Reason for change <span class="text-muted fw-normal">(optional)</span></label>
                                <textarea id="schedule-reason" name="reason" class="form-control form-control-sm" rows="2" maxlength="1000" placeholder="Client meeting, final event details, etc.">{{ old('reason') }}</textarea>
                            </div>
                            <button class="btn btn-sm luxury-btn" type="submit">Save changes</button>
                        </form>
                    @else
                        <p class="text-muted small mb-0">Event details can be edited once this reservation is accepted.</p>
                    @endif
                </details>
            </section>
        </div>
        <div class="col-12">
            <section class="card reservation-notes-card" aria-labelledby="reservation-notes-heading">
                <div class="reservation-secondary-heading">
                    <div>
                        <h2 class="h6 fw-bold mb-1" id="reservation-notes-heading">Notes</h2>
                        <p class="text-muted small mb-0">Internal notes are only visible to admins.</p>
                    </div>
                    <details class="reservation-notes-editor" id="reservation-notes-editor" @if($errors->any()) open @endif>
                        <summary class="btn btn-sm btn-outline-secondary">Edit</summary>
                        <form method="POST" action="{{ route('admin.reservations.update', $reservation) }}" class="reservation-notes-form">
                            @csrf @method('PATCH')
                            <label class="form-label" for="admin-reservation-notes">Internal note</label>
                            <textarea id="admin-reservation-notes" name="admin_notes" class="form-control form-control-sm mb-2" rows="3" placeholder="Add an internal note...">{{ old('admin_notes', $reservation->admin_notes) }}</textarea>
                            <button class="btn btn-sm luxury-btn" type="submit">Save note</button>
                        </form>
                    </details>
                </div>
                <p class="reservation-notes-content mb-0">{{ $reservation->admin_notes ?: 'No internal notes yet.' }}</p>
            </section>
        </div>

        <div class="col-12">
            <section class="card reservation-activity-card">
                <details id="reservation-activity">
                    <summary class="reservation-disclosure-heading reservation-activity-heading">
                        <span>
                            <strong>Activity</strong>
                            <small>Reservation history and administrative changes</small>
                        </span>
                        <span class="reservation-disclosure-action" aria-hidden="true"><span class="disclosure-label-closed">Show</span><span class="disclosure-label-open">Hide</span><span class="reservation-disclosure-chevron"></span></span>
                    </summary>
                    <div class="reservation-activity-content">
                        @forelse($activity as $entry)
                            <div class="activity-entry">
                                @if(in_array($entry->action, [
                                    'Reservation schedule changed',
                                    'Reservation details updated',
                                    'Reservation status changed',
                                    'Internal note added',
                                    'Internal note updated',
                                    'Payment recorded',
                                    'Contract amount updated',
                                    'Payment updated',
                                    'Payment deleted',
                                    'Refund recorded',
                                    'Official Receipt uploaded',
                                    'Official Receipt replaced',
                                    'Official Receipt removed',
                                    'Receipt OCR fields corrected',
                                    'Duplicate receipt blocked',
                                ], true))
                                    <div class="activity-entry-title">{{ $entry->action }}</div>
                                @endif
                                <div class="activity-entry-meta"><strong>{{ $entry->actor_name ?? 'Unknown administrator' }}</strong> &middot; {{ \Carbon\Carbon::parse($entry->activity_date.' '.$entry->activity_time)->format('M j, Y g:i A') }}</div>
                                <p class="mb-0 activity-entry-description">{{ $entry->description }}</p>
                            </div>
                        @empty
                            <p class="text-muted small mb-0">No recorded activity for this reservation yet.</p>
                        @endforelse
                    </div>
                </details>
            </section>
        </div>
    </div>
</div>

<dialog class="contract-preview-dialog" id="contract-preview-dialog" aria-labelledby="contract-preview-title">
    <div class="contract-preview-header">
        <strong id="contract-preview-title">Contract preview</strong>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-contract-preview-close>Close</button>
    </div>
    <div class="contract-preview-body">
        <img id="contract-preview-image" alt="" hidden>
        <div id="contract-preview-error" class="alert alert-warning mb-0" hidden>
            This image could not be previewed. <a id="contract-preview-fallback-download" href="#">Download the contract</a> instead.
        </div>
    </div>
    <div class="contract-preview-footer">
        <a id="contract-preview-download" class="btn btn-sm luxury-btn" href="#">Download</a>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-contract-preview-close>Close</button>
    </div>
</dialog>
<dialog class="acceptance-receipt-lightbox" id="acceptance-receipt-lightbox" aria-labelledby="acceptance-receipt-lightbox-title">
    <div class="acceptance-receipt-lightbox-header">
        <h2 class="h5 mb-0" id="acceptance-receipt-lightbox-title">Official receipt</h2>
        <div class="d-flex align-items-center gap-2">
            <button type="button" class="btn btn-sm btn-outline-secondary" data-acceptance-receipt-zoom aria-pressed="false">Zoom in</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-acceptance-receipt-close>Close</button>
        </div>
    </div>
    <div class="acceptance-receipt-lightbox-body">
        <img alt="Full-size payment receipt" data-acceptance-receipt-lightbox-image hidden>
        <p class="small mb-0" role="status" data-acceptance-receipt-lightbox-error hidden>Receipt image could not be displayed.</p>
    </div>
</dialog>

<style>
    .reservation-detail-page { padding: 1rem !important; }
    .reservation-detail-layout .card, .reservation-edit-card, .reservation-notes-card, .reservation-activity-card { padding: .9rem !important; }
    .reservation-detail-header { margin-bottom: .8rem; }
    .reservation-detail-header h1 { font-size: 1.35rem !important; }
    .reservation-detail-layout { --bs-gutter-y: .75rem; }
    .reservation-information-card { display: grid; gap: .7rem; }
    .reservation-information-card > h2 { margin-bottom: 0 !important; }
    .reservation-info-section { min-width: 0; }
    .reservation-info-section + .reservation-info-section { padding-top: .65rem; border-top: 1px solid var(--line); }
    .detail-list { display: grid; gap: .35rem; margin: 0; }
    .detail-list > div { display: flex; flex-wrap: wrap; gap: .2rem .65rem; }
    .detail-list dt { flex: 0 0 125px; color: var(--muted); font-size: .68rem; font-weight: 800; letter-spacing: .04em; text-transform: uppercase; }
    .detail-list dd { flex: 1 1 170px; min-width: 0; margin: 0; overflow-wrap: anywhere; font-size: .84rem; }
    .detail-list dd a { color: var(--teal); text-decoration: none; }
    .reservation-show-status { font-size: .8rem; padding: 0 1rem; height: 30px; }
    .back-link { display: inline-block; margin-bottom: .4rem; color: var(--teal-dark); font-size: .8rem; font-weight: 700; text-decoration: none; }

    .reservation-timeline { display: grid; grid-auto-flow: column; grid-auto-columns: 1fr; margin: 0 0 .8rem; }
    .reservation-timeline .timeline-step { position: relative; display: flex; flex-direction: column; align-items: center; text-align: center; padding: 0 .15rem; }
    .reservation-timeline .timeline-step-marker { position: relative; z-index: 1; display: grid; place-items: center; width: 24px; height: 24px; flex: 0 0 auto; border-radius: 50%; border: 2px solid var(--line); background: var(--surface); color: #b7b0a4; font-weight: 800; font-size: .75rem; line-height: 1; }
    .reservation-timeline .timeline-step:not(:last-child):before { content: ''; position: absolute; top: 11px; left: calc(50% + 12px); width: calc(100% - 24px); height: 2px; background: var(--line); z-index: 0; }
    .reservation-timeline .timeline-step--complete .timeline-step-marker { background: var(--teal-dark); border-color: var(--teal-dark); color: #fff; }
    .reservation-timeline .timeline-step--complete:not(:last-child):before { background: var(--teal-dark); }
    .reservation-timeline .timeline-step--current .timeline-step-marker { border-color: var(--teal-dark); color: var(--teal-dark); background: var(--surface); box-shadow: 0 0 0 4px rgba(13, 139, 131, .14); }
    .reservation-timeline .timeline-step--cancelled .timeline-step-marker { background: #a73838; border-color: #a73838; color: #fff; }
    .reservation-timeline .timeline-step-label { margin-top: .3rem; font-size: .62rem; font-weight: 800; line-height: 1.2; color: var(--ink); }
    .reservation-timeline .timeline-step--upcoming .timeline-step-label { color: var(--muted); }
    .reservation-timeline .timeline-step--current .timeline-step-label { color: var(--teal-dark); }

    .reservation-actions-group { display: flex; flex-wrap: wrap; gap: .5rem; margin-bottom: .75rem; }
    .reservation-actions-group form { margin: 0; }
    .reservation-subheading { margin: 0 0 .4rem; font-size: .68rem; font-weight: 800; letter-spacing: .07em; text-transform: uppercase; color: var(--muted); }

    .reservation-schedule-group { padding-top: .1rem; }
    .reservation-disclosure-heading { display: flex; align-items: center; justify-content: space-between; gap: .75rem; cursor: pointer; list-style: none; }
    .reservation-disclosure-heading::-webkit-details-marker, .reservation-notes-editor summary::-webkit-details-marker { display: none; }
    .reservation-disclosure-heading > span:first-child { display: grid; gap: .15rem; min-width: 0; }
    .reservation-disclosure-heading strong { color: var(--ink); font-size: .78rem; font-weight: 800; text-transform: uppercase; letter-spacing: .045em; }
    .reservation-disclosure-heading small { color: var(--muted); font-size: .75rem; font-weight: 400; }
    .reservation-disclosure-action { display: inline-flex; align-items: center; gap: .45rem; flex: 0 0 auto; color: var(--teal-dark); font-size: .75rem; font-weight: 800; }
    .disclosure-label-open, details[open] .disclosure-label-closed { display: none; }
    details[open] .disclosure-label-open { display: inline; }
    .reservation-disclosure-chevron { width: .45rem; height: .45rem; border-right: 1.5px solid currentColor; border-bottom: 1.5px solid currentColor; transform: rotate(45deg) translateY(-2px); transition: transform .15s ease; }
    details[open] > .reservation-disclosure-heading .reservation-disclosure-chevron { transform: rotate(225deg) translate(-2px, -1px); }
    .reservation-schedule-group[open] > form, .reservation-schedule-group[open] > p { margin-top: .8rem; }
    .reservation-schedule-group[open] .reservation-subheading { margin-bottom: .65rem; }
    .reservation-secondary-heading { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: .7rem; }
    .reservation-notes-card { display: grid; gap: .5rem; }
    .reservation-notes-content { color: var(--ink); font-size: .84rem; white-space: pre-line; overflow-wrap: anywhere; }
    .reservation-notes-editor { position: relative; }
    .reservation-notes-editor summary { list-style: none; }
    .reservation-notes-editor[open] { width: 100%; }
    .reservation-notes-editor[open] summary { display: inline-flex; }
    .reservation-notes-form { margin-top: .75rem; }
    .reservation-notes-form .form-label { font-size: .75rem; }
    .reservation-activity-heading { padding: 0; }
    .reservation-activity-content { margin-top: .75rem; }

    .contract-detail-list { display: grid; gap: .5rem; }
    .contract-detail-item { display: flex; align-items: center; justify-content: space-between; gap: .75rem; padding: .5rem .7rem; border: 1px solid var(--line); border-radius: 8px; }
    .contract-detail-meta { display: grid; gap: .12rem; min-width: 0; font-size: .8rem; }
    .contract-detail-meta span { color: var(--muted); overflow-wrap: anywhere; font-size: .72rem; }
    .contract-detail-actions { display: flex; align-items: center; justify-content: flex-end; flex-wrap: wrap; gap: .4rem; }
    .contract-detail-item form { margin: 0; }
    .contract-preview-dialog { width: min(1100px, calc(100vw - 2rem)); max-width: none; max-height: calc(100dvh - 2rem); padding: 0; overflow: hidden; border: 1px solid var(--line); background: var(--surface); color: var(--ink); }
    .contract-preview-dialog::backdrop { background: rgba(16, 20, 24, .72); }
    .contract-preview-header, .contract-preview-footer { display: flex; align-items: center; justify-content: space-between; gap: .75rem; padding: .75rem 1rem; background: var(--surface); }
    .contract-preview-header { border-bottom: 1px solid var(--line); }
    .contract-preview-footer { justify-content: flex-end; border-top: 1px solid var(--line); }
    .contract-preview-body { display: grid; place-items: center; min-height: 160px; max-height: calc(100dvh - 9rem); overflow: auto; padding: .75rem; }
    .contract-preview-body img { display: block; width: auto; height: auto; max-width: 100%; max-height: calc(100dvh - 11rem); object-fit: contain; }
    .contract-preview-body img[hidden], #contract-preview-error[hidden] { display: none; }
    @media(max-width:575px) {
        .contract-detail-item { align-items: flex-start; flex-direction: column; }
        .contract-detail-actions { justify-content: flex-start; }
        .contract-preview-dialog { width: calc(100vw - 1rem); max-height: calc(100dvh - 1rem); }
        .contract-preview-header, .contract-preview-footer { padding: .65rem .75rem; }
        .contract-preview-body { max-height: calc(100dvh - 8rem); padding: .5rem; }
        .contract-preview-body img { max-height: calc(100dvh - 10rem); }
    }

    .summary-grid--compact { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .4rem; margin-bottom: 0; }
    .summary-grid--compact .summary-item { padding: .55rem .65rem; box-shadow: none; }
    .summary-grid--compact .summary-item > span { margin-bottom: .2rem; font-size: .6rem; }
    .summary-grid--compact .summary-item > strong { font-size: .9rem; }
    .summary-item--warn { border-left: 4px solid #d49b28; }
    .receipt-review { padding: .75rem; border: 1px solid var(--line); border-radius: .65rem; }
    .receipt-review-preview { display: block; width: auto; max-width: min(100%, 320px); max-height: 240px; object-fit: contain; border-radius: .45rem; }
    .receipt-preview-trigger { display: inline-flex; flex-direction: column; align-items: flex-start; gap: .35rem; padding: 0; border: 0; color: var(--muted); background: transparent; text-align: left; cursor: zoom-in; }
    .receipt-preview-trigger[hidden] { display: none !important; }
    .receipt-review-details { overflow-wrap: anywhere; }
    .acceptance-receipt-lightbox { width: min(94vw, 480px); max-width: none; max-height: min(96dvh, 960px); padding: 0; overflow: hidden; border: 1px solid #314753; border-radius: .8rem; background: #1b2b35; color: #eef4f7; }
    .acceptance-receipt-lightbox::backdrop { background: rgba(0, 0, 0, .76); }
    .acceptance-receipt-lightbox-header { display: flex; align-items: center; justify-content: space-between; gap: .75rem; padding: .75rem 1rem; border-bottom: 1px solid #314753; }
    .acceptance-receipt-lightbox-header h2 { font-size: 1.2rem; }
    .acceptance-receipt-lightbox-body { display: grid; place-items: center; max-height: calc(96dvh - 4.5rem); overflow: auto; padding: .75rem; }
    .acceptance-receipt-lightbox-body > img { display: block; width: auto; height: auto; max-width: 100%; max-height: calc(96dvh - 6.5rem); object-fit: contain; }
    .acceptance-receipt-lightbox-body > img.is-zoomed { max-width: none; max-height: none; }
    .acceptance-receipt-lightbox [hidden] { display: none; }

    .activity-entry { padding: .65rem 0; border-top: 1px solid var(--line); font-size: .82rem; }
    .activity-entry:first-child { border-top: 0; padding-top: 0; }
    .activity-entry-title { margin-bottom: .15rem; font-weight: 700; }
    .activity-entry-meta { margin-bottom: .2rem; color: var(--muted); font-size: .7rem; }
    .activity-entry-description { white-space: pre-line; overflow-wrap: anywhere; }

    @media(max-width: 991.98px) {
        .reservation-detail-layout > .col-lg-5 { gap: .75rem !important; }
    }
    @media(max-width: 575px) {
        .reservation-detail-page { padding: .75rem !important; }
        .reservation-detail-header h1 { font-size: 1.2rem !important; }
        .detail-list dt { flex-basis: 120px; }
        .detail-list dd { flex-basis: 145px; }
        .summary-grid--compact { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .reservation-timeline .timeline-step-label { font-size: .56rem; }
        .reservation-secondary-heading { align-items: flex-start; }
        .reservation-notes-editor { margin-left: auto; }
        .acceptance-receipt-lightbox { width: calc(100vw - 1rem); max-height: calc(100dvh - 1rem); }
        .acceptance-receipt-lightbox-header { padding: .65rem .75rem; }
        .acceptance-receipt-lightbox-header h2 { font-size: 1.05rem; }
        .acceptance-receipt-lightbox-body { max-height: calc(100dvh - 4rem); padding: .5rem; }
        .acceptance-receipt-lightbox-body > img { max-height: calc(100dvh - 6rem); }
    }
    body.dark-mode .detail-list dd a,
    body.dark-mode .reservation-disclosure-action,
    body.dark-mode .reservation-timeline .timeline-step--current .timeline-step-label { color: #76c8bf; }
</style>
<script>
(() => {
    const form = document.querySelector('[data-acceptance-form]');
    if (!form) return;

    const fileInput = form.querySelector('[data-receipt-file]');
    const review = form.querySelector('[data-receipt-review]');
    const preview = form.querySelector('[data-receipt-preview]');
    const previewTrigger = form.querySelector('[data-receipt-preview-trigger]');
    const analyze = form.querySelector('[data-analyze-receipt]');
    const status = form.querySelector('[data-receipt-status]');
    const details = form.querySelector('[data-receipt-details]');
    const confirmation = form.querySelector('[data-receipt-confirmation]');
    const confirmCheckbox = form.querySelector('[name="receipt_confirmed"]');
    const reviewToken = form.querySelector('[name="receipt_review_token"]');
    const clear = form.querySelector('[data-clear-receipt]');
    const saveButton = form.querySelector('[data-save-payment]');
    if (!fileInput || !review || !preview || !previewTrigger || !analyze || !status || !details || !confirmation || !confirmCheckbox || !reviewToken || !clear || !saveButton) return;

    let analysisController = null;
    const updateSubmitState = () => {
        saveButton.disabled = !reviewToken.value || !confirmCheckbox.checked;
    };
    const resetReview = () => {
        analysisController?.abort();
        analysisController = null;
        reviewToken.value = '';
        confirmCheckbox.checked = false;
        confirmation.hidden = true;
        details.hidden = true;
        details.textContent = '';
        status.textContent = '';
        updateSubmitState();
    };
    const analyzeReceipt = async () => {
        const file = fileInput.files && fileInput.files[0];
        if (!file) return;
        resetReview();
        const controller = new AbortController();
        analysisController = controller;
        analyze.disabled = true;
        analyze.textContent = 'Analyzing…';
        status.textContent = 'Analyzing receipt locally…';

        try {
            const response = await fetch(form.dataset.analyzeUrl, {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: new FormData(form),
                credentials: 'same-origin',
                signal: controller.signal,
            });
            const result = await response.json();
            if (controller !== analysisController) return;
            if (!response.ok) {
                status.textContent = Object.values(result.errors || {}).flat()[0] || result.message || 'The receipt could not be analyzed. Try another image.';
                return;
            }

            const receipt = result.receipt || {};
            status.textContent = `${receipt.receipt_type || 'Payment receipt'} · ${receipt.confidence || 'Needs Review'}. Review and correct the fields below.`;
            details.textContent = [
                `Provider: ${receipt.provider || 'Not identified'}`,
                `Amount: ${receipt.amount ? `₱${receipt.amount}` : 'Not detected'}`,
                `Reference: ${receipt.reference_number || 'Not detected'}`,
                `Method: ${receipt.payment_method || 'Select manually'}`,
                `Date: ${receipt.payment_date || 'Select manually'}`,
            ].join(' · ');
            details.hidden = false;
            if (receipt.amount) form.querySelector('[name="amount"]').value = receipt.amount;
            if (receipt.amount) form.querySelector('[name="amount"]').dispatchEvent(new Event('input', { bubbles: true }));
            if (receipt.reference_number) form.querySelector('[name="reference_number"]').value = receipt.reference_number;
            if (receipt.payment_method && [...form.querySelector('[name="payment_method"]').options].some((option) => option.value === receipt.payment_method)) {
                form.querySelector('[name="payment_method"]').value = receipt.payment_method;
            }
            if (receipt.payment_date) form.querySelector('[name="payment_date"]').value = receipt.payment_date;
            reviewToken.value = result.review_token;
            confirmation.hidden = false;
            analyze.textContent = 'Analyze again';
            updateSubmitState();
        } catch {
            if (controller !== analysisController || controller.signal.aborted) return;
            status.textContent = 'Receipt analysis failed. Retry the analysis before accepting.';
        } finally {
            if (controller === analysisController) {
                analysisController = null;
                analyze.disabled = false;
                if (analyze.textContent === 'Analyzing…') analyze.textContent = 'Analyze again';
            }
        }
    };

    fileInput.addEventListener('change', () => {
        resetReview();
        const file = fileInput.files && fileInput.files[0];
        review.hidden = !file;
        previewTrigger.hidden = true;
        if (!file) {
            preview.removeAttribute('src');
            return;
        }
        const reader = new FileReader();
        reader.addEventListener('load', () => {
            if (fileInput.files[0] !== file) return;
            const source = String(reader.result || '');
            if (!source.startsWith('data:image/')) {
                status.textContent = 'The selected receipt image could not be previewed. Choose the image again.';
                return;
            }
            preview.src = source;
            previewTrigger.hidden = false;
            analyzeReceipt();
        });
        reader.addEventListener('error', () => {
            status.textContent = 'The selected receipt image could not be read. Choose the image again.';
        });
        reader.readAsDataURL(file);
    });
    clear.addEventListener('click', () => {
        fileInput.value = '';
        fileInput.dispatchEvent(new Event('change', { bubbles: true }));
    });
    analyze.addEventListener('click', analyzeReceipt);
    confirmCheckbox.addEventListener('change', updateSubmitState);
    form.addEventListener('submit', (event) => {
        if (!reviewToken.value || !confirmCheckbox.checked) {
            event.preventDefault();
            status.textContent = 'Analyze the receipt and confirm the reviewed details before accepting.';
            return;
        }
    });
    updateSubmitState();
})();
</script>
<script>
(() => {
    const dialog = document.getElementById('acceptance-receipt-lightbox');
    const lightboxImage = dialog?.querySelector('[data-acceptance-receipt-lightbox-image]');
    const lightboxError = dialog?.querySelector('[data-acceptance-receipt-lightbox-error]');
    const zoomButton = dialog?.querySelector('[data-acceptance-receipt-zoom]');
    const form = document.querySelector('[data-acceptance-form]');
    if (!dialog || !lightboxImage || !lightboxError || !zoomButton || !form) return;

    const resetZoom = () => {
        lightboxImage.classList.remove('is-zoomed');
        zoomButton.textContent = 'Zoom in';
        zoomButton.setAttribute('aria-pressed', 'false');
    };
    const closePreview = () => dialog.close();
    form.querySelectorAll('[data-acceptance-receipt-preview]').forEach((button) => {
        button.addEventListener('click', () => {
            const thumbnail = button.querySelector('img');
            const source = thumbnail?.currentSrc || thumbnail?.src;
            if (!source) return;
            resetZoom();
            lightboxError.hidden = true;
            lightboxImage.hidden = true;
            lightboxImage.src = source;
            dialog.showModal();
        });
    });
    dialog.querySelector('[data-acceptance-receipt-close]')?.addEventListener('click', closePreview);
    zoomButton.addEventListener('click', () => {
        const zoomed = !lightboxImage.classList.contains('is-zoomed');
        lightboxImage.classList.toggle('is-zoomed', zoomed);
        zoomButton.textContent = zoomed ? 'Fit to screen' : 'Zoom in';
        zoomButton.setAttribute('aria-pressed', String(zoomed));
    });
    dialog.addEventListener('cancel', (event) => {
        event.preventDefault();
        closePreview();
    });
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) closePreview();
    });
    lightboxImage.addEventListener('load', () => {
        lightboxImage.hidden = false;
        lightboxError.hidden = true;
    });
    lightboxImage.addEventListener('error', () => {
        lightboxImage.hidden = true;
        lightboxError.hidden = false;
    });
    dialog.addEventListener('close', () => {
        lightboxImage.removeAttribute('src');
        lightboxImage.hidden = true;
        lightboxError.hidden = true;
        resetZoom();
    });
})();
</script>
<script>
(() => {
    const dialog = document.getElementById('contract-preview-dialog');
    const image = document.getElementById('contract-preview-image');
    const title = document.getElementById('contract-preview-title');
    const download = document.getElementById('contract-preview-download');
    const error = document.getElementById('contract-preview-error');
    const fallbackDownload = document.getElementById('contract-preview-fallback-download');

    if (!dialog || !image || !title || !download || !error || !fallbackDownload) return;

    document.querySelectorAll('[data-contract-preview]').forEach((button) => {
        button.addEventListener('click', () => {
            const previewUrl = button.dataset.previewUrl;
            const downloadUrl = button.dataset.downloadUrl;
            const filename = button.dataset.filename;
            if (!previewUrl || !downloadUrl || !filename) return;

            title.textContent = filename;
            download.href = downloadUrl;
            fallbackDownload.href = downloadUrl;
            image.alt = filename;
            image.hidden = true;
            error.hidden = true;
            image.src = previewUrl;
            dialog.showModal();
        });
    });

    image.addEventListener('load', () => {
        image.hidden = false;
        error.hidden = true;
    });
    image.addEventListener('error', () => {
        image.hidden = true;
        error.hidden = false;
    });
    document.querySelectorAll('[data-contract-preview-close]').forEach((button) => {
        button.addEventListener('click', () => dialog.close());
    });
    dialog.addEventListener('close', () => {
        image.removeAttribute('src');
        image.hidden = true;
        error.hidden = true;
    });
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) dialog.close();
    });
})();
</script>
<script>
(() => {
    const form = document.querySelector('[data-acceptance-form]');
    if (!form) return;

    const contract = form.querySelector('[name="total_cost"]');
    const amount = form.querySelector('[name="amount"]');
    const type = form.querySelector('[name="payment_type"]');
    const balance = form.querySelector('[data-acceptance-balance]');
    const paidCents = Number(balance.dataset.paidCents || 0);
    const formatPeso = (cents) => `₱${(cents / 100).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    const updateBalance = () => {
        const contractCents = Math.round((Number(contract.value) || 0) * 100);
        const amountCents = Math.round((Number(amount.value) || 0) * 100);
        const availableCents = Math.max(0, contractCents - paidCents);
        const remainingCents = Math.max(0, availableCents - amountCents);
        balance.textContent = contractCents > 0
            ? `Remaining after this payment: ${formatPeso(remainingCents)}`
            : 'Enter the contract price and payment to see the remaining balance.';
        amount.max = (availableCents / 100).toFixed(2);
        if (type.value === 'Full Payment' && availableCents > 0) {
            amount.value = (availableCents / 100).toFixed(2);
        }
    };

    [contract, amount].forEach((input) => input.addEventListener('input', updateBalance));
    type.addEventListener('change', updateBalance);
    form.addEventListener('submit', (event) => {
        const contractCents = Math.round((Number(contract.value) || 0) * 100);
        const amountCents = Math.round((Number(amount.value) || 0) * 100);
        const remainingCents = contractCents - paidCents - amountCents;
        if (contractCents <= 0 || amountCents <= 0 || remainingCents < 0) return;
        if (!window.confirm(`Confirm reservation acceptance?\n\nContract price: ${formatPeso(contractCents)}\nPayment: ${formatPeso(amountCents)}\nBalance remaining: ${formatPeso(remainingCents)}\n\nThe reviewed receipt, payment, contract price, and acceptance will be saved together.`)) {
            event.preventDefault();
        }
    });
    updateBalance();
})();
</script>
@endsection
