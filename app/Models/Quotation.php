<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Quotation extends Model
{
    protected $fillable = [
        'branch_id',
        'quotation_number',
        'invoice_mode',
        'validity_until',
        'payload',
        'converted_invoice_id',
        'created_by_staff_user_id',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'validity_until' => 'date',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function convertedInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'converted_invoice_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(StaffUser::class, 'created_by_staff_user_id');
    }
}