<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reservation extends Model
{
    protected $fillable = [
        'client_id',
        'package_id',
        'full_name',
        'contact_number',
        'email',
        'address',
        'event_type',
        'event_date',
        'event_time',
        'venue',
        'guest_count',
        'estimated_budget',
        'total_cost',
        'additional_services',
        'special_requests',
        'additional_notes',
        'service_contract',
        'service_contracts',
        'status',
        'payment_status',
        'payment_type',
        'amount_paid',
        'balance',
        'payment_due_date',
        'reservation_code',
    ];

    protected $casts = [
        'estimated_budget' => 'float',
        'total_cost' => 'float',
        'amount_paid' => 'float',
        'balance' => 'float',
        'payment_due_date' => 'date',
        'service_contracts' => 'array',
    ];

    public function contractFiles(): array
    {
        return array_values(array_filter(array_merge(
            $this->service_contract ? [$this->service_contract] : [],
            $this->service_contracts ?? [],
        )));
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function package()
    {
        return $this->belongsTo(Package::class);
    }

    public function payments()
    {
        return $this->hasMany(ReservationPayment::class)->orderBy('payment_date')->orderBy('id');
    }

    public function refunds()
    {
        return $this->hasMany(ReservationRefund::class)->orderBy('refund_date')->orderBy('id');
    }

    public function financials(): array
    {
        return app(\App\Services\ReservationFinancialService::class)->calculate($this);
    }

    /** Display label for the stored payment_status ("Unpaid" is kept in storage for existing filters and reports). */
    public static function paymentStatusLabel(?string $status): string
    {
        return $status === null || $status === 'Unpaid' ? 'No Payment' : $status;
    }

    /** Maps a payment status onto the shared status-badge palette. */
    public static function paymentStatusBadge(?string $status): string
    {
        return match ($status) {
            'Fully Paid' => 'confirmed',
            'Partial Payment' => 'completed',
            'Downpayment' => 'pending',
            default => 'neutral',
        };
    }

    /**
     * Bookings paid before the payment history existed only have a running total.
     * Record that total as one opening entry so the ledger and the total always agree.
     */
    public function ensurePaymentLedger(): void
    {
        if ((float) ($this->amount_paid ?? 0) <= 0 || $this->payments()->exists()) {
            return;
        }

        $this->payments()->create([
            'payment_date' => ($this->updated_at ?? now())->toDateString(),
            'payment_type' => $this->payment_status === 'Fully Paid' || $this->payment_type === 'Full Payment' ? 'Full Payment' : 'Downpayment',
            'amount' => $this->amount_paid,
            'payment_method' => 'Other',
            'notes' => 'Opening balance carried over from the previous payment tracker.',
            'recorded_by_name' => 'System',
        ]);
    }

    /** Recomputes the stored net totals and status from the payment and refund history. */
    public function recalculatePaymentTotals(): void
    {
        app(\App\Services\ReservationFinancialService::class)->recalculate($this);
    }

    public function remainingBalanceCents(): ?int
    {
        return $this->financials()['remaining_balance_cents'];
    }

    public static function toCents(float|int|string|null $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }
}
