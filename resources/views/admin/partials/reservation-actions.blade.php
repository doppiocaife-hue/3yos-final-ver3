<div class="reservation-actions">
    @php($outstandingBalance = $reservation->total_cost === null ? null : max(0, (float) $reservation->total_cost - (float) ($reservation->amount_paid ?? 0)))
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
        <form method="POST" action="{{ route('admin.reservations.status', $reservation) }}" data-confirm-message="Update payment details for this reservation?">@csrf @method('PATCH')
            <input type="hidden" name="status" value="{{ $reservation->status }}">
            <select name="payment_type" class="form-select form-select-sm">
                <option value="Unpaid" @selected(($reservation->payment_type ?? $reservation->payment_status) === 'Unpaid')>Unpaid</option>
                <option value="Downpayment" @selected(($reservation->payment_type ?? $reservation->payment_status) === 'Downpayment')>Downpayment</option>
                <option value="Full Payment" @selected(($reservation->payment_type ?? $reservation->payment_status) === 'Full Payment')>Full Payment</option>
            </select>
            <input type="number" name="amount_paid" min="0" step="1" value="{{ old('amount_paid', (int) ($reservation->amount_paid ?? 0)) }}" class="form-control form-control-sm" placeholder="Amount">
            <button class="btn btn-sm luxury-btn" type="submit">Save</button>
        </form>
        @if($outstandingBalance > 0)
            <div class="payment-warning" role="alert">Unpaid balance: &#8369;{{ number_format($outstandingBalance, 2) }}</div>
        @endif
    </div>
</div>
