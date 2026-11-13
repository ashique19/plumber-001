<?php

namespace App\Console\Commands;

use App\Models\Ingredient;
use Illuminate\Console\Command;

class InventoryLowStockCommand extends Command
{
    protected $signature = 'inventory:low-stock';

    protected $description = 'List branch ingredients at or below reorder level.';

    public function handle(): int
    {
        Ingredient::query()
            ->withoutGlobalScopes()
            ->whereColumn('quantity_on_hand', '<=', 'reorder_level')
            ->each(function (Ingredient $ingredient) {
                $this->line("LOW branch {$ingredient->branch_id}: {$ingredient->displayName()} = {$ingredient->quantity_on_hand}");
            });

        return self::SUCCESS;
    }
}
