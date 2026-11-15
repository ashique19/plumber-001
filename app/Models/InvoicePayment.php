<?php

namespace App\Models;

use App\Enums\InvoiceArPaymentMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoicePayment extends Model
{
    protected $fillable = [
        'invoice_id',
        'customer_id',
        'paid_on',
        'amount',
        'method',
        'reference',
        'for_retention',
        'recorded_by_id',
    ];

    protected function casts(): array
    {
        return [
            'paid_on' => 'date',
            'amount' => 'decimal:2',
            'method' => InvoiceArPaymentMethod::class,
            'for_retention' => 'boolean',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(StaffUser::class, 'recorded_by_id');
    }
}
