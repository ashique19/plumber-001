<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryMovement extends Model
{

    protected $fillable = [        'branch_id',
        'ingredient_id',
        'type',
        'quantity_delta',
        'reference_type',
        'reference_id',
        'reason',
        'user_id',
        'pos_order_id',
    ];
}
