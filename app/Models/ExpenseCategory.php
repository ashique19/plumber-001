<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExpenseCategory extends Model
{
    protected $table = 'business_expense_categories';

    protected $fillable = [
        'slug',
        'name',
    ];

    public function expenses(): HasMany
    {
        return $this->hasMany(BusinessExpense::class, 'category', 'slug');
    }
}