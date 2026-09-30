<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Reservation;
use App\Models\ReservationPayment;
use App\Models\ReservationRefund;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ReservationPaymentController extends Controller
{
    public function index(Reservation $reservation)
    {
        $reservation->ensurePaymentLedger();
        $reservation->recalculatePaymentTotals();
        $reservation->load('payments', 'refunds', 'package');
        $transactions = $this->transactions($reservation);

        return view('admin.reservation-payments', [
            'reservation' => $reservation,
            'payments' => $reservation->payments,
            'refunds' => $reservation->refunds,
            'transactions' => $transactions,
            'financials' => $reservation->financials(),
            'types' => ReservationPayment::TYPES,
            'methods' => ReservationPayment::METHODS,
            'suggestedType' => $reservation->payments->isEmpty() ? 'Downpayment' : 'Partial Payment',
            'refundRequestKey' => (string) Str::uuid(),
        ]);
    }

    public function print(Reservation $reservation)
    {
        $reservation->ensurePaymentLedger();
        $reservation->recalculatePaymentTotals();
        $reservation->load('payments', 'refunds', 'package');
        $transactions = $this->transactions($reservation);

        return view('admin.reservation-payments-print', [
            'reservation' => $reservation,
            'payments' => $reservation->payments,
            'refunds' => $reservation->refunds,
            'transactions' => $transactions,
            'financials' => $reservation->financials(),
        ]);
    }

    public function store(Request $request, Reservation $reservation): RedirectResponse
    {
        $data = $this->validatePayment($request);

        $created = DB::transaction(function () use ($request, $reservation, $data) {
            $reservation = Reservation::whereKey($reservation->id)->lockForUpdate()->firstOrFail();
            $reservation->ensurePaymentLedger();
            $reservation->recalculatePaymentTotals();

            if ($reservation->total_cost === null) {
                throw ValidationException::withMessages(['amount' => 'Set the contract price before recording payments.']);
            }

            // A double-clicked Save posts the same payment twice within moments; keep only the first.
            $duplicate = $reservation->payments()
                ->where('amount', $data['amount'])
                ->whereDate('payment_date', $data['payment_date'])
                ->where('payment_method', $data['payment_method'])
                ->where('payment_type', $data['payment_type'])
                ->where('created_at', '>=', now()->subSeconds(15))
                ->exists();
            if ($duplicate) {
                return false;
            }

            $this->assertWithinBalance($data['amount'], $reservation->remainingBalanceCents());

            $reservation->payments()->create($data + [
                'recorded_by_user_id' => $request->session()->get('admin_user_id'),
                'recorded_by_name' => $request->session()->get('admin_name', 'Administrator'),
            ]);
            $reservation->recalculatePaymentTotals();

            return true;
        });

        return redirect()->route('admin.reservations.payments', $reservation)->with(
            'success',
            $created ? 'Payment of '.$this->peso($data['amount']).' recorded.' : 'That payment was already recorded, so the repeat submission was ignored.'
        );
    }

    public function update(Request $request, Reservation $reservation, ReservationPayment $payment): RedirectResponse
    {
        try {
            $data = $this->validatePayment($request);

            DB::transaction(function () use ($reservation, $payment, $data) {
                $reservation = Reservation::whereKey($reservation->id)->lockForUpdate()->firstOrFail();
                $payment = $reservation->payments()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
                $reservation->ensurePaymentLedger();
                $reservation->recalculatePaymentTotals();

                // This payment's current amount is freed up before checking the new one.
                $financials = $reservation->financials();
                $available = ($financials['remaining_balance_cents'] ?? 0) + Reservation::toCents($payment->amount);
                $this->assertWithinBalance($data['amount'], $available, true);
                $grossAfterEdit = $financials['gross_paid_cents'] - Reservation::toCents($payment->amount) + Reservation::toCents($data['amount']);
                if ($grossAfterEdit < $financials['total_refunded_cents']) {
                    throw ValidationException::withMessages([
                        'amount' => 'The edited payment cannot be lower than the refunds already processed.',
                    ]);
                }

                $payment->update($data);
                $reservation->recalculatePaymentTotals();
            });
        } catch (ValidationException $exception) {
            // Reopen the edit dialog with its own errors instead of flagging the add-payment form.
            return redirect()->route('admin.reservations.payments', $reservation)
                ->withErrors($exception->errors(), 'editPayment')
                ->withInput()
                ->with('editing_payment', $payment->id);
        }

        return redirect()->route('admin.reservations.payments', $reservation)->with('success', 'Payment updated and balance recalculated.');
    }

    public function destroy(Reservation $reservation, ReservationPayment $payment): RedirectResponse
    {
        DB::transaction(function () use ($reservation, $payment) {
            $reservation = Reservation::whereKey($reservation->id)->lockForUpdate()->firstOrFail();
            $payment = $reservation->payments()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $reservation->ensurePaymentLedger();
            $financials = $reservation->financials();

            if ($financials['gross_paid_cents'] - Reservation::toCents($payment->amount) < $financials['total_refunded_cents']) {
                throw ValidationException::withMessages([
                    'payment' => 'This payment cannot be deleted because processed refunds depend on the total amount received.',
                ]);
            }

            $payment->delete();
            $reservation->recalculatePaymentTotals();
        });

        return redirect()->route('admin.reservations.payments', $reservation)->with('success', 'Payment of '.$this->peso($payment->amount).' deleted and balance recalculated.');
    }

    public function updateDetails(Request $request, Reservation $reservation): RedirectResponse
    {
        $data = $request->validate([
            'total_cost' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'payment_due_date' => ['nullable', 'date'],
        ], [
            'total_cost.required' => 'Enter the contract price.',
            'total_cost.min' => 'The contract price cannot be negative.',
        ]);

        DB::transaction(function () use ($reservation, $data) {
            $reservation = Reservation::whereKey($reservation->id)->lockForUpdate()->firstOrFail();
            $reservation->ensurePaymentLedger();
            $reservation->recalculatePaymentTotals();

            $financials = $reservation->financials();
            if (Reservation::toCents($data['total_cost']) < $financials['net_paid_cents']) {
                throw ValidationException::withMessages([
                    'total_cost' => 'The contract price cannot be lower than the '.$this->peso($financials['net_paid_cents'] / 100).' net amount paid.',
                ]);
            }

            $reservation->update($data);
            $reservation->recalculatePaymentTotals();
        });

        return redirect()->route('admin.reservations.payments', $reservation)->with('success', 'Contract price and payment due date saved.');
    }

    public function storeRefund(Request $request, Reservation $reservation): RedirectResponse
    {
        $data = $request->validate([
            'request_key' => ['required', 'uuid'],
            'refund_date' => ['required', 'date', 'before_or_equal:today'],
            'refund_amount' => ['required', 'numeric', 'gt:0', 'max:9999999999', 'decimal:0,2'],
            'refund_method' => ['required', Rule::in(ReservationPayment::METHODS)],
            'reason' => ['nullable', 'string', 'max:1000'],
        ], [
            'refund_date.required' => 'Choose the refund date.',
            'refund_date.before_or_equal' => 'The refund date cannot be in the future.',
            'refund_amount.required' => 'Enter the refund amount.',
            'refund_amount.gt' => 'The refund amount must be greater than ₱0.00.',
            'refund_amount.decimal' => 'The refund amount can have at most two decimal places.',
            'refund_method.required' => 'Choose the refund method.',
            'refund_method.in' => 'Choose Cash, GCash, Bank Transfer, or Other.',
        ]);
        $data['refund_amount'] = round((float) $data['refund_amount'], 2);

        $created = DB::transaction(function () use ($request, $reservation, $data): bool {
            $reservation = Reservation::whereKey($reservation->id)->lockForUpdate()->firstOrFail();
            $reservation->ensurePaymentLedger();

            $duplicate = ReservationRefund::where('request_key', $data['request_key'])->first();
            if ($duplicate) {
                if ($duplicate->reservation_id !== $reservation->id) {
                    throw ValidationException::withMessages([
                        'refund_amount' => 'This refund submission has already been used. Refresh the page and try again.',
                    ]);
                }

                return false;
            }

            $financials = $reservation->financials();
            $amountCents = Reservation::toCents($data['refund_amount']);
            if ($amountCents > $financials['net_paid_cents']) {
                throw ValidationException::withMessages([
                    'refund_amount' => 'Refund cannot exceed the total amount paid.',
                ]);
            }

            $refund = $reservation->refunds()->create([
                'refund_date' => $data['refund_date'],
                'amount' => $data['refund_amount'],
                'refund_method' => $data['refund_method'],
                'reason' => $data['reason'] ?? null,
                'status' => 'completed',
                'request_key' => $data['request_key'],
                'recorded_by_user_id' => $request->session()->get('admin_user_id'),
                'recorded_by_name' => $request->session()->get('admin_name', 'Administrator'),
            ]);

            $reservation->recalculatePaymentTotals();

            ActivityLog::create([
                'user_id' => $request->session()->get('admin_user_id'),
                'actor_name' => $request->session()->get('admin_name', 'Unknown administrator'),
                'actor_email' => $request->session()->get('admin_email'),
                'actor_role' => $request->session()->get('admin_role', 'limited'),
                'action' => 'Processed refund',
                'method' => $request->method(),
                'ip_address' => $request->ip(),
                'activity_date' => now()->toDateString(),
                'activity_time' => now()->toTimeString(),
                'description' => 'Processed a ₱'.number_format($data['refund_amount'], 2).' '.$data['refund_method'].' refund dated '.$refund->refund_date->format('Y-m-d').' for reservation '.($reservation->reservation_code ?: '#'.$reservation->id).'.',
            ]);

            return true;
        });

        return redirect()->route('admin.reservations.payments', $reservation)->with(
            'success',
            $created
                ? 'Refund of '.$this->peso($data['refund_amount']).' processed.'
                : 'That refund submission was already processed.',
        );
    }

    private function validatePayment(Request $request): array
    {
        $data = $request->validate([
            'payment_date' => ['required', 'date', 'before_or_equal:today'],
            'payment_type' => ['required', Rule::in(ReservationPayment::TYPES)],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999', 'decimal:0,2'],
            'payment_method' => ['required', Rule::in(ReservationPayment::METHODS)],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'payment_date.required' => 'Choose the payment date.',
            'payment_date.before_or_equal' => 'The payment date cannot be in the future.',
            'payment_type.required' => 'Choose the payment type.',
            'amount.required' => 'Enter the payment amount.',
            'amount.numeric' => 'The amount must be a number.',
            'amount.gt' => 'The amount must be greater than ₱0.00.',
            'amount.decimal' => 'The amount can have at most two decimal places.',
            'payment_method.required' => 'Choose how the customer paid.',
            'payment_method.in' => 'Choose Cash, GCash, Bank Transfer, or Other.',
        ]);
        $data['amount'] = round((float) $data['amount'], 2);

        return $data;
    }

    private function assertWithinBalance(float $amount, ?int $availableCents, bool $editing = false): void
    {
        if ($availableCents === null) {
            throw ValidationException::withMessages(['amount' => 'Set the contract price before recording payments.']);
        }

        if (Reservation::toCents($amount) > $availableCents) {
            throw ValidationException::withMessages([
                'amount' => $editing
                    ? 'This payment can be at most '.$this->peso($availableCents / 100).' (the remaining balance plus its current amount).'
                    : ($availableCents === 0
                    ? 'This booking is already fully paid.'
                    : 'The amount cannot exceed the remaining balance of '.$this->peso($availableCents / 100).'.'),
            ]);
        }
    }

    private function peso(float|int|null $amount): string
    {
        return '₱'.number_format((float) $amount, 2);
    }

    private function transactions(Reservation $reservation)
    {
        $payments = $reservation->payments->map(fn (ReservationPayment $payment) => (object) [
            'kind' => 'payment',
            'date' => $payment->payment_date,
            'type' => $payment->payment_type,
            'amount' => $payment->amount,
            'method' => $payment->payment_method,
            'notes' => $payment->notes,
            'recorded_by_name' => $payment->recorded_by_name,
            'created_at' => $payment->created_at,
            'id' => $payment->id,
            'payment' => $payment,
        ]);

        $refunds = $reservation->refunds->map(fn (ReservationRefund $refund) => (object) [
            'kind' => 'refund',
            'date' => $refund->refund_date,
            'type' => 'Refund',
            'amount' => $refund->amount,
            'method' => $refund->refund_method,
            'notes' => $refund->reason,
            'recorded_by_name' => $refund->recorded_by_name,
            'created_at' => $refund->created_at,
            'id' => $refund->id,
            'payment' => null,
        ]);

        return $payments->concat($refunds)->sortBy([
            ['date', 'asc'],
            ['created_at', 'asc'],
            ['id', 'asc'],
        ])->values();
    }
}
