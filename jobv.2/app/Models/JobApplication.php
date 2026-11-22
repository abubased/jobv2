<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobApplication extends Model
{
    protected $fillable = [
        'name',
        'father_or_husband',
        'mother',
        'phone',
        'address',
        'education',
        'courier_help',
        'nearest_courier',
        'payment_number',
        'payment_amount',
        'payment_method',
        'payment_reference',
        'payment_url',
        'submitted',
    ];

    protected $casts = [
        'payment_amount' => 'array',
        'submitted' => 'boolean',
    ];
}
