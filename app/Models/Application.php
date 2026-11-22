<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Application extends Model
{
    protected $table = 'all_applications';

    protected $fillable = [
        'form_slug',
        'form_name',
        'name',
        'phone',
        'address',
        'form_data',
        'payment_method',
        'payment_reference',
        'payment_url',
        'payment_status',
        'payment_amount',
        'merchant_name',
        'submitted',
    ];

    protected $attributes = [
        'submitted' => true,
    ];

    protected $casts = [
        'form_data' => 'array',
        'payment_amount' => 'decimal:2',
        'submitted' => 'boolean',
    ];

    public function scopeForForm(Builder $query, string $formSlug): Builder
    {
        return $query->where('form_slug', $formSlug);
    }

    public function scopeSubmitted(Builder $query): Builder
    {
        return $query->where('submitted', true);
    }

    public function scopePaymentStatus(Builder $query, ?string $status): Builder
    {
        return $query->where('payment_status', $status);
    }
}
