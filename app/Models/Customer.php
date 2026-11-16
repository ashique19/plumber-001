<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{

    protected $fillable = [
        'name',
        'legal_name_ar',
        'legal_name_en',
        'phone',
        'email',
        'vat_number',
        'vat_registered',
        'alt_id_type',
        'alt_id_number',
        'country_code',
        'street_name',
        'city',
        'referral_code',
        'referred_by_customer_id',
        'points_balance',
        'profile',
    ];

    protected function casts(): array
    {
        return [
            'profile' => 'array',
            'vat_registered' => 'boolean',
        ];
    }

    public function loyaltyTransactions(): HasMany
    {
        return $this->hasMany(LoyaltyTransaction::class);
    }

    public function referredBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'referred_by_customer_id');
    }
}
