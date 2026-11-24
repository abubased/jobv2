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
        'meta_info',
        'address',
        'form_data',
        'form_field_sources',
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
        'meta_info' => 'array',
        'form_data' => 'array',
        'form_field_sources' => 'array',
        'payment_amount' => 'decimal:2',
        'submitted' => 'boolean',
    ];

    /**
     * Canonical ordering for the All Applications dashboard.
     * The id tie-breaker makes pagination deterministic when timestamps match.
     */
    public function scopeDashboardOrder(Builder $query): Builder
    {
        return $query->orderByDesc('updated_at')->orderByDesc('id');
    }

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
