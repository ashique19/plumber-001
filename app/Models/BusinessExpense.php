<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessExpense extends Model
{
    protected $fillable = [
        'branch_id',
        'spent_on',
        'category',
        'vendor',
        'description',
        'amount_ex_vat',
        'recorded_by_staff_user_id',
    ];

    protected function casts(): array
    {
        return [
            'spent_on' => 'date',
            'amount_ex_vat' => 'decimal:2',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(StaffUser::class, 'recorded_by_staff_user_id');
    }
}