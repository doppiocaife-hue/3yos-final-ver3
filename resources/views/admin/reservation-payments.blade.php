@extends('layouts.admin')

@php
    $peso = fn ($amount) => '₱' . number_format((float) $amount, 2);
    $balanceCents = $reservation->remainingBalanceCents();
    $contractSet = $reservation->total_cost !== null;
    $fullyPaid = $contractSet && $balanceCents === 0 && $payments->isNotEmpty();
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
            <a class="btn btn-outline-secondary" href="{{ route('admin.reservations.payments.print', $reservation) }}" target="_blank" rel="noopener">View / print record</a>
        </div>
    </div>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())
        <div class="alert alert-danger" role="alert"><ul class="mb-0 ps-3">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <div class="summary-grid" aria-label="Payment summary">
        <div class="summary-item summary-item--accent"><span>Contract price</span><strong>{{ $contractSet ? $peso($reservation->total_cost) : 'Not set' }}</strong></div>
        <div class="summary-item"><span>Payment status</span><strong><span class="status-badge status-badge--{{ \App\Models\Reservation::paymentStatusBadge($reservation->payment_status) }}">{{ \App\Models\Reservation::paymentStatusLabel($reservation->payment_status) }}</span></strong></div>
        <div class="summary-item"><span>Total paid</span><strong>{{ $peso($reservation->amount_paid) }}</strong></div>
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
                    <div class="alert alert-success mb-0">This booking is fully paid. Edit or delete a payment below to make changes.</div>
                @else
                    <form method="POST" action="{{ route('admin.reservations.payments.store', $reservation) }}" data-submit-once data-confirm-message="Record this payment? The balance and status will be recalculated.">
                        @csrf
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
                                <label class="form-label" for="notes">Notes <span class="text-muted fw-normal">(optional)</span></label>
                                <textarea class="form-control" id="notes" name="notes" rows="2" maxlength="1000" placeholder="Reference number, who paid, etc.">{{ old('notes') }}</textarea>
                            </div>
                        </div>
                        <div class="d-flex justify-content-end mt-3"><button class="btn luxury-btn" type="submit">Save Payment</button></div>
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
                        <input class="form-control @error('total_cost') is-invalid @enderror" type="number" id="total_cost" name="total_cost" value="{{ old('total_cost', $reservation->total_cost) }}" min="{{ number_format((float) $reservation->amount_paid, 2, '.', '') }}" step="0.01" inputmode="decimal" required>
                        @if((float) $reservation->amount_paid > 0)<div class="form-text">Cannot be lower than the {{ $peso($reservation->amount_paid) }} already paid.</div>@endif
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

    <section aria-labelledby="history-title">
        <h5 class="fw-bold mb-2" id="history-title">Payment history</h5>
        <div class="table-responsive">
            <table class="table align-middle payment-history">
                <thead>
                    <tr><th>Date</th><th>Payment type</th><th class="text-end">Amount</th><th>Method</th><th>Notes</th><th>Recorded by</th><th class="text-end">Actions</th></tr>
                </thead>
                <tbody>
                    @forelse($payments as $payment)
                        <tr>
                            <td class="text-nowrap">{{ $payment->payment_date->format('m/d/Y') }}</td>
                            <td>{{ $payment->payment_type }}</td>
                            <td class="text-end money fw-bold">{{ $peso($payment->amount) }}</td>
                            <td>{{ $payment->payment_method }}</td>
                            <td class="text-muted payment-notes">{{ $payment->notes ?: '—' }}</td>
                            <td class="text-muted">{{ $payment->recorded_by_name ?: '—' }}<br><small>{{ $payment->created_at?->format('M j, Y g:i A') }}</small></td>
                            <td class="text-end">
                                <div class="table-actions">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" data-edit-payment
                                        data-action="{{ route('admin.reservations.payments.update', [$reservation, $payment]) }}"
                                        data-id="{{ $payment->id }}"
                                        data-date="{{ $payment->payment_date->toDateString() }}"
                                        data-type="{{ $payment->payment_type }}"
                                        data-amount="{{ number_format($payment->amount, 2, '.', '') }}"
                                        data-method="{{ $payment->payment_method }}"
                                        data-notes="{{ $payment->notes }}">Edit</button>
                                    <form method="POST" action="{{ route('admin.reservations.payments.destroy', [$reservation, $payment]) }}" data-submit-once data-confirm-message="Delete this {{ $peso($payment->amount) }} payment? The total paid and balance will be recalculated.">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">No payments recorded yet.</td></tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr><th colspan="2">Total paid</th><th class="text-end money">{{ $peso($reservation->amount_paid) }}</th><th colspan="4"></th></tr>
                    <tr><th colspan="2">Balance</th><th class="text-end money">{{ $contractSet ? $peso($balanceCents / 100) : '—' }}</th><th colspan="4"></th></tr>
                </tfoot>
            </table>
        </div>
    </section>
