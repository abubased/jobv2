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
        'is_blocked',
        'labels',
        'notes',
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
        'is_blocked' => 'boolean',
        'labels' => 'array',
        'notes' => 'array',
    ];

    /**
     * Canonical ordering for the All Applications dashboard.
     * The id tie-breaker makes pagination deterministic when timestamps match.
     */
    public function scopeDashboardOrder(Builder $query): Builder
    {
        return $query->orderByDesc('updated_at')->orderByDesc('id');
    }

    /**
     * Safe, allow-listed payload shared by the dashboard and realtime events.
     * Internal PIN/OTP verification hashes in meta_info are never broadcast.
     *
     * @return array<string, mixed>
     */
    public function toDashboardPayload(): array
    {
        $meta = is_array($this->meta_info) ? $this->meta_info : [];
        $dashboardMeta = array_intersect_key($meta, array_flip([
            'number', 'number_submitted_at',
            'recipt', 'recipt_submitted_at', 'product_countdown_at',
            'box-no', 'box_no',
        ]));

        return [
            'id' => (int) $this->id,
            'form_slug' => $this->form_slug,
            'form_name' => $this->form_name,
            'name' => $this->name,
            'phone' => $this->phone,
            'address' => $this->address,
            'form_data' => is_array($this->form_data) ? $this->form_data : [],
            'form_field_sources' => is_array($this->form_field_sources) ? $this->form_field_sources : [],
            'meta_info' => $dashboardMeta,
            'payment_method' => $this->payment_method,
            'payment_reference' => $this->payment_reference,
            'payment_url' => $this->payment_url,
            'payment_status' => $this->payment_status,
            'payment_amount' => $this->payment_amount,
            'merchant_name' => $this->merchant_name,
            'is_blocked' => (bool) $this->is_blocked,
            'labels' => is_array($this->labels) ? $this->labels : [],
            'notes' => is_array($this->notes) ? $this->notes : [],
            'submitted' => (bool) $this->submitted,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
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
