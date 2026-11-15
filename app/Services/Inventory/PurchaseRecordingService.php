<?php

namespace App\Services\Inventory;

use App\Models\Branch;
use App\Models\Purchase;
use App\Models\PurchaseLine;
use App\Models\StaffUser;
use App\Models\Supplier;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PurchaseRecordingService
{
    public function __construct(
        private readonly ProductStockService $stock,
    ) {}

    /**
     * @param  list<array{product_id: int, quantity: float, unit_cost_ex_vat: float, vat_rate: float}>  $lines
     */
    public function record(
        Branch $branch,
        Supplier $supplier,
        array $lines,
        ?string $notes,
        StaffUser $actor,
        ?Carbon $purchasedAt = null,
    ): Purchase {
        $purchasedAt ??= Carbon::now('Asia/Riyadh');

        return DB::transaction(function () use ($branch, $supplier, $lines, $notes, $actor, $purchasedAt): Purchase {
            $purchase = Purchase::query()->create([
                'branch_id' => $branch->id,
                'supplier_id' => $supplier->id,
                'purchase_number' => $this->nextPurchaseNumber($branch),
                'purchased_at' => $purchasedAt,
                'notes' => $notes,
                'created_by_staff_user_id' => $actor->id,
            ]);

            foreach ($lines as $row) {
                $line = PurchaseLine::query()->create([
                    'purchase_id' => $purchase->id,
                    'product_id' => (int) $row['product_id'],
                    'quantity' => round((float) $row['quantity'], 3),
                    'unit_cost_ex_vat' => round((float) $row['unit_cost_ex_vat'], 2),
                    'vat_rate' => round((float) ($row['vat_rate'] ?? 15), 2),
                ]);
                $this->stock->recordPurchaseLine($purchase, $line, $actor->id);
            }

            return $purchase->fresh(['lines', 'supplier']);
        });
    }

    public function findOrCreateSupplier(Branch $branch, string $name, ?string $vat, ?string $phone): Supplier
    {
        $name = trim($name);
        $existing = Supplier::query()
            ->where('branch_id', $branch->id)
            ->where('name', $name)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return Supplier::query()->create([
            'branch_id' => $branch->id,
            'name' => $name,
            'vat_number' => filled($vat) ? trim($vat) : null,
            'phone' => filled($phone) ? trim($phone) : null,
        ]);
    }

    private function nextPurchaseNumber(Branch $branch): string
    {
        $seq = Purchase::query()
            ->where('branch_id', $branch->id)
            ->whereDate('purchased_at', today())
            ->count() + 1;

        return 'PO-'.$branch->code.'-'.now()->format('Ymd').'-'.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
