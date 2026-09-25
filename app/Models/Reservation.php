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
        'reservation_code',
    ];

    protected $casts = [
        'estimated_budget' => 'float',
        'total_cost' => 'float',
        'amount_paid' => 'float',
        'balance' => 'float',
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
}
