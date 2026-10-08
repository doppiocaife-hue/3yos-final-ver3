<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReservationPayment extends Model
{
    public const TYPES = ['Downpayment', 'Partial Payment', 'Final Payment', 'Full Payment'];

    public const METHODS = ['Cash', 'GCash', 'Maya', 'Bank Transfer', 'Other'];

    protected $fillable = [
        'reservation_id',
        'payment_date',
        'payment_type',
        'amount',
        'payment_method',
        'reference_number',
        'notes',
        'receipt_image_path',
        'receipt_sha256',
        'reference_sha256',
        'recorded_by_user_id',
        'recorded_by_name',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount' => 'float',
    ];

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    public function corrections()
    {
        return $this->hasMany(ReservationPaymentCorrection::class, 'payment_id')->latest('id');
    }
}
