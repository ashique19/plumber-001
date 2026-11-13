<?php

namespace App\Models;

use App\Enums\PosOrderStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosOrder extends Model
{

    protected $fillable = [        'branch_id',
        'customer_id',
        'cashier_id',
        'order_number',
        'status',
        'payment_method',
        'subtotal_ex_vat',
        'discount_ex_vat',
        'promo_discount_ex_vat',
        'loyalty_discount_ex_vat',
        'vat_total',
        'total_inc_vat',
        'loyalty_points_redeemed',
        'line_items',
        'metadata',
        'invoice_id',
        'refund_credit_note_id',
        'paid_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => PosOrderStatus::class,
            'subtotal_ex_vat' => 'decimal:2',
            'discount_ex_vat' => 'decimal:2',
            'promo_discount_ex_vat' => 'decimal:2',
            'loyalty_discount_ex_vat' => 'decimal:2',
            'vat_total' => 'decimal:2',
            'total_inc_vat' => 'decimal:2',
            'line_items' => 'array',
            'metadata' => 'array',
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(StaffUser::class, 'cashier_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function refundCreditNote(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'refund_credit_note_id');
    }
}
