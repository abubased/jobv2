<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LalcoinApplication extends Model
{
    protected $fillable = [
        'name',
        'address',
        'phone',
        'coin_count',
        'coin_year',
        'submitted',
        'payment_number',
        'payment_amount',
        'payment_method',
        'payment_reference',
        'payment_url',
        'payment_status',
        'meta',
    ];

    protected $casts = [
        'payment_amount' => 'array',
        'submitted' => 'boolean',
        'meta' => 'array',
    ];
}
