<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductStockMovement extends Model
{
    protected $fillable = [
        'branch_id',
        'product_id',
        'type',
        'quantity_delta',
        'unit_cost_ex_vat',
        'purchase_id',
        'invoice_id',
        'user_id',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'quantity_delta' => 'decimal:3',
            'unit_cost_ex_vat' => 'decimal:2',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(StaffUser::class);
    }
}
