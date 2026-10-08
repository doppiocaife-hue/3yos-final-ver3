<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReservationPaymentCorrection extends Model
{
    protected $fillable = [
        'reservation_id',
        'payment_id',
        'admin_user_id',
        'admin_name',
        'admin_email',
        'reason',
        'original_values',
        'corrected_values',
        'ocr_used',
        'receipt_validation_result',
        'duplicate_check_result',
    ];

    protected function casts(): array
    {
        return [
            'original_values' => 'array',
            'corrected_values' => 'array',
            'ocr_used' => 'boolean',
        ];
    }

    public function payment()
    {
        return $this->belongsTo(ReservationPayment::class, 'payment_id');
    }
}
