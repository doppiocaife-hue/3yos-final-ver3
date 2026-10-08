@extends('layouts.admin')

@php
    $peso = fn ($amount) => '₱' . number_format((float) $amount, 2);
    $balanceCents = $financials['remaining_balance_cents'];
    $grossPaid = $financials['gross_paid_cents'] / 100;
    $totalRefunded = $financials['total_refunded_cents'] / 100;
    $netPaid = $financials['net_paid_cents'] / 100;
    $contractSet = $reservation->total_cost !== null;
    $fullyPaid = $financials['payment_status'] === 'Fully Paid';
    $lastPayment = $payments->last();
    $dueDate = $reservation->payment_due_date;
    $overdue = $dueDate && $dueDate->isPast() && ! $dueDate->isToday() && ($balanceCents ?? 0) > 0;
@endphp

@section('content')
<div class="content-card">
    <div class="page-header">
        <div>
            <a class="back-link" href="{{ route('admin.reservations') }}">← Back to reservations</a>
            <div class="page-kicker">Contract &amp; payment</div>
            <h1 class="fw-bold mb-1">Payments · {{ $reservation->reservation_code ?? '#' . $reservation->id }}</h1>
            <p class="text-muted mb-0">{{ $reservation->full_name }} · {{ $reservation->event_type }} on {{ \Carbon\Carbon::parse($reservation->event_date)->format('M j, Y') }}{{ $reservation->package ? ' · ' . $reservation->package->name : '' }}</p>
        </div>
        <div class="page-actions">
            <button class="btn btn-outline-secondary" type="button" data-print-record
                data-print-url="{{ route('admin.reservations.payments.print', $reservation) }}">Print record</button>
        </div>
    </div>
    <iframe title="Printable payment record" data-print-frame aria-hidden="true" tabindex="-1" hidden></iframe>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

    <div class="summary-grid" aria-label="Payment summary">
        <div class="summary-item summary-item--accent"><span>Contract price</span><strong>{{ $contractSet ? $peso($reservation->total_cost) : 'Not set' }}</strong></div>
        <div class="summary-item"><span>Payment status</span><strong><span class="status-badge status-badge--{{ \App\Models\Reservation::paymentStatusBadge($reservation->payment_status) }}">{{ \App\Models\Reservation::paymentStatusLabel($reservation->payment_status) }}</span></strong></div>
        <div class="summary-item"><span>Gross paid</span><strong>{{ $peso($grossPaid) }}</strong></div>
        <div class="summary-item"><span>Total refunded</span><strong>{{ $peso($totalRefunded) }}</strong></div>
        <div class="summary-item"><span>Net paid</span><strong>{{ $peso($netPaid) }}</strong></div>
        <div class="summary-item {{ ($balanceCents ?? 0) > 0 ? 'summary-item--warn' : '' }}"><span>Remaining balance</span><strong>{{ $contractSet ? $peso($balanceCents / 100) : '—' }}</strong></div>
        <div class="summary-item"><span>Payment due date</span><strong>{{ $dueDate ? $dueDate->format('F j, Y') : 'Not set' }}</strong>@if($overdue)<span class="status-badge status-badge--cancelled mt-2">Overdue</span>@endif</div>
        <div class="summary-item"><span>Last payment method</span><strong>{{ $lastPayment?->payment_method ?? '—' }}</strong></div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-lg-7">
            <section class="card h-100" aria-labelledby="record-payment-title">
                <div class="panel-header"><div><h5 class="fw-bold mb-1" id="record-payment-title">Record a payment</h5><p class="text-muted small">Log money received. Totals, balance, and status update automatically.</p></div></div>
                @if(! $contractSet)
                    <div class="alert alert-warning mb-0">Set the contract price first, then record payments against it.</div>
                @elseif($fullyPaid)
                    <div class="alert alert-success mb-0">This booking is fully paid. Correct a payment below if its recorded details need to change.</div>
                @else
                    <form method="POST" enctype="multipart/form-data" action="{{ route('admin.reservations.payments.store', $reservation) }}" novalidate data-payment-validation="record" data-submit-once data-confirm-message="Record this payment? The balance and status will be recalculated." data-receipt-form data-analyze-url="{{ route('admin.reservations.payments.receipt.analyze', $reservation) }}">
                        @csrf
                        <input type="hidden" name="receipt_review_token" value="">
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label class="form-label" for="payment_date">Payment date</label>
                                <input class="form-control @error('payment_date') is-invalid @enderror" type="date" id="payment_date" name="payment_date" value="{{ old('payment_date', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label" for="payment_type">Payment type</label>
                                <select class="form-select @error('payment_type') is-invalid @enderror" id="payment_type" name="payment_type" required>
                                    @foreach($types as $type)<option value="{{ $type }}" @selected(old('payment_type', $suggestedType) === $type)>{{ $type }}</option>@endforeach
                                </select>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label" for="amount">Amount (₱)</label>
                                <input class="form-control @error('amount') is-invalid @enderror" type="number" id="amount" name="amount" value="{{ old('amount') }}" min="0.01" max="{{ number_format($balanceCents / 100, 2, '.', '') }}" step="0.01" inputmode="decimal" placeholder="0.00" required>
                                <div class="form-text">Remaining balance: {{ $peso($balanceCents / 100) }}</div>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label" for="payment_method">Payment method</label>
                                <select class="form-select @error('payment_method') is-invalid @enderror" id="payment_method" name="payment_method" required>
                                    @foreach($methods as $method)<option value="{{ $method }}" @selected(old('payment_method', 'Cash') === $method)>{{ $method }}</option>@endforeach
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="reference_number">Reference / transaction number <span class="text-muted fw-normal">(optional)</span></label>
                                <input class="form-control @error('reference_number') is-invalid @enderror" type="text" id="reference_number" name="reference_number" value="{{ old('reference_number') }}" maxlength="100" autocomplete="off">
                            </div>
                            <div class="col-12 receipt-upload-field">
                                <label class="form-label" for="receipt_image">Payment receipt image <span class="text-muted fw-normal">(optional)</span></label>
                                <input class="form-control @error('receipt_image') is-invalid @enderror" type="file" id="receipt_image" name="receipt_image" accept="image/jpeg,image/png,image/webp" data-receipt-file>
                                <div class="form-text">JPG, PNG, or WEBP; maximum 5MB. The receipt is analyzed automatically when selected. Review the extracted details before saving.</div>
                                <div class="receipt-review mt-3" data-receipt-review hidden>
                                    <button type="button" class="receipt-preview-trigger" data-receipt-preview-trigger data-view-receipt hidden aria-label="View selected receipt full size">
                                        <img class="receipt-review-preview" alt="" data-receipt-preview>
                                        <span>Click to view full receipt</span>
                                    </button>
                                    <button class="btn btn-sm btn-outline-primary mt-2" type="button" data-analyze-receipt>Analyze receipt</button>
                                    <button class="btn btn-sm btn-outline-secondary mt-2" type="button" data-clear-receipt>Remove image</button>
                                    <p class="small mt-2 mb-2" role="status" aria-live="polite" data-receipt-status></p>
                                    <div class="receipt-review-details small" data-receipt-details hidden></div>
                                    <label class="form-check mt-2" data-receipt-confirmation hidden>
                                        <input class="form-check-input" type="checkbox" name="receipt_confirmed" value="1">
                                        <span class="form-check-label">I reviewed the extracted details and confirm them before recording this payment.</span>
                                    </label>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="notes">Notes <span class="text-muted fw-normal">(optional)</span></label>
                                <textarea class="form-control" id="notes" name="notes" rows="2" maxlength="1000" placeholder="Reference number, who paid, etc.">{{ old('notes') }}</textarea>
                            </div>
                        </div>
                        <div class="d-flex justify-content-end mt-3"><button class="btn luxury-btn" type="submit" data-save-payment>Save Payment</button></div>
                    </form>
                @endif
            </section>
        </div>
        <div class="col-lg-5">
            <section class="card h-100" aria-labelledby="contract-title">
                <div class="panel-header"><div><h5 class="fw-bold mb-1" id="contract-title">Contract &amp; due date</h5><p class="text-muted small">The due date is separate from the event date.</p></div></div>
                <form method="POST" action="{{ route('admin.reservations.payments.details', $reservation) }}" data-submit-once data-confirm-message="Save the contract price and payment due date?">
                    @csrf @method('PATCH')
                    <div class="mb-3">
                        <label class="form-label" for="total_cost">Contract price (₱)</label>
                        <input class="form-control @error('total_cost') is-invalid @enderror" type="number" id="total_cost" name="total_cost" value="{{ old('total_cost', $reservation->total_cost) }}" min="{{ number_format($netPaid, 2, '.', '') }}" step="0.01" inputmode="decimal" required>
                        @if($netPaid > 0)<div class="form-text">Cannot be lower than the {{ $peso($netPaid) }} net amount paid.</div>@endif
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="payment_due_date">Payment due date</label>
                        <input class="form-control" type="date" id="payment_due_date" name="payment_due_date" value="{{ old('payment_due_date', $dueDate?->toDateString()) }}">
                        <div class="form-text">Event date: {{ \Carbon\Carbon::parse($reservation->event_date)->format('F j, Y') }}</div>
                    </div>
                    <div class="d-flex justify-content-end"><button class="btn luxury-btn" type="submit">Save</button></div>
                </form>
            </section>
        </div>
    </div>

    <section class="card mb-4 refund-panel" aria-labelledby="refund-payment-title">
        <div class="panel-header">
            <div>
                <h5 class="fw-bold mb-1" id="refund-payment-title">Refund payment</h5>
                <p class="text-muted small mb-0">Record money returned to the customer. The original payments remain in the history.</p>
            </div>
        </div>
        @if($netPaid <= 0)
            <div class="alert alert-info mb-0">A refund can be recorded after a payment has been received.</div>
        @else
            <form method="POST" id="refund-payment-form" action="{{ route('admin.reservations.refunds.store', $reservation) }}" data-submit-once data-confirm-message="Confirm this refund?" data-refund-confirm>
                @csrf
                <input type="hidden" name="request_key" value="{{ $refundRequestKey }}">
                <div class="row g-3">
                    <div class="col-sm-6 col-lg-3">
                        <label class="form-label" for="refund_amount">Amount (₱)</label>
                        <input class="form-control @error('refund_amount') is-invalid @enderror" type="number" id="refund_amount" name="refund_amount" value="{{ old('refund_amount') }}" min="0.01" max="{{ number_format($netPaid, 2, '.', '') }}" step="0.01" inputmode="decimal" placeholder="0.00" required>
                        <div class="form-text">Maximum refundable: {{ $peso($netPaid) }}</div>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <label class="form-label" for="refund_method">Refund method</label>
                        <select class="form-select @error('refund_method') is-invalid @enderror" id="refund_method" name="refund_method" required>
                            @foreach($methods as $method)<option value="{{ $method }}" @selected(old('refund_method', 'Cash') === $method)>{{ $method }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <label class="form-label" for="refund_date">Refund date</label>
                        <input class="form-control @error('refund_date') is-invalid @enderror" type="date" id="refund_date" name="refund_date" value="{{ old('refund_date', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <label class="form-label" for="refund_reason">Reason / notes <span class="text-muted fw-normal">(optional)</span></label>
                        <input class="form-control @error('reason') is-invalid @enderror" type="text" id="refund_reason" name="reason" value="{{ old('reason') }}" maxlength="1000" placeholder="Reason for refund">
                    </div>
                </div>
                <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 mt-3">
                    <span class="small text-muted">Current net paid: {{ $peso($netPaid) }}</span>
                    <button class="btn btn-outline-danger" type="submit">Process Refund</button>
                </div>
            </form>
        @endif
    </section>

    <section aria-labelledby="history-title">
        <h5 class="fw-bold mb-2" id="history-title">Payment history</h5>
        <div class="table-responsive">
            <table class="table align-middle payment-history">
                <thead>
            <tr><th>Date</th><th>Payment type</th><th class="text-end">Amount</th><th>Method</th><th>Receipt</th><th>Notes</th><th>Recorded by</th><th class="text-end">Actions</th></tr>
                </thead>
                <tbody>
                    @forelse($transactions as $transaction)
                        <tr>
                            <td class="text-nowrap">{{ $transaction->date->format('m/d/Y') }}</td>
                            <td>
                                @if($transaction->kind === 'refund')
                                    <span class="refund-type">Refund</span>
                                @else
                                    {{ $transaction->type }}
                                @endif
                            </td>
                            <td class="text-end money fw-bold {{ $transaction->kind === 'refund' ? 'refund-amount' : '' }}">{{ $transaction->kind === 'refund' ? '−' : '' }}{{ $peso($transaction->amount) }}</td>
                            <td>{{ $transaction->method }}</td>
                            <td>
                                @if($transaction->kind === 'payment' && $transaction->payment->receipt_image_path)
                                    <button type="button" class="btn btn-sm btn-outline-secondary" data-view-receipt
                                        data-receipt-url="{{ route('admin.reservations.payments.receipt', [$reservation, $transaction->payment]) }}"
                                        aria-label="View receipt for payment {{ $peso($transaction->amount) }}">View Receipt</button>
                                @elseif($transaction->kind === 'payment')
                                    <span class="text-muted">No receipt</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-muted payment-notes">@if($transaction->kind === 'payment' && $transaction->payment->reference_number)<span>Reference: {{ $transaction->payment->reference_number }}</span><br>@endif{{ $transaction->notes ?: '—' }}</td>
                            <td class="text-muted">{{ $transaction->recorded_by_name ?: '—' }}<br><small>{{ $transaction->created_at?->format('M j, Y g:i A') }}</small></td>
                            <td class="text-end">
                                @if($transaction->kind === 'payment')
                                    <div class="table-actions">
                                        <button type="button" class="btn btn-sm btn-outline-secondary" data-correct-payment
                                            data-action="{{ route('admin.reservations.payments.correct', [$reservation, $transaction->payment]) }}"
                                            data-id="{{ $transaction->payment->id }}"
                                            data-date="{{ $transaction->payment->payment_date->toDateString() }}"
                                            data-type="{{ $transaction->payment->payment_type }}"
                                            data-amount="{{ number_format($transaction->payment->amount, 2, '.', '') }}"
                                            data-method="{{ $transaction->payment->payment_method }}"
                                            data-notes="{{ $transaction->payment->notes }}"
                                            data-reference="{{ $transaction->payment->reference_number }}"
                                            data-receipt-url="{{ $transaction->payment->receipt_image_path ? route('admin.reservations.payments.receipt', [$reservation, $transaction->payment]) : '' }}">Correct Payment</button>
                                    </div>
                                    @if($transaction->payment->corrections->isNotEmpty())
                                        <details class="small text-start mt-2">
                                            <summary>{{ $transaction->payment->corrections->count() }} correction{{ $transaction->payment->corrections->count() === 1 ? '' : 's' }} recorded</summary>
                                            <div class="pt-2">
                                                @foreach($transaction->payment->corrections as $correction)
                                                    <div class="border-top pt-2 mt-2">
                                                        <strong>{{ $correction->created_at?->format('M j, Y g:i A') }} · {{ $correction->admin_name }}</strong>
                                                        <div>Reason: {{ $correction->reason }}</div>
                                                        <div>Amount: {{ $peso($correction->original_values['amount'] ?? 0) }} → {{ $peso($correction->corrected_values['amount'] ?? 0) }}</div>
                                                        <div>Method: {{ $correction->original_values['payment_method'] ?? '—' }} → {{ $correction->corrected_values['payment_method'] ?? '—' }}</div>
                                                        <div>Receipt: {{ !empty($correction->original_values['receipt_image_path']) ? basename($correction->original_values['receipt_image_path']) : 'No receipt' }} → {{ !empty($correction->corrected_values['receipt_image_path']) ? basename($correction->corrected_values['receipt_image_path']) : 'No receipt' }}</div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </details>
                                    @endif
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-4">No payments recorded yet.</td></tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr><th colspan="2">Gross paid</th><th class="text-end money">{{ $peso($grossPaid) }}</th><th colspan="5"></th></tr>
                    <tr><th colspan="2">Total refunded</th><th class="text-end money refund-amount">−{{ $peso($totalRefunded) }}</th><th colspan="5"></th></tr>
                    <tr><th colspan="2">Net paid</th><th class="text-end money">{{ $peso($netPaid) }}</th><th colspan="5"></th></tr>
                    <tr><th colspan="2">Balance</th><th class="text-end money">{{ $contractSet ? $peso($balanceCents / 100) : '—' }}</th><th colspan="5"></th></tr>
                </tfoot>
            </table>
        </div>
    </section>
</div>

<dialog id="payment-receipt-dialog" aria-labelledby="payment-receipt-title" class="payment-receipt-dialog">
    <div class="receipt-dialog-header">
        <h2 id="payment-receipt-title" class="h5 mb-0">Official receipt</h2>
        <div class="d-flex align-items-center gap-2">
            <button type="button" class="btn btn-sm btn-outline-secondary" data-toggle-receipt-zoom aria-pressed="false">Zoom in</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-close-receipt>Close</button>
        </div>
    </div>
    <div class="receipt-dialog-body">
        <img id="payment-receipt-image" alt="Official receipt image" hidden>
        <p id="payment-receipt-error" class="alert alert-warning mb-0" hidden>The receipt image could not be displayed.</p>
    </div>
</dialog>

<dialog id="correct-payment-dialog" aria-labelledby="correct-payment-title">
    <h2 id="correct-payment-title" class="h5 mb-3">Correct payment</h2>
    <form method="POST" enctype="multipart/form-data" id="correct-payment-form" action="" novalidate data-payment-validation="correct" data-password-confirm data-password-message="Confirm your administrator password to correct this payment." data-submit-once data-receipt-form data-analyze-url="{{ route('admin.reservations.payments.receipt.analyze', $reservation) }}">
        @csrf @method('PUT')
        <input type="hidden" name="receipt_review_token" value="">
        <input type="hidden" name="payment_id" value="">
        <div class="alert alert-warning small">Corrections are permanently recorded. The original payment details and any replaced receipt will be preserved in the audit history.</div>
        <div class="row g-3">
            <div class="col-sm-6"><label class="form-label" for="edit_payment_date">Payment date</label><input class="form-control" type="date" id="edit_payment_date" name="payment_date" max="{{ now()->toDateString() }}" required></div>
            <div class="col-sm-6"><label class="form-label" for="edit_payment_type">Payment type</label><select class="form-select" id="edit_payment_type" name="payment_type" required>@foreach($types as $type)<option value="{{ $type }}">{{ $type }}</option>@endforeach</select></div>
            <div class="col-sm-6"><label class="form-label" for="edit_amount">Amount (₱)</label><input class="form-control" type="number" id="edit_amount" name="amount" min="0.01" step="0.01" inputmode="decimal" required></div>
            <div class="col-sm-6"><label class="form-label" for="edit_payment_method">Payment method</label><select class="form-select" id="edit_payment_method" name="payment_method" required>@foreach($methods as $method)<option value="{{ $method }}">{{ $method }}</option>@endforeach</select></div>
            <div class="col-12"><label class="form-label" for="edit_reference_number">Reference / transaction number <span class="text-muted fw-normal">(optional)</span></label><input class="form-control" type="text" id="edit_reference_number" name="reference_number" maxlength="100" autocomplete="off"></div>
            <div class="col-12 receipt-upload-field">
                <label class="form-label" for="edit_receipt_image">Official Receipt Image <span class="text-muted fw-normal">(optional)</span></label>
                <div class="current-receipt mb-2"><span id="edit-receipt-empty" class="text-muted small">No receipt uploaded.</span><button id="edit-view-receipt" type="button" class="btn btn-sm btn-outline-secondary" data-view-receipt hidden>View current receipt</button></div>
                <input class="form-control" type="file" id="edit_receipt_image" name="receipt_image" accept="image/jpeg,image/png,image/webp" data-receipt-file>
                <div class="form-text">Choose a new JPG, PNG, or WEBP image (maximum 5MB). It will be analyzed automatically; review the extracted details before replacing the receipt. Existing receipts are retained in correction history.</div>
                <div class="receipt-review mt-3" data-receipt-review hidden>
                    <button type="button" class="receipt-preview-trigger" data-receipt-preview-trigger data-view-receipt hidden aria-label="View selected receipt full size">
                        <img class="receipt-review-preview" alt="" data-receipt-preview>
                        <span>Click to view full receipt</span>
                    </button>
                    <button class="btn btn-sm btn-outline-primary mt-2" type="button" data-analyze-receipt>Analyze receipt</button>
                    <button class="btn btn-sm btn-outline-secondary mt-2" type="button" data-clear-receipt>Remove image</button>
                    <p class="small mt-2 mb-2" role="status" aria-live="polite" data-receipt-status></p>
                    <div class="receipt-review-details small" data-receipt-details hidden></div>
                    <label class="form-check mt-2" data-receipt-confirmation hidden>
                        <input class="form-check-input" type="checkbox" name="receipt_confirmed" value="1">
                        <span class="form-check-label">I reviewed the extracted details and confirm them before saving this change.</span>
                    </label>
                </div>
            </div>
            <div class="col-12"><label class="form-label" for="edit_notes">Notes <span class="text-muted fw-normal">(optional)</span></label><textarea class="form-control" id="edit_notes" name="notes" rows="2" maxlength="1000"></textarea></div>
            <div class="col-12"><label class="form-label" for="correction_reason">Reason for correction</label><textarea class="form-control" id="correction_reason" name="correction_reason" rows="3" minlength="3" maxlength="1000" required>{{ old('correction_reason') }}</textarea><div class="form-text">Explain why the recorded payment is being corrected. This reason is saved with the audit history.</div></div>
        </div>
        <div class="d-flex justify-content-end gap-2 mt-4">
            <button type="button" class="btn btn-outline-secondary" data-close-dialog>Cancel</button>
            <button type="submit" class="btn luxury-btn">Review and correct</button>
        </div>
    </form>
</dialog>

<dialog id="payment-validation-dialog" class="payment-validation-dialog" aria-labelledby="payment-validation-title" aria-describedby="payment-validation-message">
    <div class="payment-validation-header">
        <span class="payment-validation-icon" aria-hidden="true">!</span>
        <h2 id="payment-validation-title" class="h5 mb-0">Information Required</h2>
    </div>
    <div id="payment-validation-message" class="alert alert-warning mb-3" role="alert" aria-live="assertive"></div>
    <div class="d-flex justify-content-end">
        <button type="button" class="btn btn-outline-secondary" data-close-payment-validation>Close</button>
    </div>
</dialog>

<script type="application/json" id="payment-server-errors">@json(array_values(array_unique(array_merge($errors->all(), $errors->correctPayment->all()))))</script>

<style>
    .payment-history { min-width: 900px; }
    .payment-history tfoot th { border-top: 1px solid var(--line); background: #f7f9fa; font-size: .8rem; }
    body.dark-mode .payment-history tfoot th { background: #223641; }
    .payment-notes { max-width: 260px; overflow-wrap: anywhere; }
    .summary-item .status-badge { font-family: "DM Sans", sans-serif; }
    .refund-panel { border-color: rgba(185, 71, 71, .28); }
    .refund-type, .refund-amount { color: var(--danger) !important; }
    body.dark-mode .refund-type, body.dark-mode .refund-amount { color: #ffb0b0 !important; }
    .receipt-upload-field { min-width: 0; }
    .receipt-review { padding: .75rem; border: 1px solid var(--line); border-radius: .65rem; }
    .receipt-review-preview { display: block; width: auto; max-width: min(100%, 320px); max-height: 240px; object-fit: contain; border-radius: .45rem; }
    .receipt-preview-trigger { display: inline-flex; flex-direction: column; align-items: flex-start; gap: .35rem; max-width: 100%; padding: 0; border: 0; background: transparent; color: var(--muted); font: inherit; font-size: .8rem; text-align: left; cursor: zoom-in; }
    .receipt-preview-trigger:hover .receipt-review-preview, .receipt-preview-trigger:focus-visible .receipt-review-preview { outline: 2px solid var(--accent, #b78b4b); outline-offset: 3px; }
    .receipt-preview-trigger[hidden] { display: none; }
    .receipt-review-details { overflow-wrap: anywhere; }
    .current-receipt { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; }
    .payment-receipt-dialog { width: min(900px, calc(100vw - 2rem)); max-width: none; max-height: calc(100dvh - 2rem); padding: 0; overflow: hidden; border: 1px solid var(--line); background: var(--surface); color: var(--ink); }
    .payment-receipt-dialog::backdrop { background: rgba(16, 20, 24, .72); }
    .payment-validation-dialog { width: min(480px, calc(100vw - 2rem)); max-height: min(80dvh, 640px); overflow-y: auto; padding: 1.25rem; border: 1px solid var(--line); border-radius: .75rem; background: var(--surface); color: var(--ink); }
    .payment-validation-dialog::backdrop { background: rgba(16, 20, 24, .62); }
    .payment-validation-header { display: flex; align-items: center; gap: .7rem; margin-bottom: 1rem; }
    .payment-validation-icon { display: grid; flex: 0 0 1.8rem; width: 1.8rem; height: 1.8rem; place-items: center; border: 1px solid currentColor; border-radius: 50%; color: #8a5b00; font-weight: 700; }
    body.dark-mode .payment-validation-icon { color: #ffd36b; }
    .payment-validation-dialog #payment-validation-message { white-space: pre-line; overflow-wrap: anywhere; }
    [data-receipt-status][data-state="error"] { color: var(--danger); font-weight: 600; }
    [data-receipt-status][data-state="success"] { color: var(--success, #26734d); font-weight: 600; }
    .receipt-dialog-header { display: flex; align-items: center; justify-content: space-between; gap: .75rem; padding: .75rem 1rem; border-bottom: 1px solid var(--line); }
    .receipt-dialog-body { display: grid; place-items: center; min-height: 140px; max-height: calc(100dvh - 6rem); overflow: auto; padding: .75rem; }
    .receipt-dialog-body.is-zoomed { display: block; }
    .receipt-dialog-body img { display: block; width: auto; height: auto; max-width: 100%; max-height: calc(100dvh - 8rem); object-fit: contain; }
    .receipt-dialog-body img.is-zoomed { max-width: none; max-height: none; }
    .receipt-dialog-body img[hidden], #payment-receipt-error[hidden] { display: none; }
    #correct-payment-dialog { width: min(640px, calc(100vw - 2rem)); max-height: calc(100dvh - 2rem); overflow-y: auto; }
    @media (max-width: 575px) {
        .payment-receipt-dialog { width: calc(100vw - 1rem); max-height: calc(100dvh - 1rem); }
        .payment-validation-dialog { width: calc(100vw - 1rem); max-height: calc(100dvh - 2rem); padding: 1rem; }
        .receipt-dialog-header { padding: .65rem .75rem; }
        .receipt-dialog-body { max-height: calc(100dvh - 5rem); padding: .5rem; }
        .receipt-dialog-body img { max-height: calc(100dvh - 7rem); }
    }
</style>
<script>
(() => {
    const form = document.getElementById('refund-payment-form');
    if (!form) return;

    const amount = form.querySelector('[name="refund_amount"]');
    const maximum = Number(amount.max);
    const peso = (value) => `₱${Number(value || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    const updateConfirmation = () => {
        const refund = Number(amount.value);
        form.dataset.confirmMessage = [
            'Confirm Refund',
            `Refund amount: ${peso(refund)}`,
            `Current net paid: ${peso(maximum)}`,
            `Remaining after refund: ${peso(Math.max(0, maximum - refund))}`,
        ].join('\n');
    };

    amount.addEventListener('input', updateConfirmation);
    updateConfirmation();
})();
</script>
<script>
(() => {
    const dialog = document.getElementById('payment-receipt-dialog');
    const image = document.getElementById('payment-receipt-image');
    const error = document.getElementById('payment-receipt-error');
    const body = dialog && dialog.querySelector('.receipt-dialog-body');
    const zoomButton = dialog && dialog.querySelector('[data-toggle-receipt-zoom]');
    if (!dialog || !image || !error || !body || !zoomButton) return;

    const resetZoom = () => {
        image.classList.remove('is-zoomed');
        body.classList.remove('is-zoomed');
        zoomButton.textContent = 'Zoom in';
        zoomButton.setAttribute('aria-pressed', 'false');
    };

    const open = (url) => {
        if (!url) return;
        resetZoom();
        image.hidden = true;
        error.hidden = true;
        image.src = url;
        dialog.showModal();
    };

    zoomButton.addEventListener('click', () => {
        const zoomed = !image.classList.contains('is-zoomed');
        image.classList.toggle('is-zoomed', zoomed);
        body.classList.toggle('is-zoomed', zoomed);
        zoomButton.textContent = zoomed ? 'Fit to screen' : 'Zoom in';
        zoomButton.setAttribute('aria-pressed', String(zoomed));
        if (zoomed) body.scrollTo({ top: 0, left: 0 });
    });

    document.querySelectorAll('[data-view-receipt]').forEach((button) => {
        button.addEventListener('click', () => open(button.dataset.receiptUrl));
    });
    image.addEventListener('load', () => {
        image.hidden = false;
        error.hidden = true;
    });
    image.addEventListener('error', () => {
        image.hidden = true;
        error.hidden = false;
    });
    dialog.querySelector('[data-close-receipt]').addEventListener('click', () => dialog.close());
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && dialog.open) {
            event.preventDefault();
            dialog.close();
        }
    });
    dialog.addEventListener('close', () => {
        image.removeAttribute('src');
        image.hidden = true;
        error.hidden = true;
        resetZoom();
    });
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) dialog.close();
    });
})();
</script>
<script>
(() => {
    const dialog = document.getElementById('correct-payment-dialog');
    const form = document.getElementById('correct-payment-form');
    const passwordDialog = document.getElementById('admin-password-dialog');
    const currentReceiptButton = document.getElementById('edit-view-receipt');
    const currentReceiptEmpty = document.getElementById('edit-receipt-empty');
    const receiptFile = document.getElementById('edit_receipt_image');
    const fields = {
        date: document.getElementById('edit_payment_date'),
        type: document.getElementById('edit_payment_type'),
        amount: document.getElementById('edit_amount'),
        method: document.getElementById('edit_payment_method'),
        reference: document.getElementById('edit_reference_number'),
        notes: document.getElementById('edit_notes'),
    };
    form.addEventListener('submit', (event) => {
        if (form.dataset.correctionConfirmed === 'true') {
            delete form.dataset.correctionConfirmed;
            return;
        }
        if (!window.confirm(form.dataset.confirmMessage || 'Confirm this payment correction?')) {
            event.preventDefault();
            event.stopPropagation();
            return;
        }
        form.dataset.correctionConfirmed = 'true';
    });
    passwordDialog?.addEventListener('close', () => {
        if (passwordDialog.returnValue !== 'confirmed') delete form.dataset.correctionConfirmed;
    });
    const open = (values) => {
        form.action = values.action;
        fields.date.value = values.date;
        fields.type.value = values.type;
        fields.amount.value = values.amount;
        fields.method.value = values.method;
        fields.reference.value = values.reference || '';
        fields.notes.value = values.notes || '';
        form.querySelector('[name="payment_id"]').value = values.id;
        form.querySelector('[name="correction_reason"]').value = '';
        form.dataset.confirmMessage = [
            'Confirm payment correction',
            `Payment ID: #${values.id}`,
            `Current amount: ₱${Number(values.amount || 0).toFixed(2)}`,
            'The original values and any replaced receipt will remain in the audit history.',
        ].join('\n');
        receiptFile.value = '';
        receiptFile.dispatchEvent(new Event('change', { bubbles: true }));
        currentReceiptButton.dataset.receiptUrl = values.receiptUrl || '';
        currentReceiptButton.hidden = ! values.receiptUrl;
        currentReceiptEmpty.hidden = Boolean(values.receiptUrl);
        dialog.showModal();
        fields.amount.focus();
    };

    document.querySelectorAll('[data-correct-payment]').forEach((button) => {
        button.addEventListener('click', () => open(button.dataset));
    });
    dialog.querySelector('[data-close-dialog]').addEventListener('click', () => dialog.close());

    @if(session('correcting_payment'))
        const failed = document.querySelector('[data-correct-payment][data-id="{{ session('correcting_payment') }}"]');
        if (failed) open({
            ...failed.dataset,
            date: @json(old('payment_date')),
            type: @json(old('payment_type')),
            amount: @json(old('amount')),
            method: @json(old('payment_method')),
            reference: @json(old('reference_number')),
            notes: @json(old('notes')),
        });
        if (failed) form.querySelector('[name="correction_reason"]').value = @json(old('correction_reason'));
    @endif
})();
</script>
<script>
(() => {
    const button = document.querySelector('[data-print-record]');
    const frame = document.querySelector('[data-print-frame]');
    if (!button || !frame) return;

    button.addEventListener('click', () => {
        const url = button.dataset.printUrl;
        if (!url) return;

        frame.src = `${url}${url.includes('?') ? '&' : '?'}print=${Date.now()}`;
    });
})();
</script>
<script>
(() => {
    const dialog = document.getElementById('payment-validation-dialog');
    const title = document.getElementById('payment-validation-title');
    const message = document.getElementById('payment-validation-message');
    if (!dialog || !title || !message) return;
    let focusTargetAfterClose = null;

    window.showPaymentValidationAlert = (heading, details, focusTarget = null) => {
        title.textContent = heading;
        message.textContent = details;
        focusTargetAfterClose = focusTarget;
        if (!dialog.open) {
            dialog.showModal();
            dialog.querySelector('[data-close-payment-validation]').focus();
        }
    };

    dialog.querySelector('[data-close-payment-validation]').addEventListener('click', () => dialog.close());
    dialog.addEventListener('close', () => {
        if (focusTargetAfterClose instanceof HTMLElement && focusTargetAfterClose.isConnected) {
            focusTargetAfterClose.focus();
        }
        focusTargetAfterClose = null;
    });
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) dialog.close();
    });

    const serverErrors = document.getElementById('payment-server-errors');
    if (serverErrors) {
        try {
            const errors = JSON.parse(serverErrors.textContent || '[]');
            if (Array.isArray(errors) && errors.length > 0) {
                requestAnimationFrame(() => {
                    window.showPaymentValidationAlert(
                        'Please review the payment information',
                        errors.join('\n'),
                    );
                });
            }
        } catch (error) {
            console.error('Unable to display payment validation errors.', error);
        }
    }
})();
</script>
<script>
(() => {
    const fieldLabels = {
        amount: 'Amount Paid',
        payment_type: 'Payment Type',
        payment_method: 'Payment Method',
        payment_date: 'Payment Date',
        correction_reason: 'Correction Reason',
    };
    const messageForInvalidField = (name, field) => {
        if (field.validity.valueMissing) {
            return name === 'payment_type' || name === 'payment_method'
                ? `Please choose the ${fieldLabels[name]}.`
                : `Please enter the ${fieldLabels[name]}.`;
        }
        if (name === 'correction_reason' && field.validity.tooShort) {
            return 'Please enter a Correction Reason of at least 3 characters.';
        }
        if (name === 'amount' && field.validity.rangeUnderflow) {
            return 'Amount Paid must be greater than ₱0.00.';
        }
        if (name === 'amount' && field.validity.rangeOverflow) {
            return 'Amount Paid cannot exceed the remaining balance.';
        }
        if (name === 'payment_date' && field.validity.rangeOverflow) {
            return 'Payment Date cannot be in the future.';
        }
        return `Please check the ${fieldLabels[name] || name.replaceAll('_', ' ')}.`;
    };

    document.querySelectorAll('[data-payment-validation]').forEach((form) => {
        const fields = ['amount', 'payment_type', 'payment_method', 'payment_date'];
        if (form.dataset.paymentValidation === 'correct') fields.push('correction_reason');

        const clearReceiptIssue = () => {
            delete form.dataset.receiptIssue;
            const status = form.querySelector('[data-receipt-status]');
            if (status) delete status.dataset.state;
        };

        form.querySelector('[data-receipt-file]')?.addEventListener('change', clearReceiptIssue);
        form.addEventListener('submit', (event) => {
            const messages = [];
            const invalidFields = [];

            fields.forEach((name) => {
                const field = form.elements.namedItem(name);
                if (!field || !field.willValidate || field.validity.valid) return;
                invalidFields.push(field);
                messages.push(field.validity.valueMissing
                    ? { label: fieldLabels[name] }
                    : { text: messageForInvalidField(name, field) });
            });

            const fileInput = form.querySelector('[data-receipt-file]');
            const receiptFileSelected = Boolean(fileInput?.files?.length);
            const reviewToken = form.querySelector('[name="receipt_review_token"]')?.value;
            const confirmed = form.querySelector('[name="receipt_confirmed"]')?.checked;

            if (receiptFileSelected && !reviewToken) {
                let receiptIssue = null;
                try {
                    receiptIssue = JSON.parse(form.dataset.receiptIssue || 'null');
                } catch {
                    receiptIssue = null;
                }
                messages.push(receiptIssue
                    ? { text: receiptIssue.message, title: receiptIssue.title }
                    : { text: 'Please analyze the uploaded receipt before continuing.' });
            } else if (receiptFileSelected && !confirmed) {
                messages.push({ text: 'Please review and confirm the extracted receipt information before continuing.' });
            }

            if (messages.length === 0) return;

            event.preventDefault();
            event.stopImmediatePropagation();
            let heading = 'Information Required';
            let body;
            if (messages.length === 1 && messages[0].title) {
                heading = messages[0].title;
                body = messages[0].text;
            } else if (messages.length === 1 && messages[0].text) {
                body = messages[0].text;
            } else {
                heading = form.dataset.paymentValidation === 'correct'
                    ? 'Complete the correction details'
                    : 'Complete the payment details';
                body = [
                    form.dataset.paymentValidation === 'correct'
                        ? 'Please complete the following before reviewing the correction:'
                        : 'Please complete the following required fields:',
                    ...messages.map(({ label, text }) => `• ${label || text}`),
                ].join('\n');
            }
            window.showPaymentValidationAlert(heading, body, invalidFields[0] || fileInput);
        }, true);
    });
})();
</script>
<script>
(() => {
    document.querySelectorAll('[data-receipt-form]').forEach((form) => {
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
        const saveButton = form.querySelector('[data-save-payment]') || form.querySelector('button[type="submit"]');
        if (!fileInput || !review || !preview || !previewTrigger || !analyze || !status || !details || !confirmation || !confirmCheckbox || !reviewToken || !clear || !saveButton) return;
        saveButton.dataset.defaultLabel = saveButton.textContent.trim();
        const analyzeLabel = analyze.textContent.trim();
        let analysisController = null;

        const analyzeReceipt = async () => {
            const file = fileInput.files && fileInput.files[0];
            if (!file) {
                window.showPaymentValidationAlert('Receipt Required', 'Please upload a receipt image before analyzing it.');
                return;
            }

            analysisController?.abort();
            const controller = new AbortController();
            analysisController = controller;
            analyze.disabled = true;
            analyze.textContent = 'Analyzing…';
            status.textContent = 'Analyzing receipt locally…';
            status.dataset.state = 'pending';
            details.hidden = true;
            reviewToken.value = '';
            confirmCheckbox.checked = false;
            confirmation.hidden = true;
            const data = new FormData(form);
            data.delete('_method');

            try {
                const response = await fetch(form.dataset.analyzeUrl, {
                    method: 'POST',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: data,
                    credentials: 'same-origin',
                    signal: controller.signal,
                });
                const result = await response.json();
                if (controller !== analysisController) return;
                if (!response.ok) {
                    const validationMessage = Object.values(result.errors || {}).flat().find((item) => typeof item === 'string');
                    const detail = validationMessage || result.message || 'The receipt could not be validated.';
                    const isDuplicate = /duplicate receipt detected/i.test(detail);
                    const isDuplicateReference = /duplicate transaction reference/i.test(detail);
                    const isReceiptNotDetected = /receipt not detected|does not appear to be a completed payment receipt/i.test(detail);
                    const isAnalysisFailure = response.status === 503;
                    const title = isDuplicate
                        ? 'Duplicate Receipt Detected'
                        : isDuplicateReference
                            ? 'Duplicate Transaction Reference'
                            : isAnalysisFailure
                                ? 'Receipt Analysis Failed'
                                : isReceiptNotDetected
                                    ? 'Invalid Receipt'
                                    : 'Receipt Validation Failed';
                    const compactStatus = isDuplicate
                        ? '⚠ Duplicate receipt detected'
                        : isDuplicateReference
                            ? '⚠ Duplicate reference detected'
                            : isAnalysisFailure
                                ? '⚠ Receipt analysis failed'
                                : isReceiptNotDetected
                                    ? '⚠ Receipt requires a valid payment image'
                                    : '⚠ Receipt validation failed';
                    const popupDetail = isAnalysisFailure
                        ? 'The system could not analyze this receipt. Please try a clearer image.'
                        : isDuplicate
                            ? `${detail}\nPlease upload a different receipt or review the existing payment.`
                            : isDuplicateReference
                                ? `${detail}\nPlease verify the existing payment before continuing.`
                                : isReceiptNotDetected
                                    ? `${detail.replace(/^Receipt Not Detected\.\s*/i, '')}\nPlease upload a valid payment receipt.`
                                    : detail;
                    form.dataset.receiptIssue = JSON.stringify({ title, message: popupDetail });
                    status.textContent = compactStatus;
                    status.dataset.state = 'error';
                    window.showPaymentValidationAlert(title, popupDetail);
                    return;
                }

                const receipt = result.receipt || {};
                delete form.dataset.receiptIssue;
                status.textContent = '✓ Receipt analyzed successfully';
                status.dataset.state = 'success';
                details.textContent = [
                    `Provider: ${receipt.provider || 'Not identified'}`,
                    `Amount: ${receipt.amount ? `₱${receipt.amount}` : 'Not detected'}`,
                    `Reference: ${receipt.reference_number || 'Not detected'}`,
                    `Method: ${receipt.payment_method || 'Select manually'}`,
                    `Date: ${receipt.payment_date || 'Select manually'}`,
                ].join(' · ');
                details.hidden = false;
                if (receipt.amount) form.querySelector('[name="amount"]').value = receipt.amount;
                if (receipt.reference_number) form.querySelector('[name="reference_number"]').value = receipt.reference_number;
                if (receipt.payment_method && [...form.querySelector('[name="payment_method"]').options].some((option) => option.value === receipt.payment_method)) {
                    form.querySelector('[name="payment_method"]').value = receipt.payment_method;
                }
                if (receipt.payment_date) form.querySelector('[name="payment_date"]').value = receipt.payment_date;
                reviewToken.value = result.review_token;
                confirmation.hidden = false;
                confirmCheckbox.required = true;
                analyze.textContent = 'Analyze again';
                if (saveButton.textContent.trim() === 'Save Payment') saveButton.textContent = 'Confirm & Record Payment';
            } catch {
                if (controller !== analysisController || controller.signal.aborted) return;
                const detail = 'The system could not analyze this receipt. Please try a clearer image.';
                form.dataset.receiptIssue = JSON.stringify({ title: 'Receipt Analysis Failed', message: detail });
                status.textContent = '⚠ Receipt analysis failed';
                status.dataset.state = 'error';
                window.showPaymentValidationAlert('Receipt Analysis Failed', detail);
            } finally {
                if (controller === analysisController) {
                    analysisController = null;
                    analyze.disabled = false;
                    if (analyze.textContent.trim() === 'Analyzing…') analyze.textContent = 'Analyze again';
                }
            }
        };

        const resetReview = () => {
            analysisController?.abort();
            analysisController = null;
            analyze.disabled = false;
            analyze.textContent = analyzeLabel;
            reviewToken.value = '';
            delete form.dataset.receiptIssue;
            confirmCheckbox.checked = false;
            confirmCheckbox.required = Boolean(fileInput.files && fileInput.files.length);
            confirmation.hidden = ! (fileInput.files && fileInput.files.length);
            details.hidden = true;
            details.textContent = '';
            status.textContent = '';
            delete status.dataset.state;
            previewTrigger.hidden = true;
            delete previewTrigger.dataset.receiptUrl;
            preview.removeAttribute('src');
            saveButton.textContent = saveButton.dataset.defaultLabel;
        };

        fileInput.addEventListener('change', () => {
            resetReview();
            const file = fileInput.files && fileInput.files[0];
            review.hidden = ! file;
            if (file) {
                const reader = new FileReader();
                reader.addEventListener('load', () => {
                    if (fileInput.files[0] !== file) return;
                    preview.src = String(reader.result || '');
                    previewTrigger.dataset.receiptUrl = preview.src;
                    previewTrigger.hidden = false;
                    analyzeReceipt();
                });
                reader.readAsDataURL(file);
            }
        });

        clear.addEventListener('click', () => {
            fileInput.value = '';
            fileInput.dispatchEvent(new Event('change', { bubbles: true }));
        });

        analyze.addEventListener('click', () => {
            analyzeReceipt();
        });
    });
})();
</script>
@endsection