</div>

<dialog id="edit-payment-dialog" aria-labelledby="edit-payment-title">
    <h2 id="edit-payment-title" class="h5 mb-3">Edit payment</h2>
    @if($errors->editPayment->any())
        <div class="alert alert-danger" role="alert"><ul class="mb-0 ps-3">@foreach($errors->editPayment->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    <form method="POST" id="edit-payment-form" action="" data-submit-once data-confirm-message="Save changes to this payment? The balance will be recalculated.">
        @csrf @method('PUT')
        <div class="row g-3">
            <div class="col-sm-6"><label class="form-label" for="edit_payment_date">Payment date</label><input class="form-control" type="date" id="edit_payment_date" name="payment_date" max="{{ now()->toDateString() }}" required></div>
            <div class="col-sm-6"><label class="form-label" for="edit_payment_type">Payment type</label><select class="form-select" id="edit_payment_type" name="payment_type" required>@foreach($types as $type)<option value="{{ $type }}">{{ $type }}</option>@endforeach</select></div>
            <div class="col-sm-6"><label class="form-label" for="edit_amount">Amount (₱)</label><input class="form-control" type="number" id="edit_amount" name="amount" min="0.01" step="0.01" inputmode="decimal" required></div>
            <div class="col-sm-6"><label class="form-label" for="edit_payment_method">Payment method</label><select class="form-select" id="edit_payment_method" name="payment_method" required>@foreach($methods as $method)<option value="{{ $method }}">{{ $method }}</option>@endforeach</select></div>
            <div class="col-12"><label class="form-label" for="edit_notes">Notes <span class="text-muted fw-normal">(optional)</span></label><textarea class="form-control" id="edit_notes" name="notes" rows="2" maxlength="1000"></textarea></div>
        </div>
        <div class="d-flex justify-content-end gap-2 mt-4">
            <button type="button" class="btn btn-outline-secondary" data-close-dialog>Cancel</button>
            <button type="submit" class="btn luxury-btn">Save changes</button>
        </div>
    </form>
</dialog>

<style>
    .payment-history { min-width: 760px; }
    .payment-history tfoot th { border-top: 1px solid var(--line); background: #f7f9fa; font-size: .8rem; }
    body.dark-mode .payment-history tfoot th { background: #223641; }
    .payment-notes { max-width: 260px; overflow-wrap: anywhere; }
    .summary-item .status-badge { font-family: "DM Sans", sans-serif; }
</style>
<script>
(() => {
    const dialog = document.getElementById('edit-payment-dialog');
    const form = document.getElementById('edit-payment-form');
    const fields = {
        date: document.getElementById('edit_payment_date'),
        type: document.getElementById('edit_payment_type'),
        amount: document.getElementById('edit_amount'),
        method: document.getElementById('edit_payment_method'),
        notes: document.getElementById('edit_notes'),
    };
    const open = (values) => {
        form.action = values.action;
        fields.date.value = values.date;
        fields.type.value = values.type;
        fields.amount.value = values.amount;
        fields.method.value = values.method;
        fields.notes.value = values.notes || '';
        dialog.showModal();
        fields.amount.focus();
    };

    document.querySelectorAll('[data-edit-payment]').forEach((button) => {
        button.addEventListener('click', () => open(button.dataset));
    });
    dialog.querySelector('[data-close-dialog]').addEventListener('click', () => dialog.close());

    // A rejected edit comes back with its input; reopen the dialog so the admin can correct it.
    @if(session('editing_payment'))
        const failed = document.querySelector('[data-edit-payment][data-id="{{ session('editing_payment') }}"]');
        if (failed) open({
            ...failed.dataset,
            date: @json(old('payment_date')),
            type: @json(old('payment_type')),
            amount: @json(old('amount')),
            method: @json(old('payment_method')),
            notes: @json(old('notes')),
        });
    @endif
})();
</script>
@endsection
