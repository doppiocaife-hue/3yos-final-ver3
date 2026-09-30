<div class="reservation-actions">
    @php($reservationFinancials = $reservation->financials())
    @php($outstandingBalance = $reservationFinancials['remaining_balance_cents'] === null ? null : $reservationFinancials['remaining_balance_cents'] / 100)
    @if($reservation->status === 'pending')
        <form method="POST" action="{{ route('admin.reservations.status', $reservation) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="confirmed"><button class="btn btn-sm btn-success quick-action" type="submit">Accept</button></form>
        <form method="POST" action="{{ route('admin.reservations.status', $reservation) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="cancelled"><button class="btn btn-sm btn-danger quick-action" type="submit">Cancel</button></form>
    @endif
    <div class="reservation-action-group">
        <span class="reservation-action-label">Status</span>
        <form method="POST" action="{{ route('admin.reservations.status', $reservation) }}" data-confirm-status>@csrf @method('PATCH')
            <select name="status" class="form-select form-select-sm status-select status-select--{{ $reservation->status }}">
                <option value="pending" @selected($reservation->status === 'pending')>Pending</option>
                <option value="confirmed" @selected($reservation->status === 'confirmed')>Accepted</option>
                <option value="completed" @selected($reservation->status === 'completed')>Completed</option>
                <option value="cancelled" @selected($reservation->status === 'cancelled')>Cancelled</option>
            </select>
            <button class="btn btn-sm luxury-btn" type="submit">Save</button>
        </form>
    </div>
    @if($reservation->status === 'confirmed')
        <div class="reservation-action-group">
            <span class="reservation-action-label">Schedule & package</span>
            <form method="POST" action="{{ route('admin.reservations.status', $reservation) }}" data-confirm-message="Update this reservation's schedule and package?">
                @csrf @method('PATCH')
                <input type="hidden" name="status" value="confirmed">
                <select name="package_id" class="form-select form-select-sm">
                    @foreach($packages as $package)
                        <option value="{{ $package->id }}" @selected($reservation->package_id === $package->id)>{{ $package->name }}</option>
                    @endforeach
                </select>
                <input type="date" name="event_date" value="{{ $reservation->event_date }}" class="form-control form-control-sm" required>
                <input type="time" name="event_time" value="{{ $reservation->event_time }}" class="form-control form-control-sm" required>
                <button class="btn btn-sm luxury-btn" type="submit">Save</button>
            </form>
        </div>
    @endif
    <div class="reservation-action-group">
        <span class="reservation-action-label">Contract price</span>
        <form method="POST" action="{{ route('admin.reservations.status', $reservation) }}" data-confirm-message="Update the contract price for this reservation?">@csrf @method('PATCH')
            <input type="hidden" name="status" value="{{ $reservation->status }}">
            <input type="number" name="total_cost" min="0" step="1" value="{{ old('total_cost', $reservation->total_cost) }}" class="form-control form-control-sm" placeholder="Contract price" required>
            <button class="btn btn-sm luxury-btn" type="submit">Save</button>
        </form>
    </div>
    <div class="reservation-action-group">
        <span class="reservation-action-label">Payment</span>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <span class="status-badge status-badge--{{ \App\Models\Reservation::paymentStatusBadge($reservation->payment_status) }}">{{ \App\Models\Reservation::paymentStatusLabel($reservation->payment_status) }}</span>
            <span class="small text-muted">Paid &#8369;{{ number_format($reservationFinancials['gross_paid_cents'] / 100, 2) }}@if($reservationFinancials['total_refunded_cents'] > 0) · Refunded &#8369;{{ number_format($reservationFinancials['total_refunded_cents'] / 100, 2) }} · Net &#8369;{{ number_format($reservationFinancials['net_paid_cents'] / 100, 2) }}@endif @if($reservation->payment_due_date)· Due {{ $reservation->payment_due_date->format('M j, Y') }}@endif</span>
            <a class="btn btn-sm btn-outline-secondary ms-auto" href="{{ route('admin.reservations.payments', $reservation) }}">Manage payments</a>
        </div>
        @if($outstandingBalance > 0)
            <div class="payment-warning" role="alert">Unpaid balance: &#8369;{{ number_format($outstandingBalance, 2) }}</div>
        @endif
    </div>
</div>
